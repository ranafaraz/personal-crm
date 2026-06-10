<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super_admin'         => \App\Http\Middleware\RequireSuperAdmin::class,
            'require_admin'       => \App\Http\Middleware\RequireAdmin::class,
            'tenant_active'       => \App\Http\Middleware\EnsureTenantActive::class,
            'api.client'          => \App\Http\Middleware\AuthenticateApiClient::class,
            'api.scope'           => \App\Http\Middleware\CheckApiClientScope::class,
            'api.log'             => \App\Http\Middleware\LogApiClientRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // GPT Actions and other API clients don't send an `Accept: application/json`
        // header, so without this, validation/auth errors on /api/* routes would
        // redirect (302) to the web login/landing page instead of returning JSON —
        // leaving the calling agent with HTML instead of an error it can act on.
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
