<?php

namespace App\Console\Commands;

use App\Models\SocialPostTarget;
use App\Services\Social\LinkedInPublishService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishDueSocialPostsCommand extends Command
{
    protected $signature   = 'social:publish-due-posts';
    protected $description = 'Publish scheduled social posts that are due and approved';

    public function handle(LinkedInPublishService $publisher): int
    {
        // Only targets that are: scheduled, due now, and whose parent post is approved
        $due = SocialPostTarget::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->whereHas('post', fn ($q) => $q->where('approval_status', 'approved'))
            ->with(['post', 'account'])
            ->get();

        if ($due->isEmpty()) {
            $this->info('No posts due for publishing.');
            return 0;
        }

        $this->info("Found {$due->count()} target(s) to publish.");

        foreach ($due as $target) {
            // Skip if already being processed (locked by another runner)
            if ($target->locked_at && $target->locked_at->gt(now()->subMinutes(10))) {
                $this->line("  Skipping target #{$target->id} — already locked.");
                continue;
            }

            // Acquire soft lock
            $target->update(['locked_at' => now()]);

            try {
                $result = $publisher->publish($target);
                $this->info("  Published target #{$target->id} → {$result->status}");
            } catch (\Throwable $e) {
                Log::error("social:publish-due-posts failed for target #{$target->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Failed target #{$target->id}: {$e->getMessage()}");
            } finally {
                // Release lock regardless of outcome
                $target->update(['locked_at' => null]);
            }
        }

        return 0;
    }
}
