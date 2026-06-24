<?php

namespace Tests\Feature\App;

use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) dashboard summary endpoint (Milestone 5).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/app/v1/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_expected_structure(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/app/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['pipeline', 'pending_drafts', 'followups_due_today']]);
    }

    public function test_pipeline_all_stages_present_with_zero_counts(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        foreach (['draft', 'applied', 'replied', 'interview', 'offer', 'won', 'closed', 'archived'] as $stage) {
            $this->assertArrayHasKey($stage, $response->json('data.pipeline'));
        }
    }

    public function test_pipeline_counts_opportunities_by_stage(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'draft']);
        Opportunity::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'applied']);

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        $this->assertEquals(2, $response->json('data.pipeline.draft'));
        $this->assertEquals(3, $response->json('data.pipeline.applied'));
    }

    public function test_pipeline_normalizes_legacy_statuses(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'active']);
        Opportunity::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'waiting_reply']);

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        // active + waiting_reply both normalize to 'applied'
        $this->assertEquals(3, $response->json('data.pipeline.applied'));
    }

    public function test_pending_draft_count(): void
    {
        $user = $this->actingAsUser();
        EmailMessage::factory()->count(2)->create([
            'user_id'   => $user->id,
            'direction' => 'outbound',
            'status'    => 'draft',
        ]);
        EmailMessage::factory()->create([
            'user_id'   => $user->id,
            'direction' => 'outbound',
            'status'    => 'sent',
        ]);

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        $this->assertEquals(2, $response->json('data.pending_drafts'));
    }

    public function test_followups_due_today_count(): void
    {
        $user = $this->actingAsUser();
        $opp  = Opportunity::factory()->create(['user_id' => $user->id]);

        FollowUp::factory()->dueToday()->create([
            'user_id'          => $user->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
        ]);
        FollowUp::factory()->create([
            'user_id'          => $user->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
            'due_at'           => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        $this->assertEquals(1, $response->json('data.followups_due_today'));
    }

    public function test_dashboard_excludes_other_users_data(): void
    {
        $this->actingAsUser();

        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);
        Opportunity::factory()->count(3)->create(['user_id' => $other->id]);
        FollowUp::factory()->dueToday()->create([
            'user_id'          => $other->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
        ]);
        EmailMessage::factory()->create([
            'user_id'   => $other->id,
            'direction' => 'outbound',
            'status'    => 'draft',
        ]);

        $response = $this->getJson('/api/app/v1/dashboard')->assertOk();

        $this->assertEquals(0, $response->json('data.followups_due_today'));
        $this->assertEquals(0, $response->json('data.pending_drafts'));
        $this->assertEquals(0, array_sum($response->json('data.pipeline')));
    }
}
