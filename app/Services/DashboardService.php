<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return all dashboard statistics for the given user.
     *
     * Supported $filters keys:
     *   date_from        (string|Carbon)
     *   date_to          (string|Carbon)
     *   type             (string)  – opportunity type
     *   email_account_id (int)
     *   status           (string)  – opportunity status
     *   priority         (string)
     *
     * @param  User  $user
     * @param  array $filters
     * @return array<string, mixed>
     */
    private function q(string $model, User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->isSuperAdmin()) {
            return $model::where('user_id', $user->id);
        }
        if (! $user->tenant_id) {
            return $model::where('user_id', $user->id);
        }
        return $model::where('tenant_id', $user->tenant_id);
    }

    public function getStats(User $user, array $filters = []): array
    {
        $userId          = $user->id;
        $dateFrom        = isset($filters['date_from'])        ? Carbon::parse($filters['date_from'])        : null;
        $dateTo          = isset($filters['date_to'])          ? Carbon::parse($filters['date_to'])          : null;
        $typeFilter      = $filters['type']             ?? null;
        $emailAccountId  = $filters['email_account_id'] ?? null;
        $statusFilter    = $filters['status']           ?? null;
        $priorityFilter  = $filters['priority']         ?? null;

        // -----------------------------------------------------------------
        // Base opportunity query (respects all filters)
        // -----------------------------------------------------------------
        $oppsBase = $this->q(Opportunity::class, $user);

        if ($typeFilter)     { $oppsBase->where('type', $typeFilter); }
        if ($statusFilter)   { $oppsBase->where('status', $statusFilter); }
        if ($priorityFilter) { $oppsBase->where('priority', $priorityFilter); }
        if ($dateFrom)       { $oppsBase->where('created_at', '>=', $dateFrom); }
        if ($dateTo)         { $oppsBase->where('created_at', '<=', $dateTo); }

        // -----------------------------------------------------------------
        // Opportunity counts
        // -----------------------------------------------------------------
        $totalOpportunities  = (clone $oppsBase)->count();
        $activeOpportunities = (clone $oppsBase)
            ->whereIn('status', ['active', 'waiting_reply', 'replied', 'interview'])
            ->count();
        $waitingReply = (clone $oppsBase)->where('status', 'waiting_reply')->count();

        // -----------------------------------------------------------------
        // Follow-ups
        // -----------------------------------------------------------------
        $followUpsDueToday = $this->q(FollowUp::class, $user)
            ->where('status', 'pending')
            ->whereDate('due_at', Carbon::today())
            ->count();

        // -----------------------------------------------------------------
        // Scheduled sends today
        // -----------------------------------------------------------------
        $emailBase = $this->q(EmailMessage::class, $user);
        if ($emailAccountId) {
            $emailBase->where('email_account_id', $emailAccountId);
        }

        $scheduledToday = (clone $emailBase)
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        // -----------------------------------------------------------------
        // Replies needing review (matched inbox messages, pending review)
        // -----------------------------------------------------------------
        $repliesNeedingReview = $this->q(InboxMessage::class, $user)
            ->where('review_status', 'pending')
            ->whereNotNull('matched_outbound_id')
            ->count();

        // -----------------------------------------------------------------
        // Failed sends in last 24 hours
        // -----------------------------------------------------------------
        $failedSends = (clone $emailBase)
            ->where('status', 'failed')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        // -----------------------------------------------------------------
        // Email accounts with health info and usage percentages
        // -----------------------------------------------------------------
        $emailAccounts = $this->q(EmailAccount::class, $user)
            ->get()
            ->map(fn (EmailAccount $account) => [
                'id'                  => $account->id,
                'name'                => $account->name,
                'email'               => $account->email,
                'is_active'           => $account->is_active,
                'emails_sent_today'   => $account->emails_sent_today,
                'daily_limit'         => $account->daily_limit,
                'daily_usage_percent' => $account->daily_usage_percent,
                'last_sync_at'        => $account->last_sync_at?->toISOString(),
                'sync_status'         => $account->sync_status,
            ]);

        // -----------------------------------------------------------------
        // Recent replies (last 10 matched inbox messages)
        // -----------------------------------------------------------------
        $recentReplies = $this->q(InboxMessage::class, $user)
            ->whereNotNull('matched_outbound_id')
            ->with(['matchedContact', 'matchedOpportunity'])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn (InboxMessage $m) => [
                'id'            => $m->id,
                'from_email'    => $m->from_email,
                'from_name'     => $m->from_name,
                'subject'       => $m->subject,
                'received_at'   => $m->received_at?->toISOString(),
                'sentiment'     => $m->sentiment,
                'review_status' => $m->review_status,
                'contact'       => $m->matchedContact?->only(['id', 'first_name', 'last_name', 'email']),
                'opportunity'   => $m->matchedOpportunity?->only(['id', 'title', 'status']),
            ]);

        // -----------------------------------------------------------------
        // Positive replies count
        // -----------------------------------------------------------------
        $positiveReplies = $this->q(InboxMessage::class, $user)
            ->where('sentiment', 'positive')
            ->whereNotNull('matched_outbound_id')
            ->count();

        // -----------------------------------------------------------------
        // Stale opportunities (no activity in 14+ days, still open)
        // -----------------------------------------------------------------
        $staleOpportunities = (clone $oppsBase)
            ->whereIn('status', ['active', 'waiting_reply'])
            ->where(function ($q) {
                $threshold = Carbon::now()->subDays(14);
                $q->where('last_activity_at', '<', $threshold)
                  ->orWhereNull('last_activity_at');
            })
            ->orderBy('last_activity_at')
            ->limit(20)
            ->get()
            ->map(fn (Opportunity $o) => [
                'id'               => $o->id,
                'title'            => $o->title,
                'status'           => $o->status,
                'last_activity_at' => $o->last_activity_at?->toISOString(),
                'days_stale'       => $o->last_activity_at
                    ? (int) $o->last_activity_at->diffInDays(now())
                    : null,
            ]);

        // -----------------------------------------------------------------
        // Upcoming deadlines (next 30 days)
        // -----------------------------------------------------------------
        $upcomingDeadlines = (clone $oppsBase)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [Carbon::today(), Carbon::today()->addDays(30)])
            ->orderBy('deadline')
            ->limit(20)
            ->get()
            ->map(fn (Opportunity $o) => [
                'id'        => $o->id,
                'title'     => $o->title,
                'status'    => $o->status,
                'deadline'  => $o->deadline?->toDateString(),
                'days_left' => (int) Carbon::today()->diffInDays($o->deadline, false),
            ]);

        // -----------------------------------------------------------------
        // Outreach funnel: count by status
        // -----------------------------------------------------------------
        $outreachFunnel = $this->q(Opportunity::class, $user)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // -----------------------------------------------------------------
        // Response rate by opportunity type
        // -----------------------------------------------------------------
        $responseRateByType = $this->buildResponseRateByType($user, $filters);

        // -----------------------------------------------------------------
        // Legacy fields retained for backward compatibility
        // -----------------------------------------------------------------
        $totalContacts   = $this->q(Contact::class, $user)->count();
        $totalEmailsSent = (clone $emailBase)->where('status', 'sent')->count();
        $deadlineSoon    = (clone $oppsBase)->deadlineSoon(7)->count();
        $inboxPending    = $this->q(InboxMessage::class, $user)->where('review_status', 'pending')->count();
        $followUpsOverdue = $this->q(FollowUp::class, $user)
            ->where('status', 'pending')
            ->where('due_at', '<', Carbon::today())
            ->count();
        $emailsPerDay = $this->q(EmailMessage::class, $user)
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->whereBetween('sent_at', [now()->subDays(13)->startOfDay(), now()->endOfDay()])
            ->select(DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(sent_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
        $recentOpportunities = $this->q(Opportunity::class, $user)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return [
            // Core stats
            'total_opportunities'    => $totalOpportunities,
            'active_opportunities'   => $activeOpportunities,
            'waiting_reply'          => $waitingReply,
            'follow_ups_due_today'   => $followUpsDueToday,
            'scheduled_today'        => $scheduledToday,
            'replies_needing_review' => $repliesNeedingReview,
            'failed_sends'           => $failedSends,
            'email_accounts'         => $emailAccounts,
            'recent_replies'         => $recentReplies,
            'positive_replies'       => $positiveReplies,
            'stale_opportunities'    => $staleOpportunities,
            'upcoming_deadlines'     => $upcomingDeadlines,
            'outreach_funnel'        => $outreachFunnel,
            'response_rate_by_type'  => $responseRateByType,
            // Legacy / additional fields
            'total_contacts'         => $totalContacts,
            'total_emails_sent'      => $totalEmailsSent,
            'deadline_soon'          => $deadlineSoon,
            'inbox_pending'          => $inboxPending,
            'follow_ups_overdue'     => $followUpsOverdue,
            'emails_per_day'         => $emailsPerDay,
            'recent_opportunities'   => $recentOpportunities,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build response rate (replied / total sent) per opportunity type.
     *
     * @return array<string, array{sent: int, replied: int, rate: float}>
     */
    private function buildResponseRateByType(User $user, array $filters): array
    {
        $typeFilter   = $filters['type'] ?? null;
        $emailScope   = $user->isSuperAdmin() ? ['email_messages.user_id' => $user->id] : ['email_messages.tenant_id' => $user->tenant_id];
        $inboxScope   = $user->isSuperAdmin() ? ['inbox_messages.user_id' => $user->id]  : ['inbox_messages.tenant_id'  => $user->tenant_id];

        $sentQuery = EmailMessage::query()
            ->select('opportunities.type', DB::raw('count(email_messages.id) as sent_count'))
            ->join('opportunities', 'opportunities.id', '=', 'email_messages.opportunity_id')
            ->where($emailScope)
            ->where('email_messages.status', 'sent')
            ->where('email_messages.direction', 'outbound')
            ->whereNotNull('email_messages.opportunity_id')
            ->groupBy('opportunities.type');

        if ($typeFilter) {
            $sentQuery->where('opportunities.type', $typeFilter);
        }

        $sentByType = $sentQuery->pluck('sent_count', 'opportunities.type')->toArray();

        $repliedQuery = InboxMessage::query()
            ->select('opportunities.type', DB::raw('count(inbox_messages.id) as replied_count'))
            ->join('opportunities', 'opportunities.id', '=', 'inbox_messages.matched_opportunity_id')
            ->where($inboxScope)
            ->whereNotNull('inbox_messages.matched_outbound_id')
            ->groupBy('opportunities.type');

        if ($typeFilter) {
            $repliedQuery->where('opportunities.type', $typeFilter);
        }

        $repliedByType = $repliedQuery->pluck('replied_count', 'opportunities.type')->toArray();

        $result   = [];
        $allTypes = array_unique(array_merge(array_keys($sentByType), array_keys($repliedByType)));

        foreach ($allTypes as $type) {
            $sent    = (int) ($sentByType[$type] ?? 0);
            $replied = (int) ($repliedByType[$type] ?? 0);
            $rate    = $sent > 0 ? round(($replied / $sent) * 100, 1) : 0.0;

            $result[$type] = [
                'sent'    => $sent,
                'replied' => $replied,
                'rate'    => $rate,
            ];
        }

        return $result;
    }
}
