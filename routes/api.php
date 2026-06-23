<?php

use App\Http\Controllers\Api\Gpt\V1\HealthController;
use App\Http\Controllers\Api\Gpt\V1\MeController;
use App\Http\Controllers\Api\Gpt\V1\DashboardSummaryController;
use App\Http\Controllers\Api\Gpt\V1\BulkController;
use App\Http\Controllers\Api\Gpt\V1\OpportunityController;
use App\Http\Controllers\Api\Gpt\V1\ContactController;
use App\Http\Controllers\Api\Gpt\V1\EmailDraftController;
use App\Http\Controllers\Api\Gpt\V1\FollowUpController;
use App\Http\Controllers\Api\Gpt\V1\ReplyController;
use App\Http\Controllers\Api\Gpt\V1\IngestionController;
use App\Http\Controllers\Api\Gpt\V1\ConfirmationController;
use App\Http\Controllers\Api\Gpt\V1\SignatureController;
use App\Http\Controllers\Api\Gpt\V1\AttachmentController;
use App\Http\Controllers\Api\Gpt\V1\DocumentController;
use App\Http\Controllers\Api\Gpt\V1\DraftAttachmentController;
use App\Http\Controllers\Api\Gpt\V1\DraftPreviewController;
use App\Http\Controllers\Api\Gpt\V1\Social\LinkedInAccountController;
use App\Http\Controllers\Api\Gpt\V1\Social\LinkedInPostController;
use App\Http\Controllers\Api\Gpt\V1\Social\LinkedInMediaController;
use App\Http\Controllers\Api\Gpt\V1\Social\LinkedInConfirmationController;
use App\Http\Controllers\Api\Gpt\V1\Social\LinkedInAnalyticsController;
use App\Http\Controllers\Api\Gpt\V1\Content\ContentItemController;
use App\Http\Controllers\Api\Gpt\V1\Research\ResearchPaperController;
use App\Http\Controllers\Api\Gpt\V1\Proposal\ProposalController;
use App\Http\Controllers\Api\Gpt\V1\Youtube\YoutubeVideoController;
use App\Http\Controllers\Api\Gpt\V1\Freelance\FreelanceProjectController;
use App\Http\Controllers\Api\Gpt\V1\Pipeline\PipelineController;
use App\Http\Controllers\Api\Gpt\V1\Pipeline\PipelineRunController;
use App\Http\Controllers\Api\Gpt\V1\Pipeline\ScheduledJobController;
use App\Http\Controllers\Api\Gpt\V1\Webhook\WebhookController;
use App\Http\Controllers\Api\Gpt\V1\Webhook\WebhookDeliveryController;
use App\Http\Controllers\Api\Gpt\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\Gpt\V1\Tag\TagController;
use App\Http\Controllers\Api\Gpt\V1\OutreachPipelineController;
use App\Http\Controllers\Api\Gpt\V1\BulkCreateController;
use App\Http\Controllers\Api\Social\AiDraftController as SocialAiDraftController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// GPT / MCP / n8n API  –  /api/gpt/v1
// ---------------------------------------------------------------------------

// Health is public — schema marks it security:[] so ChatGPT won't send auth header here.
Route::get('gpt/v1/health', HealthController::class)->middleware('throttle:60,1');

