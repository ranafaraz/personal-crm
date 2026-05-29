<?php

namespace App\Console\Commands;

use App\Jobs\SyncLinkedInAnalyticsJob;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Console\Command;

class SyncLinkedInAnalyticsCommand extends Command
{
    protected $signature = 'social:sync-linkedin-analytics
                            {--account= : Sync a specific account ID only}
                            {--post=    : Sync a specific post ID only}
                            {--hours=72 : Only sync posts published within this many hours}';

    protected $description = 'Queue analytics sync jobs for connected LinkedIn accounts';

    public function handle(): int
    {
        if ($accountId = $this->option('account')) {
            $accounts = SocialAccount::where('id', $accountId)
                ->where('status', 'connected')
                ->get();
        } else {
            $accounts = SocialAccount::whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
                ->where('status', 'connected')
                ->get();
        }

        if ($accounts->isEmpty()) {
            $this->info('No connected LinkedIn accounts found.');
            return self::SUCCESS;
        }

        $postId = $this->option('post') ? (int) $this->option('post') : null;
        $hours  = (int) $this->option('hours');

        foreach ($accounts as $account) {
            if ($postId) {
                SyncLinkedInAnalyticsJob::dispatch($account->id, $postId);
                $this->line("Queued post analytics: account={$account->id} post={$postId}");
                continue;
            }

            // Account-level sync (aggregate + followers)
            SyncLinkedInAnalyticsJob::dispatch($account->id);
            $this->line("Queued aggregate analytics: account={$account->id}");

            // Per-post sync for recently published posts
            SocialPost::where('user_id', $account->user_id)
                ->where('status', 'published')
                ->whereNotNull('linkedin_post_urn')
                ->where('updated_at', '>=', now()->subHours($hours))
                ->each(function (SocialPost $post) use ($account) {
                    SyncLinkedInAnalyticsJob::dispatch($account->id, $post->id);
                    $this->line("  Queued post analytics: post={$post->id}");
                });
        }

        return self::SUCCESS;
    }
}
