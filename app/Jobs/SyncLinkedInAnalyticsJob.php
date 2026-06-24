<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Social\LinkedInAnalyticsService;
use App\Services\Social\LinkedInPermissionException;
use App\Services\Social\LinkedInPermanentException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLinkedInAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    /**
     * @param  int|null  $postId  Null to sync aggregate + follower only
     */
    public function __construct(
        private readonly int $accountId,
        private readonly ?int $postId = null,
    ) {}

    public function handle(LinkedInAnalyticsService $service): void
    {
        $account = SocialAccount::find($this->accountId);

        if (! $account || ! $account->isConnected()) {
            return;
        }

        $token = $account->access_token_encrypted;

        if ($this->postId) {
            $post = SocialPost::find($this->postId);
            if ($post && $post->linkedin_post_urn) {
                try {
                    $service->syncPostAnalytics($token, $account, $post);
                } catch (LinkedInPermissionException | LinkedInPermanentException $e) {
                    Log::info('SyncLinkedInAnalyticsJob: post analytics unavailable', [
                        'account_id' => $this->accountId,
                        'post_id'    => $this->postId,
                        'reason'     => $e->getMessage(),
                    ]);
                }
            }
            return;
        }

        // Account-level sync: aggregate post stats + follower stats
        try {
            $service->syncAggregateAnalytics($token, $account);
        } catch (LinkedInPermissionException | LinkedInPermanentException $e) {
            Log::info('SyncLinkedInAnalyticsJob: aggregate analytics unavailable', [
                'account_id' => $this->accountId,
                'reason'     => $e->getMessage(),
            ]);
        }

        try {
            $service->syncFollowerAnalytics($token, $account);
        } catch (LinkedInPermissionException | LinkedInPermanentException $e) {
            Log::info('SyncLinkedInAnalyticsJob: follower analytics unavailable', [
                'account_id' => $this->accountId,
                'reason'     => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncLinkedInAnalyticsJob failed', [
            'account_id' => $this->accountId,
            'post_id'    => $this->postId,
            'error'      => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        $tags = ["account:{$this->accountId}", 'linkedin-analytics'];
        if ($this->postId) {
            $tags[] = "post:{$this->postId}";
        }
        return $tags;
    }
}
