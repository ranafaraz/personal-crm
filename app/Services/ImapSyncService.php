<?php

namespace App\Services;

use App\Events\ReplyReceived;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxAttachment;
use App\Models\InboxMessage;
use App\Models\SuppressionList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

class ImapSyncService
{
    /**
     * Sync the INBOX of an EmailAccount, storing new messages and matching them
     * to existing contacts / outbound emails.
     *
     * @return array{synced: int, matched: int, errors: array<int, string>}
     */
    public function syncAccount(EmailAccount $account): array
    {
        $stats = ['synced' => 0, 'matched' => 0, 'errors' => []];

        $client = $this->buildClient($account);

        try {
            $client->connect();
        } catch (Throwable $e) {
            Log::error('ImapSyncService: connection failed', [
                'email_account_id' => $account->id,
                'error'            => $e->getMessage(),
            ]);
            $stats['errors'][] = 'Connection failed: ' . $e->getMessage();
            return $stats;
        }

        try {
            // Fetch since last_sync_at, or the last 24h if never synced (cap at 7 days max)
            $since = $account->last_sync_at
                ? $account->last_sync_at->subMinutes(5)  // small overlap to avoid gaps
                : Carbon::now()->subDays(1);

            foreach ($this->syncFolderNames($account) as $folderName) {
                try {
                    $folder = $client->getFolder($folderName);
                    if ($folder === null) {
                        continue;
                    }
                } catch (Throwable $e) {
                    Log::info('ImapSyncService: folder unavailable, skipping', [
                        'email_account_id' => $account->id,
                        'folder'           => $folderName,
                        'error'            => $e->getMessage(),
                    ]);
                    continue;
                }

                $messages = $folder->query()
                    ->since($since)
                    ->leaveUnread()
                    ->get();

                foreach ($messages as $imapMessage) {
                    try {
                        $inboxMessage = $this->processMessage($imapMessage, $account);

                        if ($inboxMessage === null) {
                            // Already stored, self-sent, or irrelevant — skip
                            continue;
                        }

                        $stats['synced']++;

                        if ($inboxMessage->matched_contact_id || $inboxMessage->matched_outbound_id) {
                            $stats['matched']++;
                        }
                    } catch (Throwable $e) {
                        Log::warning('ImapSyncService: failed to process message', [
                            'email_account_id' => $account->id,
                            'folder'           => $folderName,
                            'error'            => $e->getMessage(),
                        ]);
                        $stats['errors'][] = $e->getMessage();
                    }
                }
            }

            // Update last_sync_at
            $account->update(['last_sync_at' => now()]);

        } catch (Throwable $e) {
            Log::error('ImapSyncService: sync error', [
                'email_account_id' => $account->id,
                'error'            => $e->getMessage(),
            ]);
            $stats['errors'][] = $e->getMessage();
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // Ignore disconnect errors
            }
        }

