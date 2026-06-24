<?php

namespace Tests\Feature\App;

use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) follow-up reminder CRUD + complete (Milestone 5).
 */
class FollowupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function makeOpportunity(User $user, array $overrides = []): Opportunity
    {
        return Opportunity::factory()->create(array_merge(['user_id' => $user->id], $overrides));
    }

    private function makeFollowUp(User $user, Opportunity $opp, array $overrides = []): FollowUp
    {
        return FollowUp::factory()->create(array_merge([
            'user_id'          => $user->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
            'status'           => 'pending',
            'due_at'           => now()->addDay(),
        ], $overrides));
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_guest_cannot_list_followups(): void
    {
        $this->getJson('/api/app/v1/followups')->assertUnauthorized();
    }

    public function test_returns_empty_list_when_no_followups(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/app/v1/followups')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_lists_upcoming_followups_by_default(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $this->makeFollowUp($user, $opp, ['due_at' => now()->addDay()]);   // future → included
        $this->makeFollowUp($user, $opp, ['due_at' => now()->subDay()]);   // overdue → excluded

        $this->getJson('/api/app/v1/followups')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_due_today(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $this->makeFollowUp($user, $opp, ['due_at' => now()->startOfDay()]);
        $this->makeFollowUp($user, $opp, ['due_at' => now()->addDay()]);

        $this->getJson('/api/app/v1/followups?filter=due_today')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_overdue(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $this->makeFollowUp($user, $opp, ['due_at' => now()->subDays(2)]);
        $this->makeFollowUp($user, $opp, ['due_at' => now()->addDay()]);

        $this->getJson('/api/app/v1/followups?filter=overdue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_excludes_other_users_followups(): void
    {
        $user  = $this->actingAsUser();
        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);
        FollowUp::factory()->create([
            'user_id'          => $other->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
            'due_at'           => now()->addDay(),
        ]);

        $this->getJson('/api/app/v1/followups')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_list_response_includes_opportunity_summary(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user, ['title' => 'SWE Role', 'organization' => 'Acme']);
        $this->makeFollowUp($user, $opp);

        $this->getJson('/api/app/v1/followups')
            ->assertOk()
            ->assertJsonPath('data.0.opportunity.title', 'SWE Role')
            ->assertJsonPath('data.0.opportunity.org', 'Acme');
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_guest_cannot_create_followup(): void
    {
        $this->postJson('/api/app/v1/followups', [])->assertUnauthorized();
    }

    public function test_creates_followup(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);

        $this->postJson('/api/app/v1/followups', [
            'opportunity_id' => $opp->id,
            'due_at'         => now()->addDays(3)->toIso8601String(),
            'note'           => 'Check in about the role',
        ])
            ->assertCreated()
            ->assertJsonPath('data.opportunity_id', $opp->id)
            ->assertJsonPath('data.note', 'Check in about the role')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_create_note_is_optional(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);

        $this->postJson('/api/app/v1/followups', [
            'opportunity_id' => $opp->id,
            'due_at'         => now()->addDay()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.note', null);
    }

    public function test_create_requires_opportunity_id(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/app/v1/followups', ['due_at' => now()->addDay()->toIso8601String()])
            ->assertUnprocessable();
    }

    public function test_create_requires_future_due_at(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);

        $this->postJson('/api/app/v1/followups', [
            'opportunity_id' => $opp->id,
            'due_at'         => now()->subDay()->toIso8601String(),
        ])
            ->assertUnprocessable();
    }

    public function test_cannot_create_for_another_users_opportunity(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);

        $this->postJson('/api/app/v1/followups', [
            'opportunity_id' => $opp->id,
            'due_at'         => now()->addDay()->toIso8601String(),
        ])
            ->assertNotFound();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_updates_followup_note(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $f    = $this->makeFollowUp($user, $opp, ['subject' => 'Original note']);

        $this->patchJson("/api/app/v1/followups/{$f->id}", ['note' => 'Updated reminder'])
            ->assertOk()
            ->assertJsonPath('data.note', 'Updated reminder');
    }

    public function test_update_can_clear_note(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $f    = $this->makeFollowUp($user, $opp, ['subject' => 'Some note']);

        $this->patchJson("/api/app/v1/followups/{$f->id}", ['note' => null])
            ->assertOk()
            ->assertJsonPath('data.note', null);
    }

    public function test_cannot_update_another_users_followup(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);
        $f     = FollowUp::factory()->create([
            'user_id'          => $other->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
        ]);

        $this->patchJson("/api/app/v1/followups/{$f->id}", ['note' => 'Hacked'])
            ->assertNotFound();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_deletes_followup(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $f    = $this->makeFollowUp($user, $opp);

        $this->deleteJson("/api/app/v1/followups/{$f->id}")->assertNoContent();
        $this->assertDatabaseMissing('follow_ups', ['id' => $f->id]);
    }

    public function test_cannot_delete_another_users_followup(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);
        $f     = FollowUp::factory()->create([
            'user_id'          => $other->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
        ]);

        $this->deleteJson("/api/app/v1/followups/{$f->id}")->assertNotFound();
    }

    // ── Complete ──────────────────────────────────────────────────────────────

    public function test_completes_followup(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $f    = $this->makeFollowUp($user, $opp);

        $this->postJson("/api/app/v1/followups/{$f->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($f->fresh()->sent_at);
    }

    public function test_cannot_complete_already_completed_followup(): void
    {
        $user = $this->actingAsUser();
        $opp  = $this->makeOpportunity($user);
        $f    = $this->makeFollowUp($user, $opp, ['status' => 'sent']);

        $this->postJson("/api/app/v1/followups/{$f->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'ALREADY_COMPLETED');
    }

    public function test_cannot_complete_another_users_followup(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create();
        $opp   = Opportunity::factory()->create(['user_id' => $other->id]);
        $f     = FollowUp::factory()->create([
            'user_id'          => $other->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
        ]);

        $this->postJson("/api/app/v1/followups/{$f->id}/complete")
            ->assertNotFound();
    }
}
