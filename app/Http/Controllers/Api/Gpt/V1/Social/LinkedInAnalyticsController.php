<?php

namespace App\Http\Controllers\Api\Gpt\V1\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Jobs\SyncLinkedInAnalyticsJob;
use App\Models\SocialAccount;
use App\Models\SocialAnalyticsSnapshot;
use App\Models\SocialPost;
use App\Services\Social\LinkedInAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkedInAnalyticsController extends GptController
{
    public function __construct(private LinkedInAnalyticsService $analytics) {}

    /** Get stored analytics snapshots for a specific post. */
    public function postMetrics(Request $request, int $postId): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = SocialPost::where('id', $postId)->where('user_id', $user->id)->first();

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $metrics = $this->analytics->latestPostMetrics($post);

        return response()->json([
            'post_id'    => $postId,
            'post_urn'   => $post->linkedin_post_urn,
            'metrics'    => $metrics,
            'collected_at' => SocialAnalyticsSnapshot::where('social_post_id', $postId)
                ->orderByDesc('collected_at')
                ->value('collected_at'),
        ]);
    }

    /** Get aggregate post statistics (all posts) for an account. */
    public function aggregateMetrics(Request $request, int $accountId): JsonResponse
    {
        $user    = $this->apiUser($request);
        $account = $this->findAccount($user->id, $accountId);

        if (! $account) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $snapshots = SocialAnalyticsSnapshot::where('social_account_id', $accountId)
            ->where('analytics_scope', 'aggregate')
            ->orderByDesc('collected_at')
            ->get()
            ->unique('metric_name')
            ->pluck('metric_value', 'metric_name');

        return response()->json([
            'account_id' => $accountId,
            'metrics'    => $snapshots,
        ]);
    }

    /** Get follower statistics for an account. */
    public function followerMetrics(Request $request, int $accountId): JsonResponse
    {
        $user    = $this->apiUser($request);
        $account = $this->findAccount($user->id, $accountId);

        if (! $account) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $followerCount = $this->analytics->latestFollowerCount($account);

        $history = SocialAnalyticsSnapshot::where('social_account_id', $accountId)
            ->where('analytics_scope', 'follower')
            ->where('metric_name', 'followerCount')
            ->orderByDesc('collected_at')
            ->limit(30)
            ->get(['metric_value', 'collected_at'])
            ->map(fn ($s) => [
                'value'        => $s->metric_value,
                'collected_at' => $s->collected_at->toIso8601String(),
            ]);

        return response()->json([
            'account_id'     => $accountId,
            'follower_count' => $followerCount,
            'history'        => $history,
        ]);
    }

    /**
     * Insights dashboard summary: recent posts with metrics + follower count.
     */
    public function insightsDashboard(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $accounts = SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->get();

        $summary = $accounts->map(function (SocialAccount $account) use ($user) {
            $recentPosts = SocialPost::where('user_id', $user->id)
                ->where('status', 'published')
                ->whereNotNull('linkedin_post_urn')
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get()
                ->map(fn ($post) => [
                    'post_id'     => $post->id,
                    'title'       => $post->title_internal,
                    'post_url'    => $post->linkedin_post_url,
                    'published_at' => $post->updated_at->toIso8601String(),
                    'metrics'     => $this->analytics->latestPostMetrics($post),
                ]);

            return [
                'account_id'     => $account->id,
                'display_name'   => $account->display_name,
                'follower_count' => $this->analytics->latestFollowerCount($account),
                'recent_posts'   => $recentPosts,
            ];
        });

        return response()->json(['accounts' => $summary]);
    }

    /**
     * Manually trigger an analytics sync for an account (queues a job).
     */
    public function syncNow(Request $request, int $accountId): JsonResponse
    {
        $user    = $this->apiUser($request);
        $account = $this->findAccount($user->id, $accountId);

        if (! $account) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        SyncLinkedInAnalyticsJob::dispatch($accountId);

        $this->audit($request, 'linkedin_analytics_sync_triggered', SocialAccount::class, $accountId, 'low');

        return response()->json(['message' => 'Analytics sync queued.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findAccount(int $userId, int $id): ?SocialAccount
    {
        return SocialAccount::where('id', $id)
            ->where('user_id', $userId)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->first();
    }
}
