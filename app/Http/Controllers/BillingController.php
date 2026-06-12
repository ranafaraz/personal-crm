<?php

namespace App\Http\Controllers;

use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(private PlanLimitsService $limits)
    {
    }

    /** Current plan, usage vs limits, and upgrade/cancel actions. */
    public function index(Request $request): View
    {
        $tenant       = $request->user()->tenant;
        $subscription = $tenant->subscription();

        return view('billing.index', [
            'tenant'        => $tenant,
            'subscription'  => $subscription,
            'onGracePeriod' => $subscription?->onGracePeriod() ?? false,
            'effectivePlan' => $this->limits->effectivePlanKey($tenant),
            'usage'         => $this->limits->usageSummary($tenant),
            'plans'         => config('plans.plans'),
            'paddleReady'   => $this->paddleConfigured(),
        ]);
    }

    /** Landing page for tenants whose trial has ended without a subscription. */
    public function expired(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('billing.expired', [
            'tenant'      => $tenant,
            'plans'       => config('plans.plans'),
            'paddleReady' => $this->paddleConfigured(),
        ]);
    }

    /**
     * Start a Paddle checkout (or swap an existing subscription) for the
     * given plan + billing period.
     */
    public function checkout(Request $request, string $plan, string $period = 'monthly'): View|RedirectResponse
    {
        abort_unless(in_array($plan, ['pro', 'enterprise']) && in_array($period, ['monthly', 'annual']), 404);

        $tenant  = $request->user()->tenant;
        $priceId = config("plans.plans.{$plan}.paddle_price_ids.{$period}");

        if (! $this->paddleConfigured() || ! $priceId) {
            return redirect()->route('billing.index')
                ->withErrors(['plan' => 'Billing is not configured yet. Please contact support.']);
        }

        $subscription = $tenant->subscription();

        if ($subscription && $subscription->valid()) {
            $subscription->swap($priceId);

            return redirect()->route('billing.index')
                ->with('success', 'Your subscription has been updated.');
        }

        $checkout = $tenant->subscribe($priceId)
            ->returnTo(route('billing.index'));

        return view('billing.checkout', [
            'checkout' => $checkout,
            'plan'     => config("plans.plans.{$plan}"),
            'period'   => $period,
        ]);
    }

    /** Cancel at period end (Paddle grace period applies). */
    public function cancel(Request $request): RedirectResponse
    {
        $subscription = $request->user()->tenant->subscription();

        if ($subscription && $subscription->valid() && ! $subscription->canceled()) {
            $subscription->cancel();

            return redirect()->route('billing.index')
                ->with('success', 'Your subscription will end at the close of the current billing period.');
        }

        return redirect()->route('billing.index');
    }

    /** Undo a pending cancelation while still in the grace period. */
    public function resume(Request $request): RedirectResponse
    {
        $subscription = $request->user()->tenant->subscription();

        if ($subscription && $subscription->onGracePeriod()) {
            $subscription->stopCancelation();

            return redirect()->route('billing.index')
                ->with('success', 'Your subscription has been resumed.');
        }

        return redirect()->route('billing.index');
    }

    /** Downgrade an expired trial to the free tier and keep the account usable. */
    public function continueFree(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($tenant->trialExpired()) {
            $tenant->update(['plan' => 'free', 'status' => 'active']);
        }

        return redirect()->route('dashboard')
            ->with('success', 'You are now on the Free plan. Upgrade anytime from Billing.');
    }

    private function paddleConfigured(): bool
    {
        return (bool) (config('cashier.seller_id') && config('cashier.client_side_token'));
    }
}
