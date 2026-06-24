<?php

use App\Http\Controllers\Api\App\DashboardController;
use App\Http\Controllers\Api\App\FollowupController;
use Illuminate\Support\Facades\Route;

// Dashboard summary (§4.5)
Route::get('dashboard', [DashboardController::class, 'summary']);

// Follow-up reminders (§4.5)
Route::prefix('followups')->group(function () {
    Route::get('', [FollowupController::class, 'index']);
    Route::post('', [FollowupController::class, 'store']);
    Route::patch('{id}', [FollowupController::class, 'update']);
    Route::delete('{id}', [FollowupController::class, 'destroy']);
    Route::post('{id}/complete', [FollowupController::class, 'complete']);
});
