<?php

namespace App\Notifications;

use App\Models\EmailMessage;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'email_failed';

    public function __construct(
        public readonly EmailMessage $emailMessage,
        public readonly string $reason = '',
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => self::TYPE,
            'title'            => 'Email Send Failed',
            'body'             => "Failed to send email to {$this->emailMessage->to_email}." . ($this->reason ? " Reason: {$this->reason}" : ''),
            'icon'             => 'x-circle',
            'action_url'       => route('emails.show', $this->emailMessage->id),
            'action_label'     => 'View Email',
            'email_message_id' => $this->emailMessage->id,
            'to_email'         => $this->emailMessage->to_email,
            'subject'          => $this->emailMessage->subject,
            'reason'           => $this->reason,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email Send Failed')
            ->error()
            ->greeting('An email failed to send.')
            ->line("To: {$this->emailMessage->to_email}")
            ->line("Subject: {$this->emailMessage->subject}")
            ->when($this->reason, fn ($m) => $m->line("Reason: {$this->reason}"))
            ->action('View Email', route('emails.show', $this->emailMessage->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Email Send Failed',
            'message' => "Failed to send to {$this->emailMessage->to_email}",
            'url'     => route('emails.show', $this->emailMessage->id),
        ];
    }
}
