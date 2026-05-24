<?php

namespace App\Livewire;

use App\Services\CrmNotificationService;
use Illuminate\Support\Collection;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    /** @var Collection<int, \Illuminate\Notifications\DatabaseNotification> */
    public Collection $notifications;

    protected CrmNotificationService $service;

    public function boot(CrmNotificationService $service): void
    {
        $this->service = $service;
    }

    public function mount(): void
    {
        $this->notifications = collect();
        $this->refresh();
    }

    public function refresh(): void
    {
        $user = auth()->user();
        $this->unreadCount = $this->service->getUnreadCount($user);
        $this->notifications = $this->service->getRecentUnread($user, 10);
    }

    public function markRead(string $id): void
    {
        $this->service->markAsRead(auth()->user(), $id);
        $this->refresh();
    }

    public function markAllRead(): void
    {
        $this->service->markAllAsRead(auth()->user());
        $this->refresh();
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
