<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\SuppressionList;
use App\Events\EmailSent;
use App\Events\EmailFailed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailSendingService
{
    public function __construct(
        private PlanLimitsService $planLimits,
        private EmailTrackingService $tracking,
    ) {
    }

    /**
     * Send an EmailMessage using the SMTP credentials from its EmailAccount.
     */
    public function sendEmail(EmailMessage $emailMessage): bool
    {
        $emailMessage = EmailMessage::query()->findOrFail($emailMessage->id);

        if ($emailMessage->status === 'sent') {
            Log::info('EmailSendingService: email already sent, skipping duplicate send', [
                'email_message_id' => $emailMessage->id,
            ]);

            return true;
        }

        $claimed = EmailMessage::query()
            ->whereKey($emailMessage->id)
            ->whereNotIn('status', ['sent', 'sending'])
            ->update(['status' => 'sending']);

        if ($claimed === 0) {
            Log::warning('EmailSendingService: email is already being sent, skipping duplicate send', [
                'email_message_id' => $emailMessage->id,
            ]);

            return false;
        }

        $emailMessage->refresh();
        $emailMessage->load('emailAccount', 'contact', 'opportunity', 'attachments', 'apiAttachments');
        $account = $emailMessage->emailAccount;

        // 1. Check suppression list
        if ($this->isSuppressed($emailMessage->to_email, $account->user_id)) {
            $reason = "Recipient {$emailMessage->to_email} is on the suppression list.";
            $this->markFailed($emailMessage, $reason);
            event(new EmailFailed($emailMessage, $reason));
            return false;
        }

        // 2. Check daily/hourly limits
        if (!$this->canSendFromAccount($account)) {
            $reason = "Email account '{$account->name}' has reached its send limit.";
            $this->markFailed($emailMessage, $reason);
            event(new EmailFailed($emailMessage, $reason));
            return false;
        }

        // 3. Check the tenant-wide daily plan cap. This message is already
        // claimed as 'sending' so it counts toward usage itself — strictly
        // greater means the cap was already consumed by other sends.
        $tenant = $emailMessage->tenant_id ? \App\Models\Tenant::find($emailMessage->tenant_id) : null;
        if ($tenant) {
            $dailyCap = $this->planLimits->limit($tenant, 'emails_per_day');
            if ($dailyCap !== null && $this->planLimits->usage($tenant, 'emails_per_day') > $dailyCap) {
                $reason = 'Your plan\'s daily email limit has been reached. Upgrade your plan to send more emails per day.';
                $this->markFailed($emailMessage, $reason);
                event(new EmailFailed($emailMessage, $reason));
                return false;
            }
        }

        // 4. Apply per-account send delay with jitter to avoid regularity detection
        $this->applyAccountSendDelay($account);

        try {
            // 5. Build Symfony Mailer transport from account credentials
            $transport = $this->buildTransport($account);
            $mailer    = new Mailer($transport);

            // 6. Build the MIME message
            $mime = new Email();
            $mime->from(new Address($account->email, $account->from_name ?? $account->email));
            $mime->to(new Address(
                $emailMessage->to_email,
                $emailMessage->to_name ?? $emailMessage->to_email
            ));
            $mime->subject($emailMessage->subject);
            // Open/click tracking markup goes into the outgoing MIME only;
            // the stored body stays clean.
            $mime->html($this->tracking->prepareHtml($emailMessage, $emailMessage->body));
            $mime->text(strip_tags($emailMessage->body));

            $messageId = $emailMessage->message_id
                ? self::normalizeMessageId($emailMessage->message_id)
                : $this->generateMessageId($account);

            $mime->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));

            // One-click List-Unsubscribe (required by Gmail/Yahoo for bulk senders since Feb 2024)
            $unsubUrl = URL::signedRoute('unsubscribe', ['message' => $emailMessage->id]);
            $mime->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubUrl}>");
            $mime->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

            // CC / BCC
            if (!empty($emailMessage->cc)) {
                foreach ($emailMessage->cc as $cc) {
                    $mime->addCc(is_array($cc) ? new Address($cc['email'], $cc['name'] ?? '') : $cc);
                }
            }
            if (!empty($emailMessage->bcc)) {
                foreach ($emailMessage->bcc as $bcc) {
                    $mime->addBcc(is_array($bcc) ? new Address($bcc['email'], $bcc['name'] ?? '') : $bcc);
                }
            }

            $this->attachFiles($mime, $emailMessage);

            // 6. Send
            $mailer->send($mime);

            // 7. Update account counters
            $account->increment('emails_sent_today');

            // 8. Mark message as sent
            $emailMessage->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'message_id' => $messageId,
            ]);

            // 9. Fire event. Timeline + CRM notifications are handled by listeners.
            event(new EmailSent($emailMessage));

            return true;

        } catch (Throwable $e) {
            Log::error('EmailSendingService: failed to send email', [
                'email_message_id' => $emailMessage->id,
                'error'            => $e->getMessage(),
            ]);

            $reason = $e->getMessage();
            $this->markFailed($emailMessage, $reason);
            event(new EmailFailed($emailMessage, $reason));

            return false;
        }
    }

    /**
     * Determine whether the given account is allowed to send another email.
     */
    public function canSendFromAccount(EmailAccount $account): bool
    {
        if (!$account->is_active) {
            return false;
        }

        // Daily limit (0 = unlimited)
        if ($account->daily_limit > 0 && $account->emails_sent_today >= $account->daily_limit) {
            return false;
        }

        // Hourly limit — count emails sent in the last 60 minutes
        if ($account->hourly_limit > 0) {
            $sentLastHour = $account->emailMessages()
                ->where('status', 'sent')
                ->where('sent_at', '>=', now()->subHour())
                ->count();

            if ($sentLastHour >= $account->hourly_limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reset emails_sent_today for all accounts whose counter was last reset before today.
     */
    public function resetDailyCounters(): void
    {
        $today = Carbon::today();

        EmailAccount::query()
            ->where(function ($q) use ($today) {
                $q->whereNull('last_reset_at')
                  ->orWhere('last_reset_at', '<', $today);
            })
            ->update([
                'emails_sent_today' => 0,
                'last_reset_at'     => now(),
            ]);
    }

    public static function normalizeMessageId(?string $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }

        $messageId = trim(preg_replace('/\s+/', ' ', $messageId) ?? '');
        $messageId = trim($messageId, " \t\n\r\0\x0B\"'<>;,");

        if ($messageId === '' || ! str_contains($messageId, '@')) {
            return null;
        }

        return '<' . strtolower($messageId) . '>';
    }

    /**
     * @return array<int, string>
     */
    public static function extractMessageIds(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        preg_match_all('/<[^>]+>|[^\s,;<>]+@[^\s,;<>]+/', $header, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $id) => self::normalizeMessageId($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Test the SMTP connection for the given account.
     *
     * @return array{success: bool, message: string}
     */
    public function testSmtpConnection(EmailAccount $account): array
    {
        try {
            $transport = $this->buildTransport($account);
            // EsmtpTransport connects lazily; calling start() forces a connection.
            $transport->start();
            $transport->stop();

            $account->update([
                'smtp_status'           => 'ok',
                'smtp_last_checked_at'  => now(),
                'smtp_last_error'       => null,
            ]);

            return ['success' => true, 'message' => 'SMTP connection successful.'];
        } catch (Throwable $e) {
            $account->update([
                'smtp_status'           => 'error',
                'smtp_last_checked_at'  => now(),
                'smtp_last_error'       => mb_substr($e->getMessage(), 0, 500),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Symfony EsmtpTransport from the EmailAccount's SMTP settings.
     */
    private function buildTransport(EmailAccount $account): EsmtpTransport
    {
        $encryption = strtolower($account->smtp_encryption ?? 'tls');

        // ssl  → implicit TLS on port 465 (tls = true in Symfony terms)
        // tls  → STARTTLS on port 587 (tls = false, STARTTLS negotiated)
        // none → plain, no encryption
        $useSsl = match ($encryption) {
            'ssl'  => true,
            default => false,
        };

        $transport = new EsmtpTransport(
            host: $account->smtp_host,
            port: $account->smtp_port,
            tls: $useSsl,
        );

        $transport->setUsername($account->smtp_username);
        $transport->setPassword($account->smtp_password);

        // For STARTTLS ('tls') keep auto-tls on (default).
        // For 'none' disable auto-tls so Symfony won't try STARTTLS.
        if ($encryption === 'none') {
            $transport->setAutoTls(false);
        }

        // Disable certificate verification when running locally or with self-signed certs.
        // This can be made configurable per account if needed.
        /** @var SocketStream $stream */
        $stream = $transport->getStream();
        $stream->setStreamOptions([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        return $transport;
    }

    private function generateMessageId(EmailAccount $account): string
    {
        $domain = strtolower(trim(Str::after($account->email, '@')));

        if ($domain === '' || $domain === $account->email) {
            $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        }

        return '<' . Str::uuid()->toString() . '@' . $domain . '>';
    }

    private function attachFiles(Email $mime, EmailMessage $emailMessage): void
    {
        foreach ($emailMessage->attachments as $attachment) {
            $path = Storage::disk('local')->path($attachment->file_path);

            if (is_file($path)) {
                $mime->attachFromPath($path, $attachment->file_name, $attachment->mime_type);
            } else {
                Log::warning('EmailSendingService: local attachment file missing', [
                    'email_message_id' => $emailMessage->id,
                    'attachment_id'    => $attachment->id,
                    'file_path'        => $attachment->file_path,
                ]);
            }
        }

        foreach ($emailMessage->apiAttachments as $attachment) {
            if (! $attachment->file_path) {
                continue;
            }

            $disk = Storage::disk($attachment->storage_disk ?: 'local');
            $candidates = [$attachment->file_path, 'private/' . ltrim($attachment->file_path, '/')];
            $path = null;

            foreach ($candidates as $candidate) {
                if ($disk->exists($candidate)) {
                    $path = $disk->path($candidate);
                    break;
                }
            }

            if ($path && is_file($path)) {
                $mime->attachFromPath($path, $attachment->filename, $attachment->mime_type);
            } else {
                Log::warning('EmailSendingService: API attachment file missing', [
                    'email_message_id' => $emailMessage->id,
                    'attachment_id'    => $attachment->id,
                    'file_path'        => $attachment->file_path,
                ]);
            }
        }
    }

    /**
     * Check whether an email address is on the suppression list for the user.
     */
    private function isSuppressed(string $email, int $userId): bool
    {
        return SuppressionList::where('user_id', $userId)
            ->where('email', strtolower($email))
            ->exists();
    }

    /**
     * Mark an EmailMessage as failed.
     */
    private function markFailed(EmailMessage $emailMessage, string $reason): void
    {
        $emailMessage->update([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Enforce the account's min_delay_seconds between sends, adding ±20% jitter
     * so emails don't arrive at clock-regular intervals (a spam signal).
     */
    private function applyAccountSendDelay(EmailAccount $account): void
    {
        $minDelay = $account->min_delay_seconds ?? 0;
        if ($minDelay <= 0) {
            return;
        }

        $lastSentAt = $account->emailMessages()
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->value('sent_at');

        if ($lastSentAt === null) {
            return;
        }

        $secondsSinceLast = (int) now()->diffInSeconds($lastSentAt, absolute: true);

        // ±20% jitter so each send has a slightly different gap
        $jitter      = max(1, (int) ($minDelay * 0.2));
        $targetDelay = $minDelay + random_int(-$jitter, $jitter);

        if ($secondsSinceLast < $targetDelay) {
            $sleepFor = $targetDelay - $secondsSinceLast;
            Log::info('EmailSendingService: applying send delay', [
                'email_account_id' => $account->id,
                'sleep_seconds'    => $sleepFor,
            ]);
            sleep($sleepFor);
        }
    }
}