Route::prefix('gpt/v1')
    ->middleware(['api.client', 'api.log', 'throttle:mcp-api'])
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
        // Dedup check — must be before {id} route (though id pattern [0-9]+ already prevents conflict)
        Route::get('opportunities/check-duplicate', [OpportunityController::class, 'checkDuplicate'])
            ->middleware('api.scope:opportunities:read');
        Route::get('opportunities/{id}', [OpportunityController::class, 'show'])
            ->middleware('api.scope:opportunities:read');
        Route::post('opportunities/{id}/contacts', [OpportunityController::class, 'linkContact'])
            ->middleware(['api.scope:opportunities:write', 'throttle:20,1']);
        Route::post('opportunities/{id}/notes', [OpportunityController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);
        Route::patch('opportunities/{id}', [OpportunityController::class, 'update'])
            ->middleware(['api.scope:opportunities:write', 'throttle:20,1']);
        Route::delete('opportunities/{id}', [OpportunityController::class, 'destroy'])
            ->middleware('api.scope:opportunities:delete');

        // Contacts
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware(['api.scope:contacts:write', 'throttle:20,1']);
        Route::get('contacts/{id}', [ContactController::class, 'show'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts/{id}/notes', [ContactController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);
        Route::patch('contacts/{id}', [ContactController::class, 'update'])
            ->middleware(['api.scope:contacts:write', 'throttle:20,1']);
        Route::delete('contacts/{id}', [ContactController::class, 'destroy'])
            ->middleware('api.scope:contacts:delete');

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
        // File upload — must be registered before {id} wildcard
        Route::post('attachments/upload', [AttachmentController::class, 'upload'])
            ->middleware(['api.scope:attachments:write', 'throttle:20,1']);
        Route::get('attachments/{id}', [AttachmentController::class, 'show'])
            ->middleware('api.scope:attachments:read');
        Route::get('attachments/{id}/download', [AttachmentController::class, 'download'])
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
        Route::patch('email-drafts/{id}', [EmailDraftController::class, 'update'])
            ->middleware(['api.scope:drafts:update', 'throttle:20,1']);
        Route::delete('email-drafts/{id}', [EmailDraftController::class, 'destroy'])
            ->middleware('api.scope:drafts:delete');
        // Send — MCP clients send synchronously; non-MCP queues via scheduled-send pipeline.
        Route::post('email-drafts/{id}/send', [EmailDraftController::class, 'send'])
            ->middleware(['api.scope:email:send', 'throttle:10,1']);
        // Test render — sends a copy to a verification address without marking as sent.
        Route::post('email-drafts/{id}/send-test', [EmailDraftController::class, 'sendTest'])
            ->middleware(['api.scope:drafts:read', 'throttle:10,1']);

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
        Route::get('follow-ups', [FollowUpController::class, 'index'])
            ->middleware('api.scope:followups:read');
        Route::post('follow-ups', [FollowUpController::class, 'store'])
            ->middleware(['api.scope:followups:create', 'throttle:20,1']);
        Route::patch('follow-ups/{id}', [FollowUpController::class, 'update'])
            ->middleware(['api.scope:followups:update', 'throttle:20,1']);
        Route::delete('follow-ups/{id}', [FollowUpController::class, 'destroy'])
            ->middleware('api.scope:followups:delete');

        // Replies
        Route::get('replies/recent', [ReplyController::class, 'recent'])
            ->middleware('api.scope:replies:read');

        // Bulk ingestion endpoints (n8n / scrapers)
        Route::post('ingestion/opportunities', [IngestionController::class, 'opportunities'])
            ->middleware(['api.scope:opportunities:write', 'throttle:10,1']);
        Route::post('ingestion/contacts', [IngestionController::class, 'contacts'])
            ->middleware(['api.scope:contacts:write', 'throttle:10,1']);

        // ---------------------------------------------------------------------------
        // Documents  – CRUD + versioning + entity links
        // ---------------------------------------------------------------------------

        // Core CRUD
        Route::get('documents', [DocumentController::class, 'index'])
            ->middleware('api.scope:documents:read');
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        // Dedicated file-upload alias — separate path so ChatGPT uses multipart unambiguously
        Route::post('documents/upload', [DocumentController::class, 'store'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::get('documents/{id}', [DocumentController::class, 'show'])
            ->middleware('api.scope:documents:read');
        Route::patch('documents/{id}', [DocumentController::class, 'update'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('documents/{id}', [DocumentController::class, 'destroy'])
            ->middleware('api.scope:documents:write');

        // Download current version
        Route::get('documents/{id}/download', [DocumentController::class, 'download'])
            ->middleware('api.scope:documents:read');

        // Version management (full history preserved — never overwritten)
        Route::get('documents/{id}/versions', [DocumentController::class, 'listVersions'])
            ->middleware('api.scope:documents:read');
        Route::post('documents/{id}/versions', [DocumentController::class, 'addVersion'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::get('documents/{id}/versions/{vid}/download', [DocumentController::class, 'downloadVersion'])
            ->middleware('api.scope:documents:read');

        // Entity link management
        Route::get('documents/{id}/links', [DocumentController::class, 'listLinks'])
            ->middleware('api.scope:documents:read');
        Route::post('documents/{id}/links', [DocumentController::class, 'addLink'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('documents/{id}/links/{linkId}', [DocumentController::class, 'removeLink'])
            ->middleware('api.scope:documents:write');

        // Scoped convenience routes — Opportunities
        Route::get('opportunities/{id}/documents', [DocumentController::class, 'indexForOpportunity'])
            ->middleware('api.scope:documents:read');
        Route::post('opportunities/{id}/documents', [DocumentController::class, 'storeForOpportunity'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('opportunities/{id}/documents/{docId}', [DocumentController::class, 'detachFromOpportunity'])
            ->middleware('api.scope:documents:write');

        // Scoped convenience routes — Contacts
        Route::get('contacts/{id}/documents', [DocumentController::class, 'indexForContact'])
            ->middleware('api.scope:documents:read');
        Route::post('contacts/{id}/documents', [DocumentController::class, 'storeForContact'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('contacts/{id}/documents/{docId}', [DocumentController::class, 'detachFromContact'])
            ->middleware('api.scope:documents:write');

        // Scoped convenience routes — Email Drafts (attaching never triggers sending)
        Route::get('email-drafts/{id}/documents', [DocumentController::class, 'indexForEmailDraft'])
            ->middleware('api.scope:documents:read');
        Route::post('email-drafts/{id}/documents', [DocumentController::class, 'storeForEmailDraft'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('email-drafts/{id}/documents/{docId}', [DocumentController::class, 'detachFromEmailDraft'])
            ->middleware('api.scope:documents:write');

        // Scoped convenience routes — Follow-ups
        Route::get('follow-ups/{id}/documents', [DocumentController::class, 'indexForFollowUp'])
            ->middleware('api.scope:documents:read');
        Route::post('follow-ups/{id}/documents', [DocumentController::class, 'storeForFollowUp'])
            ->middleware(['api.scope:documents:write', 'throttle:20,1']);
        Route::delete('follow-ups/{id}/documents/{docId}', [DocumentController::class, 'detachFromFollowUp'])
            ->middleware('api.scope:documents:write');

        // ---------------------------------------------------------------------------
        // Bulk operations  – batch update/delete across opportunities/contacts/follow-ups
        // Per-id partial success; each row scoped to the calling user.
        // ---------------------------------------------------------------------------
        Route::post('bulk', [BulkController::class, 'handle'])
            ->middleware(['api.scope:bulk:write', 'throttle:10,1']);

        // Bulk CREATE — up to 20 items per request (opportunities, contacts, drafts)
        Route::post('bulk/opportunities', [BulkCreateController::class, 'opportunities'])
            ->middleware(['api.scope:bulk:write', 'throttle:10,1']);
        Route::post('bulk/contacts', [BulkCreateController::class, 'contacts'])
            ->middleware(['api.scope:bulk:write', 'throttle:10,1']);
        Route::post('bulk/drafts', [BulkCreateController::class, 'drafts'])
            ->middleware(['api.scope:bulk:write', 'throttle:10,1']);

        // Outreach pipeline — orchestrates contact+opportunity+draft+followup+tags in one call
        Route::post('pipeline/execute', [OutreachPipelineController::class, 'execute'])
            ->middleware(['api.scope:pipelines:execute', 'throttle:10,1']);

        // Confirmation requests (multi-step AI action gating)
        Route::post('confirmations', [ConfirmationController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('confirmations/{id}', [ConfirmationController::class, 'show']);
    });

// ---------------------------------------------------------------------------
// Social Studio API  –  /api/social/v1
// GPT may create/update drafts and request confirmations.
// NEVER auto-publishes — explicit human approval required for all publishing.
// ---------------------------------------------------------------------------
Route::prefix('social/v1')
    ->middleware(['api.client', 'api.log', 'throttle:30,1'])
    ->group(function () {

        // Legacy draft ingestion (GPT connector)
        Route::get('drafts', [SocialAiDraftController::class, 'index'])
            ->middleware('api.scope:social:read');
        Route::post('drafts', [SocialAiDraftController::class, 'store'])
            ->middleware(['api.scope:social:write', 'throttle:10,1']);

        // ── LinkedIn Accounts ──────────────────────────────────────────────
        Route::get('linkedin/accounts', [LinkedInAccountController::class, 'index'])
            ->middleware('api.scope:social:read');
        Route::post('linkedin/accounts/{id}/verify', [LinkedInAccountController::class, 'verify'])
            ->middleware(['api.scope:social:read', 'throttle:10,1']);

        // ── LinkedIn Posts (drafts + published) ───────────────────────────
        Route::get('linkedin/posts', [LinkedInPostController::class, 'index'])
            ->middleware('api.scope:social:read');
        Route::post('linkedin/posts', [LinkedInPostController::class, 'store'])
            ->middleware(['api.scope:social:write', 'throttle:10,1']);
        Route::get('linkedin/posts/{id}', [LinkedInPostController::class, 'show'])
            ->middleware('api.scope:social:read');
        Route::patch('linkedin/posts/{id}', [LinkedInPostController::class, 'update'])
            ->middleware(['api.scope:social:write', 'throttle:10,1']);
        Route::delete('linkedin/posts/{id}', [LinkedInPostController::class, 'destroy'])
            ->middleware('api.scope:social:write');

        // Confirmation request (human must approve before any publish action)
        Route::post('linkedin/posts/{id}/request-confirmation', [LinkedInPostController::class, 'requestConfirmation'])
            ->middleware(['api.scope:social:publish', 'throttle:5,1']);
        // MCP direct publish — bypasses confirmation gate (MCP clients only)
        Route::post('linkedin/posts/{id}/publish', [LinkedInPostController::class, 'publish'])
            ->middleware(['api.scope:social:publish', 'throttle:5,1']);

        // Published-post management (requires approved confirmation + social:publish scope)
        Route::patch('linkedin/posts/{id}/published', [LinkedInPostController::class, 'updatePublished'])
            ->middleware(['api.scope:social:publish', 'throttle:5,1']);
        Route::delete('linkedin/posts/{id}/published', [LinkedInPostController::class, 'deletePublished'])
            ->middleware(['api.scope:social:publish', 'throttle:5,1']);
        Route::get('linkedin/posts/{id}/provider-status', [LinkedInPostController::class, 'providerStatus'])
            ->middleware('api.scope:social:read');

        // ── CRM Media Library upload (file or URL → asset_id) ────────────
        Route::post('media', [LinkedInMediaController::class, 'store'])
            ->middleware(['api.scope:social:write', 'throttle:10,1']);

        // ── LinkedIn Media ────────────────────────────────────────────────
        Route::get('linkedin/posts/{postId}/media', [LinkedInMediaController::class, 'index'])
            ->middleware('api.scope:social:read');
        Route::post('linkedin/posts/{postId}/media', [LinkedInMediaController::class, 'attach'])
            ->middleware(['api.scope:social:write', 'throttle:10,1']);
        Route::delete('linkedin/posts/{postId}/media/{assetId}', [LinkedInMediaController::class, 'detach'])
            ->middleware('api.scope:social:write');
        Route::post('linkedin/posts/{postId}/media/{assetId}/upload-to-linkedin', [LinkedInMediaController::class, 'uploadToLinkedIn'])
            ->middleware(['api.scope:social:publish', 'throttle:5,1']);

        // ── Confirmations ─────────────────────────────────────────────────
        Route::get('linkedin/confirmations/{token}', [LinkedInConfirmationController::class, 'show'])
            ->middleware('api.scope:social:read');
        Route::post('linkedin/confirmations/{token}/approve', [LinkedInConfirmationController::class, 'approve'])
            ->middleware(['api.scope:social:publish', 'throttle:10,1']);
        Route::post('linkedin/confirmations/{token}/reject', [LinkedInConfirmationController::class, 'reject'])
            ->middleware(['api.scope:social:publish', 'throttle:10,1']);

        // ── Analytics ─────────────────────────────────────────────────────
        Route::get('linkedin/analytics/dashboard', [LinkedInAnalyticsController::class, 'insightsDashboard'])
            ->middleware('api.scope:social:analytics');
        Route::get('linkedin/analytics/accounts/{accountId}/aggregate', [LinkedInAnalyticsController::class, 'aggregateMetrics'])
            ->middleware('api.scope:social:analytics');
        Route::get('linkedin/analytics/accounts/{accountId}/followers', [LinkedInAnalyticsController::class, 'followerMetrics'])
            ->middleware('api.scope:social:analytics');
        Route::get('linkedin/analytics/posts/{postId}', [LinkedInAnalyticsController::class, 'postMetrics'])
            ->middleware('api.scope:social:analytics');
        Route::post('linkedin/analytics/accounts/{accountId}/sync', [LinkedInAnalyticsController::class, 'syncNow'])
            ->middleware(['api.scope:social:analytics', 'throttle:5,1']);
    });

// ---------------------------------------------------------------------------
// Content Calendar API  –  /api/content/v1
// Editorial calendar: ideas → drafts → scheduled → published.
// Publishing here only records status/URL — it does not push to any platform.
// ---------------------------------------------------------------------------
Route::prefix('content/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('items', [ContentItemController::class, 'index'])
            ->middleware('api.scope:content:read');
        Route::post('items', [ContentItemController::class, 'store'])
            ->middleware(['api.scope:content:write', 'throttle:20,1']);
        Route::get('items/{id}', [ContentItemController::class, 'show'])
            ->middleware('api.scope:content:read');
        Route::patch('items/{id}', [ContentItemController::class, 'update'])
            ->middleware(['api.scope:content:write', 'throttle:20,1']);
        Route::delete('items/{id}', [ContentItemController::class, 'destroy'])
            ->middleware('api.scope:content:write');
        Route::post('items/{id}/publish', [ContentItemController::class, 'publish'])
            ->middleware(['api.scope:content:publish', 'throttle:10,1']);
    });

// ---------------------------------------------------------------------------
// Research Papers API  –  /api/research/v1
// Reading list / reference library: to_read → reading → read → archived.
// ---------------------------------------------------------------------------
Route::prefix('research/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('papers', [ResearchPaperController::class, 'index'])
            ->middleware('api.scope:research:read');
        Route::post('papers', [ResearchPaperController::class, 'store'])
            ->middleware(['api.scope:research:write', 'throttle:20,1']);
        Route::get('papers/{id}', [ResearchPaperController::class, 'show'])
            ->middleware('api.scope:research:read');
        Route::patch('papers/{id}', [ResearchPaperController::class, 'update'])
            ->middleware(['api.scope:research:write', 'throttle:20,1']);
        Route::delete('papers/{id}', [ResearchPaperController::class, 'destroy'])
            ->middleware('api.scope:research:write');
    });

// ---------------------------------------------------------------------------
// Proposals API  –  /api/proposals/v1
// Client proposals/quotes: draft → sent → accepted | rejected | expired.
// "send" only records CRM state — it does not transmit the proposal.
// ---------------------------------------------------------------------------
Route::prefix('proposals/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('proposals', [ProposalController::class, 'index'])
            ->middleware('api.scope:proposals:read');
        Route::post('proposals', [ProposalController::class, 'store'])
            ->middleware(['api.scope:proposals:write', 'throttle:20,1']);
        Route::get('proposals/{id}', [ProposalController::class, 'show'])
            ->middleware('api.scope:proposals:read');
        Route::patch('proposals/{id}', [ProposalController::class, 'update'])
            ->middleware(['api.scope:proposals:write', 'throttle:20,1']);
        Route::delete('proposals/{id}', [ProposalController::class, 'destroy'])
            ->middleware('api.scope:proposals:write');
        Route::post('proposals/{id}/send', [ProposalController::class, 'send'])
            ->middleware(['api.scope:proposals:write', 'throttle:10,1']);
    });

// ---------------------------------------------------------------------------
// YouTube API  –  /api/youtube/v1
// Video production pipeline: idea → scripting → recording → editing → scheduled → published.
// "publish" only records CRM state/URL — it does not upload or publish on YouTube.
// ---------------------------------------------------------------------------
Route::prefix('youtube/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('videos', [YoutubeVideoController::class, 'index'])
            ->middleware('api.scope:youtube:read');
        Route::post('videos', [YoutubeVideoController::class, 'store'])
            ->middleware(['api.scope:youtube:write', 'throttle:20,1']);
        Route::get('videos/{id}', [YoutubeVideoController::class, 'show'])
            ->middleware('api.scope:youtube:read');
        Route::patch('videos/{id}', [YoutubeVideoController::class, 'update'])
            ->middleware(['api.scope:youtube:write', 'throttle:20,1']);
        Route::delete('videos/{id}', [YoutubeVideoController::class, 'destroy'])
            ->middleware('api.scope:youtube:write');
        Route::post('videos/{id}/publish', [YoutubeVideoController::class, 'publish'])
            ->middleware(['api.scope:youtube:write', 'throttle:10,1']);
    });

// ---------------------------------------------------------------------------
// Freelance API  –  /api/freelance/v1
// Freelance projects/gigs: lead → proposal → active → on_hold → completed | cancelled.
// "complete" only records CRM state — it does not notify the client.
// ---------------------------------------------------------------------------
Route::prefix('freelance/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('projects', [FreelanceProjectController::class, 'index'])
            ->middleware('api.scope:freelance:read');
        Route::post('projects', [FreelanceProjectController::class, 'store'])
            ->middleware(['api.scope:freelance:write', 'throttle:20,1']);
        Route::get('projects/{id}', [FreelanceProjectController::class, 'show'])
            ->middleware('api.scope:freelance:read');
        Route::patch('projects/{id}', [FreelanceProjectController::class, 'update'])
            ->middleware(['api.scope:freelance:write', 'throttle:20,1']);
        Route::delete('projects/{id}', [FreelanceProjectController::class, 'destroy'])
            ->middleware('api.scope:freelance:write');
        Route::post('projects/{id}/complete', [FreelanceProjectController::class, 'complete'])
            ->middleware(['api.scope:freelance:write', 'throttle:10,1']);
    });

// ---------------------------------------------------------------------------
// Pipelines + Scheduler API  –  /api/pipelines/v1
// Pipelines are named automation definitions (manual/scheduled/webhook trigger);
// "execute" records a PipelineRun. Scheduled jobs are recurring/one-off triggers
// that may run a linked pipeline. Both "execute" and "run" only record state —
// there is no inline execution engine or background cron dispatcher.
// ---------------------------------------------------------------------------
Route::prefix('pipelines/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        // Pipeline definitions
        Route::get('pipelines', [PipelineController::class, 'index'])
            ->middleware('api.scope:pipelines:read');
        Route::post('pipelines', [PipelineController::class, 'store'])
            ->middleware(['api.scope:pipelines:write', 'throttle:20,1']);
        Route::get('pipelines/{id}', [PipelineController::class, 'show'])
            ->middleware('api.scope:pipelines:read');
        Route::patch('pipelines/{id}', [PipelineController::class, 'update'])
            ->middleware(['api.scope:pipelines:write', 'throttle:20,1']);
        Route::delete('pipelines/{id}', [PipelineController::class, 'destroy'])
            ->middleware('api.scope:pipelines:write');
        Route::post('pipelines/{id}/execute', [PipelineController::class, 'execute'])
            ->middleware(['api.scope:pipelines:execute', 'throttle:10,1']);

        // Execution log (read-only)
        Route::get('runs', [PipelineRunController::class, 'index'])
            ->middleware('api.scope:pipelines:read');
        Route::get('runs/{id}', [PipelineRunController::class, 'show'])
            ->middleware('api.scope:pipelines:read');

        // Scheduled jobs
        Route::get('scheduled-jobs', [ScheduledJobController::class, 'index'])
            ->middleware('api.scope:scheduler:read');
        Route::post('scheduled-jobs', [ScheduledJobController::class, 'store'])
            ->middleware(['api.scope:scheduler:write', 'throttle:20,1']);
        Route::get('scheduled-jobs/{id}', [ScheduledJobController::class, 'show'])
            ->middleware('api.scope:scheduler:read');
        Route::patch('scheduled-jobs/{id}', [ScheduledJobController::class, 'update'])
            ->middleware(['api.scope:scheduler:write', 'throttle:20,1']);
        Route::delete('scheduled-jobs/{id}', [ScheduledJobController::class, 'destroy'])
            ->middleware('api.scope:scheduler:write');
        Route::post('scheduled-jobs/{id}/run', [ScheduledJobController::class, 'run'])
            ->middleware(['api.scope:scheduler:write', 'throttle:10,1']);
    });

// ---------------------------------------------------------------------------
// Webhooks API  –  /api/webhooks/v1
// Outbound webhook registrations + a read-only delivery log. "test" records a
// delivery and stamps last_triggered_at — there is no outbound HTTP dispatcher,
// so deliveries are recorded as pending (CRM-state only).
// ---------------------------------------------------------------------------
Route::prefix('webhooks/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        Route::get('webhooks', [WebhookController::class, 'index'])
            ->middleware('api.scope:webhooks:read');
        Route::post('webhooks', [WebhookController::class, 'store'])
            ->middleware(['api.scope:webhooks:write', 'throttle:20,1']);
        Route::get('webhooks/{id}', [WebhookController::class, 'show'])
            ->middleware('api.scope:webhooks:read');
        Route::patch('webhooks/{id}', [WebhookController::class, 'update'])
            ->middleware(['api.scope:webhooks:write', 'throttle:20,1']);
        Route::delete('webhooks/{id}', [WebhookController::class, 'destroy'])
            ->middleware('api.scope:webhooks:write');
        Route::post('webhooks/{id}/test', [WebhookController::class, 'test'])
            ->middleware(['api.scope:webhooks:write', 'throttle:10,1']);

        // Delivery log (read-only)
        Route::get('deliveries', [WebhookDeliveryController::class, 'index'])
            ->middleware('api.scope:webhooks:read');
        Route::get('deliveries/{id}', [WebhookDeliveryController::class, 'show'])
            ->middleware('api.scope:webhooks:read');
    });

// ---------------------------------------------------------------------------
// Analytics API  –  /api/analytics/v1
// Read-only aggregations over the user's CRM data. No tables of its own; every
// query is scoped to the authenticated user. All endpoints require analytics:read.
// ---------------------------------------------------------------------------
Route::prefix('analytics/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1', 'api.scope:analytics:read'])
    ->group(function () {
        Route::get('summary', [AnalyticsController::class, 'summary']);
        Route::get('opportunities', [AnalyticsController::class, 'opportunities']);
        Route::get('revenue', [AnalyticsController::class, 'revenue']);
        Route::get('content', [AnalyticsController::class, 'content']);
    });

// ---------------------------------------------------------------------------
// Tags API  –  /api/tags/v1
// Cross-cutting tag management + attach/detach to contacts and opportunities.
// Tags + the taggable morph pivot pre-exist; the tags table has NO tenant_id
// column, so tags are created with user_id only.
// ---------------------------------------------------------------------------
Route::prefix('tags/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        // Specific routes before tags/{id} to avoid collisions.
        Route::post('tags/attach', [TagController::class, 'attach'])
            ->middleware(['api.scope:tags:write', 'throttle:30,1']);
        Route::post('tags/detach', [TagController::class, 'detach'])
            ->middleware(['api.scope:tags:write', 'throttle:30,1']);
        Route::get('tags/on/{entity}/{id}', [TagController::class, 'on'])
            ->middleware('api.scope:tags:read');

        Route::get('tags', [TagController::class, 'index'])
            ->middleware('api.scope:tags:read');
        Route::post('tags', [TagController::class, 'store'])
            ->middleware(['api.scope:tags:write', 'throttle:30,1']);
        Route::get('tags/{id}', [TagController::class, 'show'])
            ->middleware('api.scope:tags:read');
        Route::patch('tags/{id}', [TagController::class, 'update'])
            ->middleware(['api.scope:tags:write', 'throttle:30,1']);
        Route::delete('tags/{id}', [TagController::class, 'destroy'])
            ->middleware('api.scope:tags:write');
    });
