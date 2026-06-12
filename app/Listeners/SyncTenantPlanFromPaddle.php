<?php

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Events\SubscriptionCanceled;
use Laravel\Paddle\Events\SubscriptionCreated;
use Laravel\Paddle\Events\SubscriptionPaused;
use Laravel\Paddle\Events\SubscriptionUpdated;
use Laravel\Paddle\Subscription;

/**
 * Keeps tenants.plan / tenants.status in sync with Paddle subscription
 * webhooks. The plan key is resolved by matching the subscription's price id
 * against the paddle_price_ids configured per plan in config/plans.php.
 */
class SyncTenantPlanFromPaddle
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            SubscriptionCreated::class  => 'handleCreated',
            SubscriptionUpdated::class  => 'handleUpdated',
            SubscriptionCanceled::class => 'handleCanceled',
            SubscriptionPaused::class   => 'handlePaused',
        ];
    }

    public function handleCreated(SubscriptionCreated $event): void
    {
        $this->syncFromSubscription($event->subscription);
    }

    public function handleUpdated(SubscriptionUpdated $event): void
    {
        $this->syncFromSubscription($event->subscription);
    }

    public function handleCanceled(SubscriptionCanceled $event): void
    {
        $tenant = $event->subscription->billable;
        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($event->subscription->onGracePeriod()) {
            // Paid until period end; the final webhook after expiry (or the
            // scheduled status flip) downgrades the tenant.
            return;
        }

        // Fully ended: fall back to the free tier but keep the account usable.
        $tenant->update(['plan' => 'free', 'status' => 'active']);
    }

    public function handlePaused(SubscriptionPaused $event): void
    {
        $tenant = $event->subscription->billable;
        if ($tenant instanceof Tenant) {
            $tenant->update(['plan' => 'free', 'status' => 'active']);
        }
    }

    private function syncFromSubscription(Subscription $subscription): void
    {
        $tenant = $subscription->billable;
        if (! $tenant instanceof Tenant) {
            return;
        }

        if (! in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])) {
            if ($subscription->status === Subscription::STATUS_CANCELED && ! $subscription->onGracePeriod()) {
                $tenant->update(['plan' => 'free', 'status' => 'active']);
            }

            return;
        }

        $planKey = $this->planKeyForSubscription($subscription);

        if ($planKey === null) {
            Log::warning('Paddle subscription price id does not match any configured plan', [
                'tenant_id'       => $tenant->id,
                'subscription_id' => $subscription->paddle_id,
            ]);

            return;
        }

        $tenant->update(['plan' => $planKey, 'status' => 'active']);
    }

    private function planKeyForSubscription(Subscription $subscription): ?string
    {
        foreach (config('plans.plans') as $key => $plan) {
            $priceIds = array_filter($plan['paddle_price_ids'] ?? []);

            foreach ($subscription->items as $item) {
                if (in_array($item->price_id, $priceIds, true)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
