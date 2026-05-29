<?php

use App\Http\Controllers\Api\Gpt\V1\HealthController;
use App\Http\Controllers\Api\Gpt\V1\MeController;
use App\Http\Controllers\Api\Gpt\V1\DashboardSummaryController;
use App\Http\Controllers\Api\Gpt\V1\OpportunityController;
use App\Http\Controllers\Api\Gpt\V1\ContactController;
use App\Http\Controllers\Api\Gpt\V1\EmailDraftController;
use App\Http\Controllers\Api\Gpt\V1\FollowUpController;
use App\Http\Controllers\Api\Gpt\V1\ReplyController;
use App\Http\Controllers\Api\Gpt\V1\IngestionController;
use App\Http\Controllers\Api\Gpt\V1\ConfirmationController;
use App\Http\Controllers\Api\Gpt\V1\SignatureController;
use App\Http\Controllers\Api\Gpt\V1\AttachmentController;
use App\Http\Controllers\Api\Gpt\V1\DraftAttachmentController;
use App\Http\Controllers\Api\Gpt\V1\DraftPreviewController;
use App\Http\Controllers\Api\Social\AiDraftController as SocialAiDraftController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// GPT / MCP / n8n API  –  /api/gpt/v1
// ---------------------------------------------------------------------------

// Health is public — schema marks it security:[] so ChatGPT won't send auth header here.
Route::get('gpt/v1/health', HealthController::class)->middleware('throttle:60,1');

Route::prefix('gpt/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        // Identity
        Route::get('me', MeController::class);

        // Dashboard
        Route::get('dashboard-summary', DashboardSummaryController::class)
            ->middleware('api.scope:dashboard:read');

        // Opportunities
        Route::get('opportunities', [OpportunityController::class, 'index'])
            ->middleware('api.scope:opportunities:read');
        Route::post('opportunities', [OpportunityController::class, 'store'])
            ->middleware(['api.scope:opportunities:write', 'throttle:20,1']);
        Route::get('opportunities/{id}', [OpportunityController::class, 'show'])
            ->middleware('api.scope:opportunities:read');
        Route::post('opportunities/{id}/contacts', [OpportunityController::class, 'linkContact'])
            ->middleware(['api.scope:opportunities:write', 'throttle:20,1']);
        Route::post('opportunities/{id}/notes', [OpportunityController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);

        // Contacts
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware(['api.scope:contacts:write', 'throttle:20,1']);
        Route::get('contacts/{id}', [ContactController::class, 'show'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts/{id}/notes', [ContactController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);

        // ---------------------------------------------------------------------------
        // Signatures
        // ---------------------------------------------------------------------------
        Route::get('signatures', [SignatureController::class, 'index'])
            ->middleware('api.scope:signatures:read');
        Route::post('signatures', [SignatureController::class, 'store'])
            ->middleware(['api.scope:signatures:write', 'throttle:20,1']);
        Route::get('signatures/{id}', [SignatureController::class, 'show'])
            ->middleware('api.scope:signatures:read');
        Route::patch('signatures/{id}', [SignatureController::class, 'update'])
            ->middleware(['api.scope:signatures:write', 'throttle:20,1']);
        Route::delete('signatures/{id}', [SignatureController::class, 'destroy'])
            ->middleware('api.scope:signatures:write');

        // ---------------------------------------------------------------------------
        // Attachments
        // ---------------------------------------------------------------------------
        Route::post('attachments', [AttachmentController::class, 'store'])
            ->middleware(['api.scope:attachments:write', 'throttle:20,1']);
        Route::get('attachments/{id}', [AttachmentController::class, 'show'])
            ->middleware('api.scope:attachments:read');
        Route::delete('attachments/{id}', [AttachmentController::class, 'destroy'])
            ->middleware('api.scope:attachments:write');

        // ---------------------------------------------------------------------------
        // Email Drafts
        // ---------------------------------------------------------------------------
        Route::get('email-drafts', [EmailDraftController::class, 'index'])
            ->middleware('api.scope:drafts:read');
        Route::post('email-drafts', [EmailDraftController::class, 'store'])
            ->middleware(['api.scope:drafts:create', 'throttle:5,1']);

        // Draft attachments (manage after draft creation)
        Route::get('email-drafts/{draft_id}/attachments', [DraftAttachmentController::class, 'index'])
            ->middleware('api.scope:drafts:read');
        Route::post('email-drafts/{draft_id}/attachments', [DraftAttachmentController::class, 'store'])
            ->middleware(['api.scope:drafts:create', 'api.scope:attachments:write', 'throttle:20,1']);
        Route::delete('email-drafts/{draft_id}/attachments/{attachment_id}', [DraftAttachmentController::class, 'destroy'])
            ->middleware('api.scope:drafts:create');

        // Draft rendered preview
        Route::get('email-drafts/{id}/rendered-preview', [DraftPreviewController::class, 'show'])
            ->middleware('api.scope:drafts:read');

        // ---------------------------------------------------------------------------
        // Follow-ups
        // ---------------------------------------------------------------------------
        Route::get('follow-ups/due', [FollowUpController::class, 'due'])
            ->middleware('api.scope:followups:read');
        Route::post('follow-ups', [FollowUpController::class, 'store'])
            ->middleware(['api.scope:followups:create', 'throttle:20,1']);

        // Replies
        Route::get('replies/recent', [ReplyController::class, 'recent'])
            ->middleware('api.scope:replies:read');

        // Bulk ingestion endpoints (n8n / scrapers)
        Route::post('ingestion/opportunities', [IngestionController::class, 'opportunities'])
            ->middleware(['api.scope:opportunities:write', 'throttle:10,1']);
        Route::post('ingestion/contacts', [IngestionController::class, 'contacts'])
            ->middleware(['api.scope:contacts:write', 'throttle:10,1']);

        // Confirmation requests (multi-step AI action gating)
        Route::post('confirmations', [ConfirmationController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('confirmations/{id}', [ConfirmationController::class, 'show']);
    });

// ---------------------------------------------------------------------------
// Social Studio AI draft ingestion  –  /api/social/v1
// Drafts only — NEVER auto-publishes. Human approval required.
// ---------------------------------------------------------------------------
Route::prefix('social/v1')
    ->middleware(['api.client', 'api.log', 'throttle:30,1'])
    ->group(function () {
        Route::get('drafts', [SocialAiDraftController::class, 'index']);
        Route::post('drafts', [SocialAiDraftController::class, 'store'])->middleware('throttle:10,1');
    });
