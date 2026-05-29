<?php

namespace App\Services\Social;

use App\Models\SocialActivityLog;
use App\Models\SocialAccount;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialPublishJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LinkedInPublishService
{
    private const API_BASE    = 'https://api.linkedin.com/v2';
    private const RESTLI_HDR  = ['X-Restli-Protocol-Version' => '2.0.0'];
    private const PERMANENT_ERROR_CODES = [400, 401, 403, 422];

    /**
     * Attempt to publish a target post via LinkedIn.
     * Returns the updated SocialPostTarget.
     */
    public function publish(SocialPostTarget $target): SocialPostTarget
    {
        $post    = $target->post;
        $account = $target->account;

        // Safety checks
        $this->guardPublish($target, $post, $account);

        // Increment attempt count via a new job record
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
            $accessToken = $account->access_token_encrypted;
            $authorUrn   = $account->provider_account_urn;

            $remoteId = match ($post->post_type) {
                'text'         => $this->publishText($accessToken, $authorUrn, $post),
                'article_link' => $this->publishArticle($accessToken, $authorUrn, $post),
                'image'        => $this->publishImage($accessToken, $authorUrn, $post),
                default        => throw new \RuntimeException("Unsupported post_type: {$post->post_type}"),
            };

            $target->update([
                'status'        => 'published',
                'remote_post_id' => $remoteId,
                'published_at'  => now(),
                'error_code'    => null,
                'error_message' => null,
            ]);
            $post->update(['status' => 'published']);

            $job->update([
                'job_status'                       => 'succeeded',
                'provider_response_sanitized_json' => ['remote_post_id' => $remoteId],
            ]);

            SocialActivityLog::record(
                $post->user_id, $post->tenant_id,
                'linkedin_published',
                SocialPost::class, $post->id,
                "Published to LinkedIn. Remote ID: {$remoteId}"
            );

        } catch (\Throwable $e) {
            $sanitizedMsg = $this->sanitizeError($e->getMessage());
            $errorCode    = method_exists($e, 'getCode') ? (string) $e->getCode() : null;
            $isPermanent  = $e instanceof LinkedInPermanentException;

            $target->update([
                'status'        => 'failed',
                'error_code'    => $errorCode,
                'error_message' => $sanitizedMsg,
            ]);

            // Only reset parent post to 'failed'; do not re-schedule automatically
            $post->update(['status' => 'failed']);

            $nextRetry = (! $isPermanent && $job->attempt_count < $job->max_attempts)
                ? now()->addMinutes((int) pow(2, $job->attempt_count) * 5)
                : null;

            $job->update([
                'job_status'    => $isPermanent ? 'failed' : 'retrying',
                'next_retry_at' => $nextRetry,
                'provider_response_sanitized_json' => ['error' => $sanitizedMsg],
            ]);

            // Mark account as needing reauth on auth failures
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

    // ── Text post ─────────────────────────────────────────────────────────────

    private function publishText(string $token, string $authorUrn, SocialPost $post): string
    {
        $visibility = $post->targets->first()?->platform_metadata_json['visibility'] ?? 'PUBLIC';

        $payload = [
            'author'          => $authorUrn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'   => ['text' => $this->buildBody($post)],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        return $this->postUgc($token, $payload);
    }

    // ── Article / link share ──────────────────────────────────────────────────

    private function publishArticle(string $token, string $authorUrn, SocialPost $post): string
    {
        if (empty($post->article_url)) {
            throw new LinkedInPermanentException('Article URL is required for article_link post type.');
        }

        $visibility = $post->targets->first()?->platform_metadata_json['visibility'] ?? 'PUBLIC';

        $payload = [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $this->buildBody($post)],
                    'shareMediaCategory' => 'ARTICLE',
                    'media'              => [[
                        'status'      => 'READY',
                        'originalUrl' => $post->article_url,
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        return $this->postUgc($token, $payload);
    }

    // ── Image post (register upload → upload → post) ──────────────────────────

    private function publishImage(string $token, string $authorUrn, SocialPost $post): string
    {
        $asset = $post->mediaAssets()
            ->wherePivot('is_featured', true)
            ->where('approval_status', 'approved')
            ->first();

        if (! $asset) {
            $asset = $post->mediaAssets()
                ->where('approval_status', 'approved')
                ->first();
        }

        if (! $asset) {
            throw new LinkedInPermanentException('No approved media asset found for image post.');
        }

        if (empty($asset->alt_text)) {
            throw new LinkedInPermanentException('Image post requires alt text on the media asset.');
        }

        $assetUrn = $this->uploadImageToLinkedIn($token, $authorUrn, $asset);

        $visibility = $post->targets->first()?->platform_metadata_json['visibility'] ?? 'PUBLIC';

        $payload = [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $this->buildBody($post)],
                    'shareMediaCategory' => 'IMAGE',
                    'media'              => [[
                        'status'      => 'READY',
                        'description' => ['text' => $asset->alt_text],
                        'media'       => $assetUrn,
                        'title'       => ['text' => $post->title_internal],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        return $this->postUgc($token, $payload);
    }

    private function uploadImageToLinkedIn(string $token, string $authorUrn, SocialMediaAsset $asset): string
    {
        // Step 1: Register upload
        $registerResp = Http::withToken($token)
            ->withHeaders(self::RESTLI_HDR)
            ->post(self::API_BASE . '/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes'                  => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner'                    => $authorUrn,
                    'serviceRelationships'     => [[
                        'relationshipType' => 'OWNER',
                        'identifier'       => 'urn:li:userGeneratedContent',
                    ]],
                ],
            ]);

        $this->assertSuccessful($registerResp, 'LinkedIn asset register failed');

        $uploadUrl = $registerResp->json('value.uploadMechanism.com\\.linkedin\\.digitalmedia\\.uploading\\.MediaUploadHttpRequest.uploadUrl')
            ?? data_get($registerResp->json(), 'value.uploadMechanism.*.uploadUrl');

        $liAssetUrn = $registerResp->json('value.asset');

        if (! $uploadUrl || ! $liAssetUrn) {
            throw new \RuntimeException('LinkedIn register upload response missing uploadUrl or asset URN');
        }

        // Step 2: Upload image binary
        $filePath = Storage::disk('public')->path($asset->storage_path);
        $fileContents = file_get_contents($filePath);

        if ($fileContents === false) {
            throw new LinkedInPermanentException("Cannot read media asset file: {$asset->storage_path}");
        }

        $uploadResp = Http::withToken($token)
            ->withHeaders(['Content-Type' => $asset->mime_type])
            ->withBody($fileContents, $asset->mime_type)
            ->put($uploadUrl);

        if (! in_array($uploadResp->status(), [200, 201])) {
            throw new \RuntimeException('LinkedIn image upload failed with status ' . $uploadResp->status());
        }

        return $liAssetUrn;
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private function postUgc(string $token, array $payload): string
    {
        $response = Http::withToken($token)
            ->withHeaders(self::RESTLI_HDR)
            ->post(self::API_BASE . '/ugcPosts', $payload);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new LinkedInAuthException('LinkedIn auth rejected: ' . $response->status());
        }

        $this->assertSuccessful($response, 'LinkedIn UGC post creation failed');

        $remoteId = $response->header('x-restli-id')
            ?: $response->header('X-RestLi-Id')
            ?: $response->json('id')
            ?: 'unknown';

        return $remoteId;
    }

    private function assertSuccessful(\Illuminate\Http\Client\Response $response, string $context): void
    {
        if (! $response->successful()) {
            $status = $response->status();
            $body   = $this->sanitizeError($response->body());

            if (in_array($status, self::PERMANENT_ERROR_CODES)) {
                throw new LinkedInPermanentException("{$context} [{$status}]: {$body}");
            }

            throw new \RuntimeException("{$context} [{$status}]: {$body}");
        }
    }

    private function buildBody(SocialPost $post): string
    {
        $body = $post->post_body;
        $tags = $post->hashtagString();
        return $tags ? "{$body}\n\n{$tags}" : $body;
    }

    private function sanitizeError(string $message): string
    {
        // Remove any bearer token values that may appear in error details
        return preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $message) ?? $message;
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
}

class LinkedInAuthException extends \RuntimeException {}
class LinkedInPermanentException extends \RuntimeException {}
