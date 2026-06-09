<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialAnalyticsSnapshot;
use App\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetches LinkedIn analytics and persists them as SocialAnalyticsSnapshot records.
 */
class LinkedInAnalyticsService
{
    public function __construct(private LinkedInClient $client) {}

    // ── Post-level analytics ──────────────────────────────────────────────────

    /**
     * Sync analytics for a single published post.
     * Creates a snapshot per metric found in the response.
     * Returns number of snapshots created.
     */
    public function syncPostAnalytics(string $token, SocialAccount $account, SocialPost $post): int
    {
        if (! $post->linkedin_post_urn) {
            return 0;
        }

        try {
            $elements = $this->client->getPostStatistics($token, $post->linkedin_post_urn);
        } catch (LinkedInPermissionException $e) {
            Log::warning('LinkedIn post analytics permission denied', [
                'account_id' => $account->id,
                'post_id'    => $post->id,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }

        $count = 0;
        $collectedAt = now();

        foreach ($elements as $element) {
            $metrics = $this->extractPostMetrics($element);
            foreach ($metrics as $name => $value) {
                SocialAnalyticsSnapshot::create([
                    'social_account_id'   => $account->id,
                    'social_post_id'      => $post->id,
                    'analytics_scope'     => 'post',
                    'metric_name'         => $name,
                    'metric_value'        => (int) $value,
                    'aggregation'         => 'total',
                    'date_range_start'    => $collectedAt->toDateString(),
                    'date_range_end'      => $collectedAt->toDateString(),
                    'collected_at'        => $collectedAt,
                    'raw_provider_response' => $element,
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ── Aggregate (all posts) analytics ──────────────────────────────────────

    /**
     * Sync aggregate member post statistics (all posts combined).
     */
    public function syncAggregateAnalytics(
        string $token,
        SocialAccount $account,
        array $params = []
    ): int {
        try {
            $elements = $this->client->getAggregatePostStatistics($token, $params);
        } catch (LinkedInPermissionException $e) {
            Log::warning('LinkedIn aggregate analytics permission denied', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }

        $count = 0;
        $collectedAt = now();

        foreach ($elements as $element) {
            $metrics = $this->extractPostMetrics($element);
            foreach ($metrics as $name => $value) {
                SocialAnalyticsSnapshot::create([
                    'social_account_id'     => $account->id,
                    'social_post_id'        => null,
                    'analytics_scope'       => 'aggregate',
                    'metric_name'           => $name,
                    'metric_value'          => (int) $value,
                    'aggregation'           => 'total',
                    'date_range_start'      => $collectedAt->toDateString(),
                    'date_range_end'        => $collectedAt->toDateString(),
                    'collected_at'          => $collectedAt,
                    'raw_provider_response' => $element,
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ── Follower analytics ────────────────────────────────────────────────────

    /**
     * Sync follower statistics for the account.
     */
    public function syncFollowerAnalytics(
        string $token,
        SocialAccount $account,
        array $params = []
    ): int {
        try {
            $elements = $this->client->getFollowerStatistics($token, $params);
        } catch (LinkedInPermissionException $e) {
            Log::warning('LinkedIn follower analytics permission denied', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }

        $count = 0;
        $collectedAt = now();

        foreach ($elements as $element) {
            $metrics = $this->extractFollowerMetrics($element);
            foreach ($metrics as $name => $value) {
                SocialAnalyticsSnapshot::create([
                    'social_account_id'     => $account->id,
                    'social_post_id'        => null,
                    'analytics_scope'       => 'follower',
                    'metric_name'           => $name,
                    'metric_value'          => (int) $value,
                    'aggregation'           => 'total',
                    'date_range_start'      => $collectedAt->toDateString(),
                    'date_range_end'        => $collectedAt->toDateString(),
                    'collected_at'          => $collectedAt,
                    'raw_provider_response' => $element,
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ── Latest snapshots for display ─────────────────────────────────────────

    /**
     * Get the most recent snapshot for each metric for a given post.
     * Returns ['impressionCount' => 123, 'likeCount' => 45, ...]
     */
    public function latestPostMetrics(SocialPost $post): array
    {
        return SocialAnalyticsSnapshot::where('social_post_id', $post->id)
            ->where('analytics_scope', 'post')
            ->orderByDesc('collected_at')
            ->get()
            ->unique('metric_name')
            ->pluck('metric_value', 'metric_name')
            ->all();
    }

    /**
     * Get the most recent follower count for an account.
     */
    public function latestFollowerCount(SocialAccount $account): ?int
    {
        return SocialAnalyticsSnapshot::where('social_account_id', $account->id)
            ->where('analytics_scope', 'follower')
            ->where('metric_name', 'followerCount')
            ->orderByDesc('collected_at')
            ->value('metric_value');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function extractPostMetrics(array $element): array
    {
        if (array_key_exists('count', $element) && array_key_exists('metricType', $element)) {
            $metricType = $this->normaliseMetricType($element['metricType']);
            $name = $this->mapLinkedInMetricName($metricType);

            return $name ? [$name => $element['count']] : [];
        }

        $totalEngagement = $element['totalShareStatistics'] ?? $element['totalEngagement'] ?? [];

        $metrics = [
            'impressionCount'    => $totalEngagement['impressionCount']    ?? null,
            'uniqueImpressionsCount' => $totalEngagement['uniqueImpressionsCount'] ?? null,
            'clickCount'         => $totalEngagement['clickCount']         ?? null,
            'likeCount'          => $totalEngagement['likeCount']          ?? null,
            'commentCount'       => $totalEngagement['commentCount']       ?? null,
            'shareCount'         => $totalEngagement['shareCount']         ?? null,
            'engagementRate'     => null,
        ];

        if (
            isset($totalEngagement['impressionCount']) &&
            $totalEngagement['impressionCount'] > 0
        ) {
            $totalInteractions = ($totalEngagement['clickCount'] ?? 0)
                + ($totalEngagement['likeCount'] ?? 0)
                + ($totalEngagement['commentCount'] ?? 0)
                + ($totalEngagement['shareCount'] ?? 0);
            $metrics['engagementRate'] = (int) round(
                ($totalInteractions / $totalEngagement['impressionCount']) * 10000
            ); // stored as basis points (0.01%)
        }

        return array_filter($metrics, fn ($v) => $v !== null);
    }

    private function extractFollowerMetrics(array $element): array
    {
        return array_filter([
            'followerCount'     => $element['memberFollowersCount'] ?? $element['followerCounts']['organicFollowerCount'] ?? null,
            'paidFollowerCount' => $element['followerCounts']['paidFollowerCount'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function normaliseMetricType(mixed $metricType): ?string
    {
        if (is_string($metricType)) {
            return $metricType;
        }

        if (is_array($metricType)) {
            return collect($metricType)->first();
        }

        return null;
    }

    private function mapLinkedInMetricName(?string $metricType): ?string
    {
        return match ($metricType) {
            'IMPRESSION' => 'impressionCount',
            'MEMBERS_REACHED' => 'uniqueImpressionsCount',
            'LINK_CLICKS' => 'clickCount',
            'REACTION' => 'likeCount',
            'COMMENT' => 'commentCount',
            'RESHARE' => 'shareCount',
            'POST_SAVE' => 'saveCount',
            'POST_SEND' => 'sendCount',
            'FOLLOWER_GAINED_FROM_CONTENT' => 'followerGainedCount',
            'PROFILE_VIEW_FROM_CONTENT' => 'profileViewCount',
            default => null,
        };
    }
}
