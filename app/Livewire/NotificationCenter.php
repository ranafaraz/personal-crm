<?php

namespace App\Livewire;

use App\Services\CrmNotificationService;
use App\Services\NotificationPreferenceService;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationCenter extends Component
{
    use WithPagination;

    public string $filterType = '';
    public string $filterRead = '';

    protected CrmNotificationService $service;
    protected NotificationPreferenceService $prefService;

    public function boot(CrmNotificationService $service, NotificationPreferenceService $prefService): void
    {
        $this->service     = $service;
        $this->prefService = $prefService;
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRead(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        $this->service->markAsRead(auth()->user(), $id);
        $this->dispatch('notification-read');
    }

    public function markAllRead(): void
    {
        $this->service->markAllAsRead(auth()->user());
        $this->dispatch('notification-read');
    }

    public function render()
    {
        $user    = auth()->user();
        $filters = [];

        if ($this->filterType !== '') {
            $filters['type'] = $this->filterType;
        }

        if ($this->filterRead !== '') {
            $filters['read'] = $this->filterRead === 'read';
        }

        $notifications = $this->service->getNotifications($user, $filters, 20);
        $unreadCount   = $this->service->getUnreadCount($user);
        $preferences   = $this->prefService->getAllPreferences($user);

        return view('livewire.notification-center', compact('notifications', 'unreadCount', 'preferences'));
    }
}
