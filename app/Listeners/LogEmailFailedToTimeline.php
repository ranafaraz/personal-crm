<?php

namespace App\Listeners;

use App\Events\EmailFailed;
use App\Jobs\DispatchCrmNotificationJob;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Notifications\EmailFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogEmailFailedToTimeline implements ShouldQueue
{
    /**
     * Handle the EmailFailed event.
     *
     * Creates a TimelineEvent on the EmailMessage and, if linked, on the Opportunity.
     */
    public function handle(EmailFailed $event): void
    {
        $emailMessage = $event->message;
        $reason       = $event->reason;

        $base = [
            'user_id'     => $emailMessage->user_id,
            'event_type'  => 'email_failed',
            'description' => "Email to {$emailMessage->to_email} failed: {$reason}",
            'happened_at' => $emailMessage->failed_at ?? now(),
            'metadata'    => [
                'email_message_id' => $emailMessage->id,
                'to_email'         => $emailMessage->to_email,
                'subject'          => $emailMessage->subject,
                'failure_reason'   => $reason,
            ],
        ];

        // Attach to the EmailMessage itself
        TimelineEvent::create(array_merge($base, [
            'timelineable_id'   => $emailMessage->id,
            'timelineable_type' => EmailMessage::class,
        ]));

        // Also attach to the Opportunity if present
        if ($emailMessage->opportunity_id) {
            TimelineEvent::create(array_merge($base, [
                'timelineable_id'   => $emailMessage->opportunity_id,
                'timelineable_type' => Opportunity::class,
                'metadata'          => array_merge($base['metadata'], [
                    'opportunity_id' => $emailMessage->opportunity_id,
                ]),
            ]));
        }

        $user = User::find($emailMessage->user_id);
        if ($user) {
            DispatchCrmNotificationJob::dispatch($user, new EmailFailedNotification($emailMessage, $reason));
        }
    }
}
