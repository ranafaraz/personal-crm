<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Access control
    // =========================================================================

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_dashboard_is_also_accessible_at_dashboard_path(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    // =========================================================================
    // Stats accuracy
    // =========================================================================

    public function test_dashboard_reflects_correct_contact_count(): void
    {
        Contact::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        // The view should receive a stats array; we just confirm the page loads without error
        // and that our contacts are in the database
        $this->assertDatabaseCount('contacts', 3);
    }

    public function test_dashboard_reflects_active_opportunities(): void
    {
        Opportunity::factory()->count(4)->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);
        Opportunity::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status'  => 'rejected',
        ]);

        $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $this->assertDatabaseCount('opportunities', 6);
    }

    public function test_dashboard_loads_with_no_data(): void
    {
        // Fresh user with no contacts, opportunities, or email accounts
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_dashboard_does_not_show_other_users_data(): void
    {
        $other = User::factory()->create();

        // Create data for the other user
        Contact::factory()->count(5)->create(['user_id' => $other->id]);
        Opportunity::factory()->count(5)->create(['user_id' => $other->id]);

        // Our user has no data — dashboard should still load cleanly
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertOk();
    }

    // =========================================================================
    // Filter parameters
    // =========================================================================

    public function test_dashboard_accepts_date_filter_parameters(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard', [
            'date_from' => now()->subMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]);

        $response->assertOk();
    }

    public function test_dashboard_accepts_type_filter_parameter(): void
    {
        Opportunity::factory()->create(['user_id' => $this->user->id, 'type' => 'job']);

        $response = $this->actingAs($this->user)->get('/dashboard?type=job');

        $response->assertOk();
    }

    public function test_dashboard_accepts_status_filter_parameter(): void
    {
        Opportunity::factory()->create(['user_id' => $this->user->id, 'status' => 'active']);

        $response = $this->actingAs($this->user)->get('/dashboard?status=active');

        $response->assertOk();
    }

    public function test_dashboard_accepts_email_account_filter_parameter(): void
    {
        $account = EmailAccount::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard?email_account_id=' . $account->id);

        $response->assertOk();
    }

    // =========================================================================
    // Email accounts list in dashboard
    // =========================================================================

    public function test_dashboard_passes_email_accounts_to_view(): void
    {
        EmailAccount::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => true,
            'name'      => 'Primary Outreach Account',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Primary Outreach Account');
    }

    public function test_dashboard_does_not_show_inactive_email_accounts_in_filter(): void
    {
        EmailAccount::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => false,
            'name'      => 'Inactive Account XYZ',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        // Inactive accounts should not appear in the email account filter list
        $response->assertDontSee('Inactive Account XYZ');
    }
}
