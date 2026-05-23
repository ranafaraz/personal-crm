<?php

namespace App\Notifications;

use App\Models\FollowUp;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'followup_due';

    public function __construct(public readonly FollowUp $followUp) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        $opportunity = $this->followUp->opportunity;

        return [
            'type'           => self::TYPE,
            'title'          => 'Follow-up Due',
            'body'           => 'Follow-up due for: ' . ($opportunity?->title ?? 'an opportunity'),
            'icon'           => 'clock',
            'action_url'     => route('follow-ups.show', $this->followUp->id),
            'action_label'   => 'View Follow-up',
            'follow_up_id'   => $this->followUp->id,
            'opportunity_id' => $opportunity?->id,
            'due_at'         => $this->followUp->due_at?->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Follow-up Due: ' . ($this->followUp->opportunity?->title ?? ''))
            ->greeting('You have a follow-up due!')
            ->line('Opportunity: ' . ($this->followUp->opportunity?->title ?? 'N/A'))
            ->line('Due: ' . $this->followUp->due_at?->format('M d, Y'))
            ->action('View Follow-up', route('follow-ups.show', $this->followUp->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Follow-up Due',
            'message' => $this->followUp->opportunity?->title ?? 'Check your follow-ups',
            'url'     => route('follow-ups.show', $this->followUp->id),
        ];
    }
}
