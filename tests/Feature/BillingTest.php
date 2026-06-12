<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\SubscriptionCanceled;
use Laravel\Paddle\Events\SubscriptionCreated;
use Laravel\Paddle\Subscription;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name'      => 'Billing Tenant',
            'slug'      => 'billing-' . uniqid(),
            'plan'      => 'free',
            'status'    => 'active',
            'max_users' => 1,
        ], $attributes));
    }

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    public function test_billing_page_renders_with_usage_and_plans(): void
    {
        $user = $this->admin($this->tenant());

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Current plan')
            ->assertSee('Usage')
            ->assertSee('Pro')
            ->assertSee('Team');
    }

    public function test_billing_page_is_admin_only(): void
    {
        $tenant = $this->tenant();
        $this->admin($tenant);
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'member']);

        $this->actingAs($member)->get(route('billing.index'))->assertForbidden();
    }

    public function test_expired_trial_redirects_to_billing_expired(): void
    {
        $tenant = $this->tenant([
            'status'        => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);
        $user = $this->admin($tenant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('billing.expired'));

        // The expired page itself must stay reachable (no redirect loop).
        $this->actingAs($user)->get(route('billing.expired'))->assertOk();
    }

    public function test_continue_free_downgrades_expired_trial(): void
    {
        $tenant = $this->tenant([
            'status'        => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);
        $user = $this->admin($tenant);

        $this->actingAs($user)
            ->post(route('billing.continue-free'))
            ->assertRedirect(route('dashboard'));

        $tenant->refresh();
        $this->assertSame('free', $tenant->plan);
        $this->assertSame('active', $tenant->status);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_suspended_tenant_is_logged_out(): void
    {
        $tenant = $this->tenant(['status' => 'suspended']);
        $user   = $this->admin($tenant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_subscription_created_webhook_syncs_tenant_plan(): void
    {
        config(['plans.plans.pro.paddle_price_ids.monthly' => 'pri_test_pro']);

        $tenant = $this->tenant([
            'status'        => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $subscription = $this->makeSubscription($tenant, 'pri_test_pro');

        event(new SubscriptionCreated($tenant, $subscription, []));

        $tenant->refresh();
        $this->assertSame('pro', $tenant->plan);
        $this->assertSame('active', $tenant->status);
    }

    public function test_subscription_canceled_webhook_downgrades_after_grace(): void
    {
        config(['plans.plans.pro.paddle_price_ids.monthly' => 'pri_test_pro']);

        $tenant       = $this->tenant(['plan' => 'pro', 'status' => 'active']);
        $subscription = $this->makeSubscription($tenant, 'pri_test_pro', [
            'status'  => Subscription::STATUS_CANCELED,
            'ends_at' => now()->subDay(),
        ]);

        event(new SubscriptionCanceled($subscription, []));

        $tenant->refresh();
        $this->assertSame('free', $tenant->plan);
        $this->assertSame('active', $tenant->status);
    }

    public function test_subscribed_expired_trial_is_not_locked_out(): void
    {
        config(['plans.plans.pro.paddle_price_ids.monthly' => 'pri_test_pro']);

        $tenant = $this->tenant([
            'status'        => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);
        $user = $this->admin($tenant);

        $this->makeSubscription($tenant, 'pri_test_pro');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    private function makeSubscription(Tenant $tenant, string $priceId, array $attributes = []): Subscription
    {
        $subscription = $tenant->subscriptions()->create(array_merge([
            'type'      => 'default',
            'paddle_id' => 'sub_' . uniqid(),
            'status'    => Subscription::STATUS_ACTIVE,
        ], $attributes));

        $subscription->items()->create([
            'product_id' => 'pro_test',
            'price_id'   => $priceId,
            'status'     => 'active',
            'quantity'   => 1,
        ]);

        return $subscription->fresh();
    }
}
