<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    public const CHANNELS = ['database', 'mail', 'push'];

    public const TYPES = [
        'reply_received',
        'followup_due',
        'email_failed',
        'email_sent',
        'positive_reply',
        'daily_summary',
        'account_sync_failed',
    ];

    /**
     * Defaults: database always on; mail/push off by default to avoid noise.
     */
    private const DEFAULTS = [
        'database' => true,
        'mail'     => false,
        'push'     => false,
    ];

    public function enabledChannels(User $user, string $type): array
    {
        $prefs = $this->getPreferencesForType($user, $type);
        $channels = [];

        foreach (self::CHANNELS as $channel) {
            $pref = $prefs->firstWhere('channel', $channel);
            $enabled = $pref ? $pref->enabled : (self::DEFAULTS[$channel] ?? false);

            if ($enabled) {
                if ($channel === 'push') {
                    // Push is a placeholder — not wired to ntfy yet
                    continue;
                }
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    public function isEnabled(User $user, string $type, string $channel): bool
    {
        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->first();

        if ($pref) {
            return $pref->enabled;
        }

        return self::DEFAULTS[$channel] ?? false;
    }

    public function setPreference(User $user, string $type, string $channel, bool $enabled): NotificationPreference
    {
        $pref = NotificationPreference::updateOrCreate(
            [
                'user_id'           => $user->id,
                'notification_type' => $type,
                'channel'           => $channel,
            ],
            ['enabled' => $enabled],
        );

        $this->bustCache($user);

        return $pref;
    }

    public function getAllPreferences(User $user): array
    {
        $stored = NotificationPreference::where('user_id', $user->id)->get();
        $result = [];

        foreach (self::TYPES as $type) {
            foreach (self::CHANNELS as $channel) {
                $pref = $stored->first(fn ($p) => $p->notification_type === $type && $p->channel === $channel);
                $result[$type][$channel] = $pref ? $pref->enabled : (self::DEFAULTS[$channel] ?? false);
            }
        }

        return $result;
    }

    private function getPreferencesForType(User $user, string $type): Collection
    {
        return NotificationPreference::where('user_id', $user->id)
            ->where('notification_type', $type)
            ->get();
    }

    private function bustCache(User $user): void
    {
        Cache::forget("notification_prefs_{$user->id}");
    }
}
