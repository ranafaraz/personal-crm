<?php

namespace App\Listeners;

use App\Events\EmailSent;
use App\Jobs\DispatchCrmNotificationJob;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Notifications\EmailSentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogEmailSentToTimeline implements ShouldQueue
{
    /**
     * Handle the EmailSent event.
     *
     * Creates a TimelineEvent on the EmailMessage and, if linked, on the Opportunity.
     */
    public function handle(EmailSent $event): void
    {
        $emailMessage = $event->message;

        $base = [
            'user_id'    => $emailMessage->user_id,
            'event_type' => 'email_sent',
            'description' => "Email sent to {$emailMessage->to_email}: \"{$emailMessage->subject}\"",
            'happened_at' => $emailMessage->sent_at ?? now(),
            'metadata'   => [
                'email_message_id' => $emailMessage->id,
                'to_email'         => $emailMessage->to_email,
                'subject'          => $emailMessage->subject,
                'is_follow_up'     => $emailMessage->is_follow_up,
                'follow_up_number' => $emailMessage->follow_up_number,
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

            // Bump last_activity_at on the opportunity
            Opportunity::where('id', $emailMessage->opportunity_id)
                ->update(['last_activity_at' => now()]);
        }

        $user = User::find($emailMessage->user_id);
        if ($user) {
            DispatchCrmNotificationJob::dispatch($user, new EmailSentNotification($emailMessage));
        }
    }
}
