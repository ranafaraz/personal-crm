<?php

namespace App\Services;

use App\Events\ReplyReceived;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
            $folder = $client->getFolder('INBOX');

            // Fetch since last_sync_at, or the last 7 days if never synced
            $since = $account->last_sync_at
                ? $account->last_sync_at->subMinutes(5)  // small overlap to avoid gaps
                : Carbon::now()->subDays(7);

            $messages = $folder->query()
                ->since($since)
                ->leaveUnread()
                ->get();

            foreach ($messages as $imapMessage) {
                try {
                    $inboxMessage = $this->processMessage($imapMessage, $account);

                    if ($inboxMessage === null) {
                        // Already stored — skip
                        continue;
                    }

                    $stats['synced']++;

                    if ($inboxMessage->matched_contact_id || $inboxMessage->matched_outbound_id) {
                        $stats['matched']++;
                    }
                } catch (Throwable $e) {
                    Log::warning('ImapSyncService: failed to process message', [
                        'email_account_id' => $account->id,
                        'error'            => $e->getMessage(),
                    ]);
                    $stats['errors'][] = $e->getMessage();
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
            return ['success' => true, 'message' => 'IMAP connection successful.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel all pending follow-ups for the opportunity+contact matched by a reply.
     */
    public function cancelFollowUpsOnReply(InboxMessage $reply): void
    {
        if (!$reply->matched_opportunity_id && !$reply->matched_contact_id) {
            return;
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
        ]);
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

        // a. Idempotency check
        $existing = InboxMessage::where('email_account_id', $account->id)
            ->where('uid', $uid)
            ->first();

        if ($existing) {
            return null;
        }

        // Extract headers
        $fromAddress  = $this->extractEmail($imapMsg->getFrom());
        $fromName     = $this->extractName($imapMsg->getFrom());
        $messageId    = $this->headerString($imapMsg->getMessageId());
        $inReplyTo    = $this->headerString($imapMsg->getInReplyTo());
        $references   = $this->headerString($imapMsg->getReferences());
        $subject      = (string) ($imapMsg->getSubject() ?? '');
        $receivedAt   = $this->parseDate($imapMsg->getDate()) ?? now();

        // b. Store inbox message
        /** @var InboxMessage $inboxMessage */
        $inboxMessage = InboxMessage::create([
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

        if ($contact) {
            $updates['matched_contact_id'] = $contact->id;
        }

        if ($outbound) {
            $updates['matched_outbound_id']   = $outbound->id;
            $updates['matched_opportunity_id'] = $outbound->opportunity_id;
        }

        if (!empty($updates)) {
            $inboxMessage->update($updates);
            $inboxMessage->refresh();
        }

        // f. Create timeline events
        $this->createTimelineEvents($inboxMessage, $account);

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
            $byHeader = (clone $query)->where('message_id', $inReplyTo)->first();
            if ($byHeader) {
                return $byHeader;
            }
        }

        // 2. Match by References header (any message_id in the thread)
        if ($references) {
            $refIds = preg_split('/\s+/', trim($references));
            foreach ($refIds as $refId) {
                if (empty($refId)) {
                    continue;
                }
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

        return (string) $from;
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
}
