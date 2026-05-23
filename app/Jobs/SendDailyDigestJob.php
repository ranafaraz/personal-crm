<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\User;
use App\Notifications\DailySummaryNotification;
use App\Services\CrmNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly ?int $userId = null) {}

    public function handle(CrmNotificationService $service): void
    {
        $query = User::query();

        if ($this->userId) {
            $query->where('id', $this->userId);
        }

        $today = Carbon::today();

        $query->chunk(100, function ($users) use ($service, $today) {
            foreach ($users as $user) {
                $summary = $this->buildSummary($user, $today);
                $notification = new DailySummaryNotification($summary);
                $service->send($user, $notification);
            }
        });
    }

    private function buildSummary(User $user, Carbon $today): array
    {
        $emailsSent = EmailMessage::where('user_id', $user->id)
            ->where('status', 'sent')
            ->whereDate('sent_at', $today)
            ->count();

        $repliesReceived = InboxMessage::where('user_id', $user->id)
            ->whereNotNull('matched_outbound_id')
            ->whereDate('received_at', $today)
            ->count();

        $positiveReplies = InboxMessage::where('user_id', $user->id)
            ->where('sentiment', 'positive')
            ->whereNotNull('matched_outbound_id')
            ->whereDate('received_at', $today)
            ->count();

        $followUpsDue = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('due_at', $today)
            ->count();

        $failedSends = EmailMessage::where('user_id', $user->id)
            ->where('status', 'failed')
            ->whereDate('failed_at', $today)
            ->count();

        return [
            'emails_sent'      => $emailsSent,
            'replies_received' => $repliesReceived,
            'positive_replies' => $positiveReplies,
            'follow_ups_due'   => $followUpsDue,
            'failed_sends'     => $failedSends,
            'date'             => $today->toDateString(),
        ];
    }
}
