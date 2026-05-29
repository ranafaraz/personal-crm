<?php

use Illuminate\Support\Facades\Schedule;

// Sync IMAP inboxes for all active accounts every 15 minutes
Schedule::command('crm:sync-inboxes')->everyFifteenMinutes();

// Process due follow-up emails every 30 minutes
Schedule::command('crm:process-follow-ups')->everyThirtyMinutes();

// Queue scheduled emails every 5 minutes
Schedule::command('crm:send-scheduled')->everyFiveMinutes();

// Reset daily email send counters at midnight
Schedule::command('crm:reset-daily-counters')->dailyAt('00:00');

// Publish scheduled social posts every 5 minutes (only approved posts are dispatched)
Schedule::command('social:publish-due-posts')->everyFiveMinutes();
