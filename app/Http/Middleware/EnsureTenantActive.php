<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            $tenant = $user->tenant;

            if (! $tenant || ! $tenant->isActive()) {
                Auth::logout();
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account has been suspended. Please contact support.']);
            }

            // Billing pages must stay reachable or the redirect below loops.
            if ($request->routeIs('billing.*', 'logout')) {
                return $next($request);
            }

            if ($tenant->trialExpired() && ! $tenant->subscribed()) {
                return redirect()->route('billing.expired');
            }
        }

        return $next($request);
    }
}
