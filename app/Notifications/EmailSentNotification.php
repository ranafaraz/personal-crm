<?php

namespace App\Notifications;

use App\Models\EmailMessage;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'email_sent';

    public function __construct(public readonly EmailMessage $emailMessage) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => self::TYPE,
            'title'            => 'Email Sent',
            'body'             => "Email sent to {$this->emailMessage->to_email}: {$this->emailMessage->subject}",
            'icon'             => 'check-circle',
            'action_url'       => route('emails.show', $this->emailMessage->id),
            'action_label'     => 'View Email',
            'email_message_id' => $this->emailMessage->id,
            'to_email'         => $this->emailMessage->to_email,
            'subject'          => $this->emailMessage->subject,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email Sent Successfully')
            ->greeting('Your email was sent!')
            ->line("To: {$this->emailMessage->to_email}")
            ->line("Subject: {$this->emailMessage->subject}")
            ->action('View Email', route('emails.show', $this->emailMessage->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Email Sent',
            'message' => "Sent to {$this->emailMessage->to_email}",
            'url'     => route('emails.show', $this->emailMessage->id),
        ];
    }
}
