<?php

namespace App\Listeners;

use App\Events\ReplyReceived;
use App\Jobs\DispatchCrmNotificationJob;
use App\Models\Contact;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Notifications\PositiveReplyNotification;
use App\Notifications\ReplyReceivedNotification;
use App\Services\ImapSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleReplyReceived implements ShouldQueue
{
    public function __construct(
        private readonly ImapSyncService $imapSyncService,
    ) {}

    /**
     * Handle the ReplyReceived event.
     *
     * 1. Cancels pending follow-ups for the matched opportunity + contact.
     * 2. Creates timeline events on the matched opportunity and contact.
     * 3. Updates the opportunity status to 'replied' if it was 'waiting_reply'.
     * 4. Bumps last_activity_at on the opportunity.
     * 5. Updates last_contacted_at on the matched contact.
     */
    public function handle(ReplyReceived $event): void
    {
        $inboxMessage = $event->inboxMessage;

        // 1. Cancel pending follow-ups
        $this->imapSyncService->cancelFollowUpsOnReply($inboxMessage);

        $meta = [
            'inbox_message_id' => $inboxMessage->id,
            'from_email'       => $inboxMessage->from_email,
            'subject'          => $inboxMessage->subject,
            'sentiment'        => $inboxMessage->sentiment,
        ];

        // 2. Create timeline event on the matched Opportunity
        if ($inboxMessage->matched_opportunity_id) {
            TimelineEvent::create([
                'user_id'           => $inboxMessage->user_id,
                'timelineable_id'   => $inboxMessage->matched_opportunity_id,
                'timelineable_type' => Opportunity::class,
                'event_type'        => 'reply_received',
                'description'       => "Reply received from {$inboxMessage->from_email}: \"{$inboxMessage->subject}\"",
                'happened_at'       => $inboxMessage->received_at ?? now(),
                'metadata'          => array_merge($meta, [
                    'opportunity_id' => $inboxMessage->matched_opportunity_id,
                ]),
            ]);

            // 3. Advance opportunity status from waiting_reply → replied
            Opportunity::where('id', $inboxMessage->matched_opportunity_id)
                ->where('status', 'waiting_reply')
                ->update([
                    'status'           => 'replied',
                    'last_activity_at' => now(),
                ]);

            // Also bump last_activity_at if status was something else active
            Opportunity::where('id', $inboxMessage->matched_opportunity_id)
                ->whereIn('status', ['active', 'replied', 'interview'])
                ->update(['last_activity_at' => now()]);
        }

        // 4. Create timeline event on the matched Contact
        if ($inboxMessage->matched_contact_id) {
            TimelineEvent::create([
                'user_id'           => $inboxMessage->user_id,
                'timelineable_id'   => $inboxMessage->matched_contact_id,
                'timelineable_type' => Contact::class,
                'event_type'        => 'reply_received',
                'description'       => "Reply received from {$inboxMessage->from_email}: \"{$inboxMessage->subject}\"",
                'happened_at'       => $inboxMessage->received_at ?? now(),
                'metadata'          => array_merge($meta, [
                    'contact_id' => $inboxMessage->matched_contact_id,
                ]),
            ]);

            // 5. Update last_contacted_at on the contact
            Contact::where('id', $inboxMessage->matched_contact_id)
                ->update(['last_contacted_at' => $inboxMessage->received_at ?? now()]);
        }

        // 6. Dispatch CRM notifications
        $user = User::find($inboxMessage->user_id);
        if ($user) {
            DispatchCrmNotificationJob::dispatch($user, new ReplyReceivedNotification($inboxMessage));

            if ($inboxMessage->sentiment === 'positive') {
                DispatchCrmNotificationJob::dispatch($user, new PositiveReplyNotification($inboxMessage));
            }
        }
    }
}
