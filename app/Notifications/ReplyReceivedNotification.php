<?php

namespace App\Notifications;

use App\Models\InboxMessage;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReplyReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'reply_received';

    public function __construct(public readonly InboxMessage $inboxMessage) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => self::TYPE,
            'title'            => 'New Reply Received',
            'body'             => "Reply from {$this->inboxMessage->from_email}: {$this->inboxMessage->subject}",
            'icon'             => 'reply',
            'action_url'       => route('inbox.show', $this->inboxMessage->id),
            'action_label'     => 'View Reply',
            'inbox_message_id' => $this->inboxMessage->id,
            'from_email'       => $this->inboxMessage->from_email,
            'subject'          => $this->inboxMessage->subject,
            'sentiment'        => $this->inboxMessage->sentiment,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Reply: ' . $this->inboxMessage->subject)
            ->greeting('New reply received!')
            ->line("From: {$this->inboxMessage->from_email}")
            ->line("Subject: {$this->inboxMessage->subject}")
            ->action('View Reply', route('inbox.show', $this->inboxMessage->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'New Reply',
            'message' => "From {$this->inboxMessage->from_email}",
            'url'     => route('inbox.show', $this->inboxMessage->id),
        ];
    }
}
