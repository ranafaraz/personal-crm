<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialPublishJob;
use Illuminate\Support\Facades\Log;

class LinkedInPublishService
{
    public function __construct(
        private LinkedInPostsService $posts,
        private LinkedInMediaService $media,
    ) {}

    /**
     * Attempt to publish a target post via LinkedIn.
     * Returns the updated SocialPostTarget.
     */
    public function publish(SocialPostTarget $target): SocialPostTarget
    {
        $post    = $target->post;
        $account = $target->account;

        $this->guardPublish($target, $post, $account);

        $job = SocialPublishJob::create([
            'social_post_target_id' => $target->id,
            'scheduled_at'          => now(),
            'job_status'            => 'processing',
            'attempt_count'         => $target->publishJobs()->count() + 1,
            'max_attempts'          => 3,
        ]);

        $target->update(['status' => 'publishing']);
        $post->update(['status' => 'publishing']);

        try {
            $token     = $account->access_token_encrypted;
            $authorUrn = $account->provider_account_urn;

            // For image posts: ensure all approved assets are uploaded to LinkedIn first
            if ($post->post_type === 'image') {
                $this->media->uploadMissingAssetsForPost($token, $authorUrn, $post);
            }

            $postUrn = $this->posts->publish($token, $post, $authorUrn);

            $postUrl = $this->buildPostUrl($postUrn);

            $target->update([
                'status'         => 'published',
                'remote_post_id' => $postUrn,
                'published_at'   => now(),
                'error_code'     => null,
                'error_message'  => null,
            ]);

            $post->update([
                'status'            => 'published',
                'linkedin_post_urn' => $postUrn,
                'linkedin_post_url' => $postUrl,
                'linkedin_response_metadata' => ['published_at' => now()->toIso8601String()],
            ]);

            $job->update([
                'job_status'                       => 'succeeded',
                'provider_response_sanitized_json' => ['post_urn' => $postUrn],
            ]);

            SocialActivityLog::record(
                $post->user_id, $post->tenant_id,
                'linkedin_published',
                SocialPost::class, $post->id,
                "Published to LinkedIn. URN: {$postUrn}"
            );

        } catch (\Throwable $e) {
            $sanitizedMsg = $this->sanitizeError($e->getMessage());
            $isPermanent  = $e instanceof LinkedInPermanentException;

            $target->update([
                'status'        => 'failed',
                'error_code'    => (string) $e->getCode(),
                'error_message' => $sanitizedMsg,
            ]);
            $post->update(['status' => 'failed']);

            $nextRetry = (! $isPermanent && $job->attempt_count < $job->max_attempts)
                ? now()->addMinutes((int) pow(2, $job->attempt_count) * 5)
                : null;

            $job->update([
                'job_status'    => $isPermanent ? 'failed' : 'retrying',
                'next_retry_at' => $nextRetry,
                'provider_response_sanitized_json' => ['error' => $sanitizedMsg],
            ]);

            if ($e instanceof LinkedInAuthException) {
                $account->update(['status' => 'reauthorization_required']);
                SocialActivityLog::record(
                    $post->user_id, $post->tenant_id,
                    'linkedin_auth_failed',
                    SocialAccount::class, $account->id,
                    'LinkedIn token expired/revoked during publish'
                );
            }

            SocialActivityLog::record(
                $post->user_id, $post->tenant_id,
                'linkedin_publish_failed',
                SocialPost::class, $post->id,
                "Publish failed: {$sanitizedMsg}"
            );

            Log::error('Social publish failed', [
                'target_id' => $target->id,
                'post_id'   => $post->id,
                'error'     => $sanitizedMsg,
            ]);
        }

        return $target->fresh();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildPostUrl(string $postUrn): ?string
    {
        // URN format: urn:li:share:7XXXXXXXXXXXXXXXXXXX
        if (preg_match('/urn:li:share:(\d+)/', $postUrn, $m)) {
            return "https://www.linkedin.com/feed/update/urn:li:share:{$m[1]}/";
        }
        if (preg_match('/urn:li:ugcPost:(\d+)/', $postUrn, $m)) {
            return "https://www.linkedin.com/feed/update/urn:li:ugcPost:{$m[1]}/";
        }
        return null;
    }

    private function guardPublish(SocialPostTarget $target, SocialPost $post, SocialAccount $account): void
    {
        if ($target->isPublished()) {
            throw new LinkedInPermanentException('Post has already been published (idempotency guard).');
        }
        if (! $account->isConnected()) {
            throw new LinkedInPermanentException('LinkedIn account is not connected.');
        }
        if ($account->isTokenExpired()) {
            throw new LinkedInAuthException('LinkedIn access token has expired.');
        }
        if (empty($account->provider_account_urn)) {
            throw new LinkedInPermanentException('LinkedIn Person URN not resolved.');
        }
        if ($post->approval_status !== 'approved') {
            throw new LinkedInPermanentException('Post has not been approved.');
        }
    }

    private function sanitizeError(string $message): string
    {
        return preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $message) ?? $message;
    }
}
