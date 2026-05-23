<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CrmNotificationService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
    ) {}

    /**
     * Send a CRM notification to a user. Dispatches via queue.
     */
    public function send(User $user, Notification $notification): void
    {
        $type = $this->resolveType($notification);
        $channels = $this->preferenceService->enabledChannels($user, $type);

        if (empty($channels)) {
            $this->log($user, $type, null, 'database', 'skipped', null, 'All channels disabled');
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            $this->log($user, $type, null, 'database', 'failed', null, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark a single notification as read for the given user.
     */
    public function markAsRead(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /**
     * Mark all unread notifications as read for the given user.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Get paginated notifications for the user with optional filters.
     *
     * @param  array{type?: string, read?: bool} $filters
     */
    public function getNotifications(User $user, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $user->notifications();

        if (! empty($filters['type'])) {
            $query->where('data->type', $filters['type']);
        }

        if (isset($filters['read'])) {
            if ($filters['read']) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        return $query->paginate($perPage);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function getRecentUnread(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $user->unreadNotifications()->latest()->limit($limit)->get();
    }

    /**
     * Log a delivery attempt.
     */
    public function log(
        User $user,
        string $type,
        ?string $notificationId,
        string $channel,
        string $status,
        ?array $payload = null,
        ?string $error = null,
    ): void {
        NotificationDeliveryLog::create([
            'user_id'           => $user->id,
            'notification_type' => $type,
            'notification_id'   => $notificationId,
            'channel'           => $channel,
            'status'            => $status,
            'payload'           => $payload,
            'error_message'     => $error,
        ]);
    }

    private function resolveType(Notification $notification): string
    {
        return defined(get_class($notification) . '::TYPE')
            ? $notification::TYPE
            : class_basename($notification);
    }
}
