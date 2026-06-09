<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialAnalyticsSnapshot;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\LinkedInAnalyticsService;
use Illuminate\Http\RedirectResponse;
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
            ->where('status', 'connected')
            ->orderBy('provider_id')
            ->orderBy('display_name')
            ->get();

        $accountSummaries = $accounts->map(function (SocialAccount $account) use ($user) {
            $isLinkedIn = $account->provider?->key === 'linkedin';
            $publishedTargets = SocialPostTarget::where('social_account_id', $account->id)
                ->whereHas('post', fn ($q) => $q->where('user_id', $user->id));

            $recentTargets = (clone $publishedTargets)
                ->with('post')
                ->where('status', 'published')
                ->latest('published_at')
                ->limit(8)
                ->get();

            $followerCount = $isLinkedIn ? $this->analytics->latestFollowerCount($account) : null;

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
                'provider_key'     => $account->provider?->key,
                'follower_count'   => $followerCount,
                'follower_history' => $followerHistory,
                'recent_posts'     => $isLinkedIn ? $recentPosts : collect(),
                'recent_targets'   => $recentTargets,
                'aggregate'        => $aggregateMetrics,
                'published_count'  => (clone $publishedTargets)->where('status', 'published')->count(),
                'scheduled_count'  => (clone $publishedTargets)->where('status', 'scheduled')->count(),
                'failed_count'     => (clone $publishedTargets)->where('status', 'failed')->count(),
            ];
        });

        $providerTotals = $accountSummaries
            ->groupBy('provider_key')
            ->map(fn ($summaries, $key) => [
                'provider_key' => $key,
                'provider_name' => $summaries->first()['account']->provider?->name ?? ucfirst((string) $key),
                'accounts' => $summaries->count(),
                'published' => $summaries->sum('published_count'),
                'scheduled' => $summaries->sum('scheduled_count'),
                'failed' => $summaries->sum('failed_count'),
            ])
            ->values();

        $hasData = $accounts->isNotEmpty();

        return view('social-studio.insights', compact('accountSummaries', 'providerTotals', 'hasData'));
    }

    public function syncNow(Request $request): RedirectResponse
    {
        $user = $request->user();

        $accounts = SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return back()->with('info', 'No connected LinkedIn accounts to sync. WordPress publishing activity is shown from CRM publish records.');
        }

        $snapshotCount = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $token = $account->access_token_encrypted;

            try {
                $snapshotCount += $this->analytics->syncAggregateAnalytics($token, $account);
                $snapshotCount += $this->analytics->syncFollowerAnalytics($token, $account);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = "{$account->display_name}: {$e->getMessage()}";
            }

            $posts = SocialPost::where('user_id', $user->id)
                ->where('status', 'published')
                ->whereNotNull('linkedin_post_urn')
                ->get();

            foreach ($posts as $post) {
                try {
                    $snapshotCount += $this->analytics->syncPostAnalytics($token, $account, $post);
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = "{$account->display_name} / {$post->title_internal}: {$e->getMessage()}";
                }
            }
        }

        if ($errors) {
            return back()->with('error', 'LinkedIn sync completed with errors: ' . implode(' | ', array_slice($errors, 0, 3)));
        }

        if ($snapshotCount === 0) {
            return back()->with('info', 'LinkedIn returned no analytics snapshots. Reconnect with r_member_postAnalytics and r_member_profileAnalytics if those scopes are missing.');
        }

        return back()->with('success', "LinkedIn analytics synced. {$snapshotCount} metric snapshot(s) stored.");
    }
}
