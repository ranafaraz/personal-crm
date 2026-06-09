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
use App\Http\Controllers\EmailSignatureController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InboxMessageController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuppressionListController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OpenApiController;
use App\Http\Controllers\SocialStudio\CalendarController as SocialCalendarController;
use App\Http\Controllers\SocialStudio\ConnectionController as SocialConnectionController;
use App\Http\Controllers\SocialStudio\DashboardController as SocialDashboardController;
use App\Http\Controllers\SocialStudio\MediaController as SocialMediaController;
use App\Http\Controllers\SocialStudio\OAuthAppController as SocialOAuthAppController;
use App\Http\Controllers\SocialStudio\PostController as SocialPostController;
use App\Http\Controllers\SocialStudio\InsightsController as SocialInsightsController;
use App\Http\Controllers\SocialStudio\PublishedController as SocialPublishedController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public landing pages (no auth required)
// ---------------------------------------------------------------------------
Route::get('/',        [LandingController::class, 'index'])->name('home');
Route::get('/privacy', [LandingController::class, 'privacy'])->name('privacy');
Route::get('/terms',   [LandingController::class, 'terms'])->name('terms');

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

    // Dashboard (named route moved to /dashboard; / is now the public landing page)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
    Route::post('email-accounts/{id}/set-default', [EmailAccountController::class, 'setDefault'])
        ->name('email-accounts.set-default');

    // ---------------------------------------------------------------------------
    // Contacts
    // ---------------------------------------------------------------------------
    Route::post('contacts/quick-store', [ContactController::class, 'quickStore'])
        ->name('contacts.quick-store');
    Route::resource('contacts', ContactController::class);
    Route::post('contacts/{id}/suppress', [ContactController::class, 'suppress'])
        ->name('contacts.suppress');

    // ---------------------------------------------------------------------------
    // Opportunities
    // ---------------------------------------------------------------------------
    Route::delete('opportunities/bulk', [OpportunityController::class, 'bulkDestroy'])
        ->name('opportunities.bulk-destroy');
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
    // Email Signatures
    // ---------------------------------------------------------------------------
    Route::post('email-signatures/{id}/set-default', [EmailSignatureController::class, 'setDefault'])
        ->name('email-signatures.set-default');
    Route::resource('email-signatures', EmailSignatureController::class)->except(['show']);

    // ---------------------------------------------------------------------------
    // Email Messages (Compose / Outbox)
    // The getTemplate route must be defined BEFORE the resource so the literal
    // segment "template" is not swallowed by {email} as a wildcard.
    // ---------------------------------------------------------------------------
    Route::get('emails/template', [EmailMessageController::class, 'getTemplate'])
        ->name('emails.get-template');
    Route::get('compose', [EmailMessageController::class, 'compose'])->name('compose');
    Route::resource('emails', EmailMessageController::class);

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

    // Master lookup autocomplete (country, industry, source, city, designation)
    Route::get('lookups/{type}', [LookupController::class, 'index'])->name('lookups.index');
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
// Social Studio
// ---------------------------------------------------------------------------
Route::middleware('auth')->prefix('social-studio')->name('social-studio.')->group(function () {
    Route::get('/', [SocialDashboardController::class, 'index'])->name('dashboard');

    // LinkedIn OAuth apps (developer credentials)
    Route::get('oauth-apps', [SocialOAuthAppController::class, 'index'])->name('oauth-apps.index');
    Route::get('oauth-apps/create', [SocialOAuthAppController::class, 'create'])->name('oauth-apps.create');
    Route::post('oauth-apps', [SocialOAuthAppController::class, 'store'])->name('oauth-apps.store');
    Route::get('oauth-apps/{id}/edit', [SocialOAuthAppController::class, 'edit'])->name('oauth-apps.edit');
    Route::put('oauth-apps/{id}', [SocialOAuthAppController::class, 'update'])->name('oauth-apps.update');
    Route::delete('oauth-apps/{id}', [SocialOAuthAppController::class, 'destroy'])->name('oauth-apps.destroy');
    Route::patch('oauth-apps/{id}/set-default', [SocialOAuthAppController::class, 'setDefault'])->name('oauth-apps.set-default');

    // LinkedIn OAuth connections (accounts)
    Route::get('connections', [SocialConnectionController::class, 'index'])->name('connections');
    Route::get('connections/connect', [SocialConnectionController::class, 'connect'])->name('connections.connect');
    Route::get('connections/callback', [SocialConnectionController::class, 'callback'])->name('connections.callback');
    Route::post('connections/wordpress', [SocialConnectionController::class, 'storeWordPress'])->name('connections.wordpress.store');
    Route::post('connections/manual', [SocialConnectionController::class, 'storeManual'])->name('connections.manual.store');
    Route::delete('connections/{id}', [SocialConnectionController::class, 'disconnect'])->name('connections.disconnect');
    Route::patch('connections/{id}/set-default', [SocialConnectionController::class, 'setDefault'])->name('connections.set-default');
    Route::patch('connections/{id}/verify', [SocialConnectionController::class, 'verify'])->name('connections.verify');

    // Posts
    Route::get('posts', [SocialPostController::class, 'index'])->name('posts.index');
    Route::get('posts/create', [SocialPostController::class, 'create'])->name('posts.create');
    Route::post('posts', [SocialPostController::class, 'store'])->name('posts.store');
    Route::get('posts/{id}', [SocialPostController::class, 'show'])->name('posts.show');
    Route::get('posts/{id}/edit', [SocialPostController::class, 'edit'])->name('posts.edit');
    Route::put('posts/{id}', [SocialPostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{id}', [SocialPostController::class, 'destroy'])->name('posts.destroy');
    Route::patch('posts/{id}/submit-for-review', [SocialPostController::class, 'submitForReview'])->name('posts.submit-for-review');
    Route::patch('posts/{id}/approve', [SocialPostController::class, 'approve'])->name('posts.approve');
    Route::patch('posts/{id}/reject', [SocialPostController::class, 'reject'])->name('posts.reject');
    Route::patch('posts/{id}/schedule', [SocialPostController::class, 'schedule'])->name('posts.schedule');
    Route::patch('posts/{id}/cancel-schedule', [SocialPostController::class, 'cancelSchedule'])->name('posts.cancel-schedule');
    Route::post('posts/{id}/publish-now', [SocialPostController::class, 'publishNow'])->name('posts.publish-now');

    // Calendar
    Route::get('calendar', [SocialCalendarController::class, 'index'])->name('calendar');

    // Published
    Route::get('published', [SocialPublishedController::class, 'index'])->name('published');

    // Insights
    Route::get('insights', [SocialInsightsController::class, 'index'])->name('insights');
    Route::post('insights/sync', [SocialInsightsController::class, 'syncNow'])->name('insights.sync');

    // Media Library
    Route::get('media', [SocialMediaController::class, 'index'])->name('media.index');
    Route::get('media/create', [SocialMediaController::class, 'create'])->name('media.create');
    Route::post('media', [SocialMediaController::class, 'store'])->name('media.store');
    Route::get('media/{id}', [SocialMediaController::class, 'show'])->name('media.show');
    Route::get('media/{id}/edit', [SocialMediaController::class, 'edit'])->name('media.edit');
    Route::put('media/{id}', [SocialMediaController::class, 'update'])->name('media.update');
    Route::delete('media/{id}', [SocialMediaController::class, 'destroy'])->name('media.destroy');
});

// ---------------------------------------------------------------------------
// OpenAPI schema (public – no auth required for GPT Actions import)
// ---------------------------------------------------------------------------
Route::get('/openapi/gpt-actions.json', [OpenApiController::class, 'gptActions'])
    ->name('openapi.gpt-actions');

Route::get('/openapi/social-gpt-actions.json', [OpenApiController::class, 'socialActions'])
    ->name('openapi.social-gpt-actions');

// ---------------------------------------------------------------------------
// Integration / API key management (requires auth)
// ---------------------------------------------------------------------------
Route::middleware('auth')->prefix('settings/integrations')->name('integrations.')->group(function () {
    Route::get('/', [IntegrationController::class, 'index'])->name('index');
    Route::post('/clients', [IntegrationController::class, 'createClient'])->name('clients.store');
    Route::post('/clients/{client}/tokens', [IntegrationController::class, 'createToken'])->name('tokens.store');
    Route::delete('/clients/{client}', [IntegrationController::class, 'deleteClient'])->name('clients.destroy');
    Route::delete('/tokens/{token}', [IntegrationController::class, 'revokeToken'])->name('tokens.revoke');
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
