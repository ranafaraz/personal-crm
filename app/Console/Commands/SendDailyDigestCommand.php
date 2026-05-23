<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyDigestJob;
use Illuminate\Console\Command;

class SendDailyDigestCommand extends Command
{
    protected $signature = 'crm:daily-digest {--user= : Send only to this user ID}';
    protected $description = 'Send daily summary notifications to all users (or one user)';

    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        SendDailyDigestJob::dispatch($userId);

        $this->info('Daily digest job dispatched.');

        return Command::SUCCESS;
    }
}
