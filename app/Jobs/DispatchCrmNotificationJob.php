<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCrmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly User $user,
        public readonly Notification $notification,
    ) {}

    public function handle(CrmNotificationService $service): void
    {
        $service->send($this->user, $this->notification);
    }
}
