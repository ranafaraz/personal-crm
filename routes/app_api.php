<?php

use App\Http\Controllers\Api\App\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — /api/app/v1  (Applai Flutter app)
|--------------------------------------------------------------------------
|
| Per-user, Sanctum-authenticated namespace. DISTINCT from the single-key
| agent API at /api/gpt/v1 (which stays untouched). Every authenticated
| route is scoped to the signed-in user via the Tenantable trait.
|
| This file is required from routes/api.php.
*/

Route::prefix('app/v1')->group(function () {

    // ── Public auth (no Bearer token). Strict throttle to deter brute force. ──
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('forgot', [AuthController::class, 'forgot']);
        Route::post('reset', [AuthController::class, 'reset']);
        Route::post('social', [AuthController::class, 'social']);
    });

    // ── Authenticated app surface. Mobile is chatty → generous per-user rate. ──
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

        // Profile / account (§2, §7.1)
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('me', [AuthController::class, 'updateMe']);
            Route::post('change-email', [AuthController::class, 'changeEmail']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::delete('account', [AuthController::class, 'deleteAccount']);
        });

        // Milestone 2+ route groups (opportunities, contacts, drafts, …) are
        // appended here as each milestone lands.
        require __DIR__ . '/app_api_opportunities.php';
        require __DIR__ . '/app_api_contacts.php';
        require __DIR__ . '/app_api_drafts.php';
        require __DIR__ . '/app_api_followups.php';
    });
});
