<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Services\CrmNotificationService;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private CrmNotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->only([
            'date_from',
            'date_to',
            'type',
            'email_account_id',
            'status',
            'priority',
        ]);

        $stats = $this->dashboardService->getStats($user, $filters);

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Reply inbox data for the Inbox Summary widget
        $recentReplies = InboxMessage::where('user_id', $user->id)
            ->whereNotNull('matched_outbound_id')
            ->with(['matchedContact', 'matchedOpportunity'])
            ->orderByDesc('received_at')
            ->limit(5)
            ->get();

        // Upcoming follow-ups for the Follow-up Radar widget
        $upcomingFollowUps = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('due_at', '<=', Carbon::today()->addDays(7))
            ->with('opportunity')
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn ($fu) => [
                'id'                => $fu->id,
                'due_at'            => $fu->due_at?->toISOString(),
                'opportunity_title' => $fu->opportunity?->title ?? 'Follow-up',
                'status'            => $fu->status,
            ]);

        // Upcoming deadlines for deadline widget
        $upcomingDeadlines = $this->dashboardService->getUpcomingDeadlines($user);

        // Funnel data for pipeline widget
        $funnelData = $stats['outreach_funnel'] ?? [];

        // Notifications
        $unreadNotifications  = $this->notificationService->getUnreadCount($user);
        $recentNotifications  = $this->notificationService->getRecentUnread($user, 5);

        // Sentiment breakdown for Inbox Summary
        $sentimentBreakdown = InboxMessage::where('user_id', $user->id)
            ->whereNotNull('matched_outbound_id')
            ->selectRaw("sentiment, COUNT(*) as total")
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment')
            ->toArray();

        $stats['sentiment_breakdown'] = [
            'positive' => $sentimentBreakdown['positive'] ?? 0,
            'neutral'  => $sentimentBreakdown['neutral']  ?? 0,
            'negative' => $sentimentBreakdown['negative'] ?? 0,
        ];

        return view('dashboard.index', compact(
            'stats',
            'filters',
            'emailAccounts',
            'recentReplies',
            'upcomingFollowUps',
            'upcomingDeadlines',
            'funnelData',
            'unreadNotifications',
            'recentNotifications',
        ));
    }
}
