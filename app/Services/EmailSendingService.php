<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\SuppressionList;
use App\Models\TimelineEvent;
use App\Events\EmailSent;
use App\Events\EmailFailed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailSendingService
{
    /**
     * Send an EmailMessage using the SMTP credentials from its EmailAccount.
     */
    public function sendEmail(EmailMessage $emailMessage): bool
    {
        $emailMessage->load('emailAccount', 'contact', 'opportunity');
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

        // 3. Mark as sending
        $emailMessage->update(['status' => 'sending']);

        try {
            // 4. Build Symfony Mailer transport from account credentials
            $transport = $this->buildTransport($account);
            $mailer    = new Mailer($transport);

            // 5. Build the MIME message
            $mime = new Email();
            $mime->from(new Address($account->email, $account->from_name ?? $account->email));
            $mime->to(new Address(
                $emailMessage->to_email,
                $emailMessage->to_name ?? $emailMessage->to_email
            ));
            $mime->subject($emailMessage->subject);
            $mime->html($emailMessage->body);
            $mime->text(strip_tags($emailMessage->body));

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

            // 6. Send
            $mailer->send($mime);

            // 7. Update account counters
            $account->increment('emails_sent_today');

            // 8. Mark message as sent
            $emailMessage->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'message_id' => $mime->generateMessageId(),
            ]);

            // 9. Create timeline event
            $this->createTimeline($emailMessage, 'email_sent', "Email sent to {$emailMessage->to_email}");

            // 10. Fire event
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

            return ['success' => true, 'message' => 'SMTP connection successful.'];
        } catch (Throwable $e) {
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

        if (app()->isLocal()) {
            /** @var SocketStream $stream */
            $stream = $transport->getStream();
            $stream->setStreamOptions([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);
        }

        return $transport;
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

        $this->createTimeline($emailMessage, 'email_failed', "Email failed: {$reason}");
    }

    /**
     * Create a TimelineEvent attached to the EmailMessage (and its opportunity if present).
     */
    private function createTimeline(EmailMessage $emailMessage, string $eventType, string $description): void
    {
        $base = [
            'user_id'          => $emailMessage->user_id,
            'event_type'       => $eventType,
            'description'      => $description,
            'happened_at'      => now(),
            'metadata'         => ['email_message_id' => $emailMessage->id],
        ];

        // Attach to the EmailMessage itself
        TimelineEvent::create(array_merge($base, [
            'timelineable_id'   => $emailMessage->id,
            'timelineable_type' => EmailMessage::class,
        ]));

        // Also attach to the related Opportunity if present
        if ($emailMessage->opportunity_id) {
            TimelineEvent::create(array_merge($base, [
                'timelineable_id'   => $emailMessage->opportunity_id,
                'timelineable_type' => \App\Models\Opportunity::class,
                'metadata'          => array_merge($base['metadata'], [
                    'opportunity_id' => $emailMessage->opportunity_id,
                ]),
            ]));
        }
    }
}
