<?php

namespace App\Jobs;

use App\Models\SocialAuditEvent;
use App\Models\SocialPostTarget;
use App\Services\Social\LinkedInAuthException;
use App\Services\Social\LinkedInPermanentException;
use App\Services\Social\LinkedInPublishService;
use App\Services\Social\LinkedInRateLimitException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishLinkedInPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 300; // 5 minutes between retries

    public function __construct(
        private readonly int $targetId,
        private readonly int $userId,
    ) {}

    public function handle(LinkedInPublishService $service): void
    {
        $target = SocialPostTarget::with(['post', 'account'])->find($this->targetId);

        if (! $target) {
            Log::warning('PublishLinkedInPostJob: target not found', ['target_id' => $this->targetId]);
            return;
        }

        if ($target->isPublished()) {
            return;
        }

        SocialAuditEvent::log(
            $this->userId,
            'publish_job_started',
            'processing',
            $target->social_account_id,
            $target->social_post_id,
        );

        $target = $service->publish($target);

        $status = $target->status === 'published' ? 'success' : 'failed';

        SocialAuditEvent::log(
            $this->userId,
            'publish_job_completed',
            $status,
            $target->social_account_id,
            $target->social_post_id,
            [],
            $status === 'success' ? ['post_urn' => $target->post->linkedin_post_urn] : [],
            $status === 'failed' ? $target->error_message : null,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PublishLinkedInPostJob permanently failed', [
            'target_id' => $this->targetId,
            'error'     => $e->getMessage(),
        ]);

        SocialAuditEvent::log(
            $this->userId,
            'publish_job_failed',
            'failed',
            null,
            null,
            [],
            [],
            $e->getMessage(),
        );
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    // Rate-limit exceptions trigger a longer backoff without consuming a retry
    public function middleware(): array
    {
        return [];
    }

    public function tags(): array
    {
        return ["target:{$this->targetId}", "user:{$this->userId}", 'linkedin'];
    }
}
