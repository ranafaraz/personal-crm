<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\AccountSyncFailedNotification;
use App\Notifications\DailySummaryNotification;
use App\Notifications\EmailFailedNotification;
use App\Notifications\EmailSentNotification;
use App\Notifications\FollowUpDueNotification;
use App\Notifications\PositiveReplyNotification;
use App\Notifications\ReplyReceivedNotification;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Database notification creation
    // =========================================================================

    public function test_reply_received_notification_stores_in_database(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox   = InboxMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $account->id,
            'from_email'       => 'test@example.com',
            'subject'          => 'Re: Hello',
            'sentiment'        => 'positive',
        ]);

        $this->user->notify(new ReplyReceivedNotification($inbox));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $this->user->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->user->notifications()->first();
        $this->assertEquals('reply_received', $notification->data['type']);
        $this->assertEquals('test@example.com', $notification->data['from_email']);
        $this->assertNull($notification->read_at);
    }

    public function test_followup_due_notification_stores_in_database(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => $this->user->id]);
        $followUp    = FollowUp::factory()->create([
            'user_id'        => $this->user->id,
            'opportunity_id' => $opportunity->id,
            'due_at'         => now()->addDay(),
        ]);

        $this->user->notify(new FollowUpDueNotification($followUp));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('followup_due', $notification->data['type']);
        $this->assertEquals($followUp->id, $notification->data['follow_up_id']);
    }

    public function test_email_failed_notification_stores_in_database(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $email   = EmailMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $account->id,
            'to_email'         => 'recipient@example.com',
            'subject'          => 'Test Subject',
            'follow_up_number' => 0,
        ]);

        $this->user->notify(new EmailFailedNotification($email, 'SMTP connection refused'));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('email_failed', $notification->data['type']);
        $this->assertStringContainsString('SMTP connection refused', $notification->data['reason']);
    }

    public function test_email_sent_notification_stores_in_database(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $email   = EmailMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $account->id,
            'follow_up_number' => 0,
        ]);

        $this->user->notify(new EmailSentNotification($email));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('email_sent', $notification->data['type']);
    }

    public function test_positive_reply_notification_stores_in_database(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox = InboxMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $account->id,
            'sentiment'        => 'positive',
        ]);

        $this->user->notify(new PositiveReplyNotification($inbox));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('positive_reply', $notification->data['type']);
    }

    public function test_daily_summary_notification_stores_in_database(): void
    {
        $summary = [
            'emails_sent'      => 5,
            'replies_received' => 2,
            'positive_replies' => 1,
            'follow_ups_due'   => 3,
            'failed_sends'     => 0,
            'date'             => now()->toDateString(),
        ];

        $this->user->notify(new DailySummaryNotification($summary));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('daily_summary', $notification->data['type']);
        $this->assertEquals(5, $notification->data['emails_sent']);
    }

    public function test_account_sync_failed_notification_stores_in_database(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);

        $this->user->notify(new AccountSyncFailedNotification($account, 'IMAP timeout'));

        $notification = $this->user->notifications()->first();
        $this->assertEquals('account_sync_failed', $notification->data['type']);
        $this->assertEquals($account->id, $notification->data['email_account_id']);
    }

    // =========================================================================
    // Unread count & mark as read
    // =========================================================================

    public function test_unread_count_increments_on_notification(): void
    {
        $service = app(CrmNotificationService::class);

        $this->assertEquals(0, $service->getUnreadCount($this->user));

        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $this->user->notify(new ReplyReceivedNotification($inbox));

        $this->assertEquals(1, $service->getUnreadCount($this->user));
    }

    public function test_mark_as_read_clears_single_notification(): void
    {
        $service = app(CrmNotificationService::class);
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $this->user->notify(new ReplyReceivedNotification($inbox));

        $notificationId = $this->user->notifications()->first()->id;
        $service->markAsRead($this->user, $notificationId);

        $this->assertEquals(0, $service->getUnreadCount($this->user));
        $this->assertNotNull($this->user->notifications()->first()->read_at);
    }

    public function test_mark_all_read_clears_all_notifications(): void
    {
        $service = app(CrmNotificationService::class);
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox1 = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $inbox2 = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $this->user->notify(new ReplyReceivedNotification($inbox1));
        $this->user->notify(new ReplyReceivedNotification($inbox2));

        $this->assertEquals(2, $service->getUnreadCount($this->user));

        $count = $service->markAllAsRead($this->user);

        $this->assertEquals(2, $count);
        $this->assertEquals(0, $service->getUnreadCount($this->user));
    }

    // =========================================================================
    // User scoping / visibility
    // =========================================================================

    public function test_notifications_are_scoped_to_user(): void
    {
        $otherUser = User::factory()->create();
        $service   = app(CrmNotificationService::class);
        $otherAccount = EmailAccount::factory()->create(['user_id' => $otherUser->id]);
        $inbox = InboxMessage::factory()->create(['user_id' => $otherUser->id, 'email_account_id' => $otherAccount->id]);
        $otherUser->notify(new ReplyReceivedNotification($inbox));

        $this->assertEquals(0, $service->getUnreadCount($this->user));
        $this->assertEquals(1, $service->getUnreadCount($otherUser));
    }

    public function test_notification_center_requires_auth(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_notification_center_loads_for_auth_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_mark_read_via_http_requires_auth(): void
    {
        $this->post(route('notifications.read', ['id' => 'fake-id']))
            ->assertRedirect(route('login'));
    }

    public function test_mark_all_read_via_http(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $this->user->notify(new ReplyReceivedNotification($inbox));

        $this->actingAs($this->user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $otherUser    = User::factory()->create();
        $otherAccount = EmailAccount::factory()->create(['user_id' => $otherUser->id]);
        $inbox        = InboxMessage::factory()->create(['user_id' => $otherUser->id, 'email_account_id' => $otherAccount->id]);
        $otherUser->notify(new ReplyReceivedNotification($inbox));

        $notifId = $otherUser->notifications()->first()->id;

        $this->actingAs($this->user)
            ->post(route('notifications.read', ['id' => $notifId]))
            ->assertRedirect();

        // Other user's notification should still be unread
        $this->assertNull($otherUser->notifications()->first()->read_at);
    }

    // =========================================================================
    // Dashboard counts
    // =========================================================================

    public function test_dashboard_shows_unread_notification_count(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);
        $inbox = InboxMessage::factory()->create(['user_id' => $this->user->id, 'email_account_id' => $account->id]);
        $this->user->notify(new ReplyReceivedNotification($inbox));

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('unreadNotifications', 1);
    }

    public function test_dashboard_notification_count_is_zero_when_none(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('unreadNotifications', 0);
    }
}
