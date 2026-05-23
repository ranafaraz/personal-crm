<?php

namespace App\Notifications;

use App\Models\InboxMessage;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PositiveReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'positive_reply';

    public function __construct(public readonly InboxMessage $inboxMessage) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => self::TYPE,
            'title'            => 'Positive Reply!',
            'body'             => "Great news! {$this->inboxMessage->from_email} sent a positive reply.",
            'icon'             => 'star',
            'action_url'       => route('inbox.show', $this->inboxMessage->id),
            'action_label'     => 'Read Reply',
            'inbox_message_id' => $this->inboxMessage->id,
            'from_email'       => $this->inboxMessage->from_email,
            'subject'          => $this->inboxMessage->subject,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Positive Reply from ' . $this->inboxMessage->from_email)
            ->greeting('You got a positive reply!')
            ->line("From: {$this->inboxMessage->from_email}")
            ->line("Subject: {$this->inboxMessage->subject}")
            ->action('Read Reply', route('inbox.show', $this->inboxMessage->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Positive Reply!',
            'message' => "From {$this->inboxMessage->from_email}",
            'url'     => route('inbox.show', $this->inboxMessage->id),
        ];
    }
}
