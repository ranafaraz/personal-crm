<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialAnalyticsSnapshot;
use App\Models\SocialPost;
use App\Services\Social\LinkedInAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightsController extends Controller
{
    public function __construct(private LinkedInAnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $accounts = SocialAccount::with('provider')
            ->where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->get();

        $accountSummaries = $accounts->map(function (SocialAccount $account) use ($user) {
            $followerCount = $this->analytics->latestFollowerCount($account);

            $followerHistory = SocialAnalyticsSnapshot::where('social_account_id', $account->id)
                ->where('analytics_scope', 'follower')
                ->where('metric_name', 'followerCount')
                ->orderBy('collected_at')
                ->limit(14)
                ->pluck('metric_value', 'collected_at')
                ->toArray();

            $recentPosts = SocialPost::where('user_id', $user->id)
                ->where('status', 'published')
                ->whereNotNull('linkedin_post_urn')
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get()
                ->map(fn ($post) => [
                    'post'     => $post,
                    'metrics'  => $this->analytics->latestPostMetrics($post),
                ]);

            $aggregateMetrics = SocialAnalyticsSnapshot::where('social_account_id', $account->id)
                ->where('analytics_scope', 'aggregate')
                ->orderByDesc('collected_at')
                ->get()
                ->unique('metric_name')
                ->pluck('metric_value', 'metric_name')
                ->toArray();

            return [
                'account'          => $account,
                'follower_count'   => $followerCount,
                'follower_history' => $followerHistory,
                'recent_posts'     => $recentPosts,
                'aggregate'        => $aggregateMetrics,
            ];
        });

        $hasData = $accountSummaries->isNotEmpty();

        return view('social-studio.insights', compact('accountSummaries', 'hasData'));
    }
}