        return $stats;
    }

    /**
     * Test IMAP connectivity for an account without storing anything.
     *
     * @return array{success: bool, message: string}
     */
    public function testImapConnection(EmailAccount $account): array
    {
        try {
            $client = $this->buildClient($account);
            $client->connect();
            $client->disconnect();

            $account->update([
                'imap_status'           => 'ok',
                'imap_last_checked_at'  => now(),
                'imap_last_error'       => null,
            ]);

            return ['success' => true, 'message' => 'IMAP connection successful.'];
        } catch (Throwable $e) {
            $account->update([
                'imap_status'           => 'error',
                'imap_last_checked_at'  => now(),
                'imap_last_error'       => mb_substr($e->getMessage(), 0, 500),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel all pending follow-ups for the opportunity+contact matched by a reply.
     */
    public function cancelFollowUpsOnReply(InboxMessage $reply): void
    {
        if (!$reply->matched_outbound_id && !$reply->matched_opportunity_id && !$reply->matched_contact_id) {
            return;
        }

        if ($reply->matched_outbound_id) {
            FollowUp::query()
                ->where('status', 'pending')
                ->where('email_message_id', $reply->matched_outbound_id)
                ->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => 'reply_received',
                ]);
        }

        $query = FollowUp::query()->where('status', 'pending');

        if ($reply->matched_opportunity_id) {
            $query->where('opportunity_id', $reply->matched_opportunity_id);
        }

        if ($reply->matched_contact_id) {
            $query->where('contact_id', $reply->matched_contact_id);
        }

        $query->update([
            'status'        => 'cancelled',
            'cancel_reason' => 'reply_received',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a webklex/php-imap Client from EmailAccount credentials.
     */
    private function buildClient(EmailAccount $account): \Webklex\PHPIMAP\Client
    {
        $clientManager = new ClientManager();

        return $clientManager->make([
            'host'          => $account->imap_host,
            'port'          => $account->imap_port,
            'encryption'    => $account->imap_encryption,
            'validate_cert' => false,
            'username'      => $account->imap_username,
            'password'      => $account->imap_password,
            'protocol'      => 'imap',
            'timeout'       => 30,
        ]);
    }

    /**
     * Gmail filters and threaded conversations can place real replies in
     * All Mail, Spam, or Junk without the INBOX label. Other providers stay
     * on INBOX.
     *
     * @return array<int, string>
     */
    private function syncFolderNames(EmailAccount $account): array
    {
        $folders = ['INBOX'];

        if (str_contains(strtolower($account->imap_host), 'gmail.com')) {
            $folders[] = '[Gmail]/All Mail';
            $folders[] = '[Gmail]/Spam';
            $folders[] = '[Gmail]/Junk';
        }

        return $folders;
    }

    /**
     * Process a single IMAP message: store it, match it, fire events.
     *
     * @return InboxMessage|null  null if the message was already stored
     */
    private function processMessage(
        \Webklex\PHPIMAP\Message $imapMsg,
        EmailAccount $account,
    ): ?InboxMessage {
        $uid = (string) $imapMsg->getUid();

        // Extract headers
        $fromAddress  = $this->extractEmail($imapMsg->getFrom());
        $fromName     = $this->extractName($imapMsg->getFrom());
        $messageId    = EmailSendingService::normalizeMessageId($this->headerString($imapMsg->getMessageId()));
        $inReplyTo    = EmailSendingService::normalizeMessageId($this->headerString($imapMsg->getInReplyTo()));
        $references   = $this->headerString($imapMsg->getReferences());
        $subject      = (string) ($imapMsg->getSubject() ?? '');
        $receivedAt   = $this->parseDate($imapMsg->getDate()) ?? now();

        if (strtolower(trim($fromAddress)) === strtolower(trim($account->email))) {
            return null;
        }

        // Bounce / delivery-failure detection — handle without creating an InboxMessage
        if ($this->isBounce($fromAddress, $subject)) {
            $this->handleBounce($imapMsg, $account, $fromAddress);
            return null;
        }

        // a. Idempotency check. Gmail can expose the same message through
        // INBOX and All Mail with different UIDs, so Message-ID is preferred.
        $existing = InboxMessage::where('email_account_id', $account->id)
            ->where(function ($query) use ($uid, $messageId) {
                $query->where('uid', $uid);

                if ($messageId) {
                    $query->orWhere('message_id', $messageId);
                }
            })
            ->first();

        if ($existing) {
            return null;
        }

        // b. Store inbox message
        /** @var InboxMessage $inboxMessage */
        $inboxMessage = InboxMessage::create([
            'tenant_id'             => $account->tenant_id,
            'user_id'               => $account->user_id,
            'email_account_id'      => $account->id,
            'uid'                   => $uid,
            'message_id'            => $messageId,
            'in_reply_to'           => $inReplyTo,
            'from_email'            => strtolower(trim($fromAddress)),
            'from_name'             => $fromName,
            'subject'               => $subject,
            'body_text'             => (string) ($imapMsg->getTextBody() ?? ''),
            'body_html'             => (string) ($imapMsg->getHTMLBody() ?? ''),
            'received_at'           => $receivedAt,
            'is_read'               => false,
            'matched_contact_id'    => null,
            'matched_opportunity_id'=> null,
            'matched_outbound_id'   => null,
            'review_status'         => 'pending',
            'sentiment'             => 'unknown',
        ]);

        // c. Match to a Contact by sender email
        $contact = Contact::where('user_id', $account->user_id)
            ->where('email', strtolower(trim($fromAddress)))
            ->first();

        // d. Match to an outbound EmailMessage via headers or subject
        $outbound = $this->matchOutbound(
            $account->user_id,
            $inReplyTo,
            $references,
            $subject,
            $contact,
        );

        // e. Persist matches
        $updates = [];

        if ($outbound) {
            $updates['matched_outbound_id']   = $outbound->id;
            $updates['matched_opportunity_id'] = $outbound->opportunity_id;
            $updates['matched_contact_id']     = $outbound->contact_id ?: $contact?->id;
        } elseif ($contact) {
            $updates['matched_contact_id'] = $contact->id;
        }

        if (!empty($updates)) {
            $inboxMessage->update($updates);
            $inboxMessage->refresh();
        }

        // f. Store inbound attachments, if any
        $this->storeAttachments($imapMsg, $inboxMessage);

        // g. Cancel pending follow-ups if this looks like a reply
        if ($outbound || $inboxMessage->matched_opportunity_id) {
            $this->cancelFollowUpsOnReply($inboxMessage);

            // Fire ReplyReceived event
            event(new ReplyReceived($inboxMessage));
        }

        return $inboxMessage;
    }

    /**
     * Try to find an outbound EmailMessage that this inbox message is replying to.
     */
    private function matchOutbound(
        int $userId,
        ?string $inReplyTo,
        ?string $references,
        string $subject,
        ?Contact $contact,
    ): ?EmailMessage {
        $query = EmailMessage::where('user_id', $userId)
            ->where('direction', 'outbound')
            ->where('status', 'sent');

        // 1. Match by In-Reply-To header against sent message_id
        if ($inReplyTo) {
            $byHeader = (clone $query)->where('message_id', EmailSendingService::normalizeMessageId($inReplyTo))->first();
            if ($byHeader) {
                return $byHeader;
            }
        }

        // 2. Match by References header (any message_id in the thread)
        if ($references) {
            foreach (EmailSendingService::extractMessageIds($references) as $refId) {
                $byRef = (clone $query)->where('message_id', $refId)->first();
                if ($byRef) {
                    return $byRef;
                }
            }
        }

        // 3. Fuzzy match by subject (strip Re:/Fwd: prefixes) + contact
        if ($contact) {
            $normalizedSubject = $this->normalizeSubject($subject);
            if ($normalizedSubject !== '') {
                $bySubject = (clone $query)
                    ->where('contact_id', $contact->id)
                    ->where('subject', 'like', '%' . $normalizedSubject . '%')
                    ->orderByDesc('sent_at')
                    ->first();
                if ($bySubject) {
                    return $bySubject;
                }
            }
        }

        return null;
    }

    /**
     * Strip common reply/forward prefixes to get the base subject.
     */
    private function normalizeSubject(string $subject): string
    {
        $cleaned = preg_replace('/^(Re|Fwd|Fw|R|AW|Aw):\s*/iu', '', $subject);
        return trim($cleaned ?? $subject);
    }

    /**
     * Create timeline events when an inbox message arrives.
     */
    private function createTimelineEvents(InboxMessage $inboxMessage, EmailAccount $account): void
    {
        $meta = [
            'inbox_message_id' => $inboxMessage->id,
            'from_email'       => $inboxMessage->from_email,
        ];

        // Timeline on the matched opportunity
        if ($inboxMessage->matched_opportunity_id) {
            \App\Models\TimelineEvent::create([
                'user_id'           => $account->user_id,
                'timelineable_id'   => $inboxMessage->matched_opportunity_id,
                'timelineable_type' => \App\Models\Opportunity::class,
                'event_type'        => 'reply_received',
                'description'       => "Reply received from {$inboxMessage->from_email}: {$inboxMessage->subject}",
                'metadata'          => $meta,
                'happened_at'       => $inboxMessage->received_at ?? now(),
            ]);
        }

        // Timeline on the matched contact
        if ($inboxMessage->matched_contact_id) {
            \App\Models\TimelineEvent::create([
                'user_id'           => $account->user_id,
                'timelineable_id'   => $inboxMessage->matched_contact_id,
                'timelineable_type' => Contact::class,
                'event_type'        => 'reply_received',
                'description'       => "Reply received from {$inboxMessage->from_email}: {$inboxMessage->subject}",
                'metadata'          => $meta,
                'happened_at'       => $inboxMessage->received_at ?? now(),
            ]);
        }
    }

    private function storeAttachments(\Webklex\PHPIMAP\Message $imapMsg, InboxMessage $inboxMessage): void
    {
        foreach ($imapMsg->getAttachments() as $attachment) {
            try {
                $fileName = $this->safeAttachmentName(
                    (string) ($attachment->getName() ?: $attachment->getFilename() ?: 'attachment')
                );
                $directory = "inbox-attachments/{$inboxMessage->id}";
                $path = "{$directory}/" . Str::uuid()->toString() . '_' . $fileName;
                $content = (string) $attachment->getContent();

                Storage::disk('local')->put($path, $content);

                InboxAttachment::create([
                    'inbox_message_id' => $inboxMessage->id,
                    'file_name'        => $fileName,
                    'file_path'        => $path,
                    'mime_type'        => $attachment->getMimeType(),
                    'file_size'        => strlen($content),
                ]);
            } catch (Throwable $e) {
                Log::warning('ImapSyncService: failed to store inbound attachment', [
                    'inbox_message_id' => $inboxMessage->id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function safeAttachmentName(string $fileName): string
    {
        $fileName = trim($fileName) ?: 'attachment';
        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'attachment';

        return mb_substr($fileName, 0, 180);
    }

    // -------------------------------------------------------------------------
    // IMAP header extraction helpers
    // -------------------------------------------------------------------------

    private function extractEmail(mixed $from): string
    {
        if ($from instanceof \Webklex\PHPIMAP\Support\AddressCollection) {
            $first = $from->first();
            return $first?->mail ?? '';
        }

        if (is_array($from)) {
            return $from[0]?->mail ?? $from[0]?->email ?? '';
        }

        $value = (string) $from;
        if (preg_match('/<([^>]+@[^>]+)>/', $value, $matches)) {
            return $matches[1];
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }

    private function extractName(mixed $from): string
    {
        if ($from instanceof \Webklex\PHPIMAP\Support\AddressCollection) {
            $first = $from->first();
            return $first?->personal ?? $first?->name ?? '';
        }

        if (is_array($from)) {
            return $from[0]?->personal ?? $from[0]?->name ?? '';
        }

        $value = (string) $from;
        if (preg_match('/^(.+?)\s*<[^>]+@[^>]+>/', $value, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"'");
        }

        return '';
    }

    private function headerString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = (string) $value;
        return $str !== '' ? trim($str) : null;
    }

    private function parseDate(mixed $date): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        try {
            if ($date instanceof \Carbon\Carbon) {
                return $date;
            }
            return Carbon::parse((string) $date);
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Bounce detection
    // -------------------------------------------------------------------------

    private const BOUNCE_SENDERS = [
        'mailer-daemon', 'postmaster', 'mail-daemon', 'noreply@bounce',
        'bounce', 'no-reply@', 'delivery-notification',
    ];

    private const BOUNCE_SUBJECTS = [
        'undeliverable', 'delivery status notification', 'delivery status report',
        'delivery failure', 'failed delivery', 'returned mail',
        'mail delivery failed', 'mail delivery failure',
        'message not delivered', 'delivery notification', 'non-delivery',
        'could not be delivered',
    ];

    private function isBounce(string $fromAddress, string $subject): bool
    {
        $fromLower    = strtolower($fromAddress);
        $subjectLower = strtolower($subject);

        foreach (self::BOUNCE_SENDERS as $pattern) {
            if (str_contains($fromLower, $pattern)) {
                return true;
            }
        }

        foreach (self::BOUNCE_SUBJECTS as $keyword) {
            if (str_contains($subjectLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a bounce notification, mark the original outbound message as bounced,
     * and suppress the recipient so we don't retry sending.
     */
    private function handleBounce(
        \Webklex\PHPIMAP\Message $imapMsg,
        EmailAccount $account,
        string $bounceFrom,
    ): void {
        $body = (string) ($imapMsg->getTextBody() ?? $imapMsg->getHTMLBody() ?? '');

        // Extract all email addresses mentioned in the bounce body
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $body, $m);
        $candidates = array_unique($m[0] ?? []);

        foreach ($candidates as $email) {
            // Skip the sending account's own address
            if (strcasecmp($email, $account->email) === 0) {
                continue;
            }

            $outbound = EmailMessage::where('email_account_id', $account->id)
                ->where('to_email', strtolower($email))
                ->where('status', 'sent')
                ->whereNull('bounced_at')
                ->orderByDesc('sent_at')
                ->first();

            if (!$outbound) {
                continue;
            }

            $outbound->update([
                'bounced_at'  => now(),
                'bounce_type' => 'hard',
            ]);

            // Suppress so we never retry sending to this address
            SuppressionList::firstOrCreate(
                ['user_id' => $account->user_id, 'email' => strtolower($email)],
                [
                    'tenant_id' => $account->tenant_id,
                    'reason'    => 'bounced',
                    'notes'     => 'Auto-added from bounce notification received ' . now()->toDateTimeString(),
                ],
            );

            Log::info('ImapSyncService: bounce handled', [
                'email_account_id' => $account->id,
                'bounced_email'    => $email,
                'outbound_id'      => $outbound->id,
            ]);
        }
    }
}
