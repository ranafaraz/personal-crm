<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private NotificationPreferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user    = User::factory()->create();
        $this->service = app(NotificationPreferenceService::class);
    }

    // =========================================================================
    // Default preferences
    // =========================================================================

    public function test_database_channel_is_enabled_by_default(): void
    {
        foreach (NotificationPreferenceService::TYPES as $type) {
            $this->assertTrue(
                $this->service->isEnabled($this->user, $type, 'database'),
                "Expected database channel to be enabled by default for {$type}",
            );
        }
    }

    public function test_mail_channel_is_disabled_by_default(): void
    {
        foreach (NotificationPreferenceService::TYPES as $type) {
            $this->assertFalse(
                $this->service->isEnabled($this->user, $type, 'mail'),
                "Expected mail channel to be disabled by default for {$type}",
            );
        }
    }

    public function test_push_channel_is_disabled_by_default(): void
    {
        foreach (NotificationPreferenceService::TYPES as $type) {
            $this->assertFalse(
                $this->service->isEnabled($this->user, $type, 'push'),
                "Expected push channel to be disabled by default for {$type}",
            );
        }
    }

    // =========================================================================
    // Setting preferences
    // =========================================================================

    public function test_set_preference_creates_record(): void
    {
        $this->service->setPreference($this->user, 'reply_received', 'mail', true);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id'           => $this->user->id,
            'notification_type' => 'reply_received',
            'channel'           => 'mail',
            'enabled'           => true,
        ]);
    }

    public function test_set_preference_updates_existing_record(): void
    {
        $this->service->setPreference($this->user, 'email_failed', 'mail', true);
        $this->service->setPreference($this->user, 'email_failed', 'mail', false);

        $this->assertDatabaseCount('notification_preferences', 1);
        $this->assertFalse($this->service->isEnabled($this->user, 'email_failed', 'mail'));
    }

    public function test_disabling_database_channel_removes_it_from_via(): void
    {
        $this->service->setPreference($this->user, 'email_sent', 'database', false);

        $channels = $this->service->enabledChannels($this->user, 'email_sent');
        $this->assertNotContains('database', $channels);
    }

    public function test_enabling_mail_adds_it_to_via(): void
    {
        $this->service->setPreference($this->user, 'positive_reply', 'mail', true);

        $channels = $this->service->enabledChannels($this->user, 'positive_reply');
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_push_channel_never_returns_in_enabled_channels(): void
    {
        // Push is a placeholder — enabling it should not add it to the via array yet
        $this->service->setPreference($this->user, 'daily_summary', 'push', true);

        $channels = $this->service->enabledChannels($this->user, 'daily_summary');
        $this->assertNotContains('push', $channels);
    }

    // =========================================================================
    // getAllPreferences structure
    // =========================================================================

    public function test_get_all_preferences_returns_all_types_and_channels(): void
    {
        $prefs = $this->service->getAllPreferences($this->user);

        foreach (NotificationPreferenceService::TYPES as $type) {
            $this->assertArrayHasKey($type, $prefs);
            foreach (NotificationPreferenceService::CHANNELS as $channel) {
                $this->assertArrayHasKey($channel, $prefs[$type]);
            }
        }
    }

    // =========================================================================
    // Preference HTTP endpoint
    // =========================================================================

    public function test_update_preference_via_http_requires_auth(): void
    {
        $this->post(route('notifications.preferences'), [
            'notification_type' => 'reply_received',
            'channel'           => 'mail',
            'enabled'           => true,
        ])->assertRedirect(route('login'));
    }

    public function test_update_preference_via_http_works(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.preferences'), [
                'notification_type' => 'reply_received',
                'channel'           => 'mail',
                'enabled'           => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($this->service->isEnabled($this->user, 'reply_received', 'mail'));
    }

    public function test_update_preference_rejects_invalid_type(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.preferences'), [
                'notification_type' => 'nonexistent_type',
                'channel'           => 'mail',
                'enabled'           => '1',
            ])
            ->assertSessionHasErrors('notification_type');
    }

    // =========================================================================
    // User scoping
    // =========================================================================

    public function test_preferences_are_scoped_to_user(): void
    {
        $otherUser = User::factory()->create();
        $this->service->setPreference($this->user, 'email_sent', 'mail', true);

        $this->assertFalse($this->service->isEnabled($otherUser, 'email_sent', 'mail'));
    }

    public function test_preference_count_stays_within_user_scope(): void
    {
        $otherUser = User::factory()->create();
        $this->service->setPreference($this->user, 'reply_received', 'mail', true);
        $this->service->setPreference($otherUser, 'reply_received', 'mail', true);

        $this->assertEquals(
            1,
            NotificationPreference::where('user_id', $this->user->id)->count(),
        );
    }
}
