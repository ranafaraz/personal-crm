<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\OpportunityImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\EmailMessageController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InboxMessageController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuppressionListController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserSettingController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Guest routes
// ---------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// ---------------------------------------------------------------------------
// Authenticated routes
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ---------------------------------------------------------------------------
    // Email Accounts
    // ---------------------------------------------------------------------------
    Route::resource('email-accounts', EmailAccountController::class);
    Route::post('email-accounts/{id}/test-smtp', [EmailAccountController::class, 'testSmtp'])
        ->name('email-accounts.test-smtp');
    Route::post('email-accounts/{id}/test-imap', [EmailAccountController::class, 'testImap'])
        ->name('email-accounts.test-imap');
    Route::post('email-accounts/{id}/sync-inbox', [EmailAccountController::class, 'syncInbox'])
        ->name('email-accounts.sync-inbox');

    // ---------------------------------------------------------------------------
    // Contacts
    // ---------------------------------------------------------------------------
    Route::resource('contacts', ContactController::class);
    Route::post('contacts/{id}/suppress', [ContactController::class, 'suppress'])
        ->name('contacts.suppress');

    // ---------------------------------------------------------------------------
    // Opportunities
    // ---------------------------------------------------------------------------
    Route::resource('opportunities', OpportunityController::class);
    Route::patch('opportunities/{id}/status', [OpportunityController::class, 'updateStatus'])
        ->name('opportunities.update-status');

    // ---------------------------------------------------------------------------
    // Documents
    // ---------------------------------------------------------------------------
    Route::resource('documents', DocumentController::class);
    Route::get('documents/{id}/download', [DocumentController::class, 'download'])
        ->name('documents.download');

    // ---------------------------------------------------------------------------
    // Email Templates
    // ---------------------------------------------------------------------------
    Route::resource('email-templates', EmailTemplateController::class);
    Route::post('email-templates/{id}/duplicate', [EmailTemplateController::class, 'duplicate'])
        ->name('email-templates.duplicate');

    // ---------------------------------------------------------------------------
    // Email Messages (Compose / Outbox)
    // The getTemplate route must be defined BEFORE the resource so the literal
    // segment "template" is not swallowed by {email} as a wildcard.
    // ---------------------------------------------------------------------------
    Route::get('emails/template', [EmailMessageController::class, 'getTemplate'])
        ->name('emails.get-template');
    Route::get('compose', [EmailMessageController::class, 'compose'])->name('compose');
    Route::resource('emails', EmailMessageController::class)->except(['edit', 'update']);

    // ---------------------------------------------------------------------------
    // Inbox
    // ---------------------------------------------------------------------------
    Route::resource('inbox', InboxMessageController::class)->only(['index', 'show', 'destroy']);
    Route::patch('inbox/{id}/review', [InboxMessageController::class, 'markReviewed'])
        ->name('inbox.review');

    // ---------------------------------------------------------------------------
    // Follow-ups
    // ---------------------------------------------------------------------------
    Route::resource('follow-ups', FollowUpController::class)->only(['index', 'show']);
    Route::patch('follow-ups/{id}/cancel', [FollowUpController::class, 'cancel'])
        ->name('follow-ups.cancel');
    Route::patch('follow-ups/{id}/reschedule', [FollowUpController::class, 'reschedule'])
        ->name('follow-ups.reschedule');

    // ---------------------------------------------------------------------------
    // Suppression List
    // ---------------------------------------------------------------------------
    Route::resource('suppression-list', SuppressionListController::class)
        ->only(['index', 'store', 'destroy']);

    // ---------------------------------------------------------------------------
    // Contact Imports
    // ---------------------------------------------------------------------------
    Route::get('imports/template', [ContactImportController::class, 'template'])
        ->name('imports.template');
    Route::resource('imports', ContactImportController::class)
        ->only(['index', 'create', 'store', 'show']);

    // ---------------------------------------------------------------------------
    // Opportunity Imports
    // ---------------------------------------------------------------------------
    Route::get('opportunity-imports/template', [OpportunityImportController::class, 'template'])
        ->name('opportunity-imports.template');
    Route::resource('opportunity-imports', OpportunityImportController::class)
        ->only(['index', 'create', 'store', 'show']);

    // ---------------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------------
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sending-activity', [ReportController::class, 'sendingActivity'])
        ->name('reports.sending-activity');
    Route::get('reports/response-rates', [ReportController::class, 'responseRates'])
        ->name('reports.response-rates');
    Route::get('reports/opportunity-funnel', [ReportController::class, 'opportunityFunnel'])
        ->name('reports.opportunity-funnel');
    Route::get('reports/top-contacts', [ReportController::class, 'topContacts'])
        ->name('reports.top-contacts');

    // ---------------------------------------------------------------------------
    // Audit Logs
    // ---------------------------------------------------------------------------
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // ---------------------------------------------------------------------------
    // Tags
    // ---------------------------------------------------------------------------
    // attach/detach must be registered before the resource to avoid route conflicts
    Route::post('tags/attach', [TagController::class, 'attach'])->name('tags.attach');
    Route::post('tags/detach', [TagController::class, 'detach'])->name('tags.detach');
    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    // ---------------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------------
    Route::get('settings', [UserSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [UserSettingController::class, 'update'])->name('settings.update');

    // ---------------------------------------------------------------------------
    // Team management (tenant admins)
    // ---------------------------------------------------------------------------
    Route::middleware('require_admin')->group(function () {
        Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('settings/team', [TeamController::class, 'store'])->name('team.store');
        Route::patch('settings/team/{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('settings/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
        Route::patch('settings/team/{user}/reset-password', [TeamController::class, 'resetPassword'])->name('team.reset-password');
    });

    // Notifications
    // ---------------------------------------------------------------------------
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/preferences', [NotificationController::class, 'updatePreference'])->name('notifications.preferences');
});

// ---------------------------------------------------------------------------
// Super Admin panel
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Tenants CRUD
        Route::resource('tenants', TenantController::class);
        Route::patch('tenants/{tenant}/suspend',  [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::patch('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');

        // Users within a tenant
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('tenants/{tenant}/users', [AdminUserController::class, 'store'])->name('tenant-users.store');
        Route::patch('tenants/{tenant}/users/{user}', [AdminUserController::class, 'update'])->name('tenant-users.update');
        Route::delete('tenants/{tenant}/users/{user}', [AdminUserController::class, 'destroy'])->name('tenant-users.destroy');
        Route::patch('tenants/{tenant}/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('tenant-users.reset-password');
    });
