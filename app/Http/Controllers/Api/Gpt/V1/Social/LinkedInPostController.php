<?php

namespace App\Http\Controllers\Api\Gpt\V1\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Jobs\PublishLinkedInPostJob;
use App\Models\SocialAccount;
use App\Models\SocialAuditEvent;
use App\Models\SocialPost;
use App\Models\SocialPostConfirmation;
use App\Models\SocialPostTarget;
use App\Services\Social\LinkedInPostsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkedInPostController extends GptController
{
    public function __construct(private LinkedInPostsService $postsSvc) {}

    // ── Draft CRUD ────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $posts = SocialPost::where('user_id', $user->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => $this->formatPost($p));

        return response()->json(['posts' => $posts]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $post = $this->findPost($this->apiUser($request)->id, $id);
        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        return response()->json(['post' => $this->formatPost($post, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $data = $request->validate([
            'title_internal'    => 'nullable|string|max:255',
            'topic'             => 'nullable|string|max:255',
            'post_body'         => 'required|string|max:3000',
            'first_comment_body' => 'nullable|string|max:1250',
            'post_type'         => ['required', Rule::in(['text', 'article_link', 'image'])],
            'article_url'       => 'nullable|url|max:2000',
            'hashtags_json'     => 'nullable|array',
            'hashtags_json.*'   => 'string|max:100',
            'scheduled_at'      => 'nullable|date|after:now',
            'timezone_display'  => 'nullable|string|max:64',
            'author_member_urn' => 'nullable|string|max:100',
        ]);

        $isMcp = $this->apiClient($request)->source_type === 'mcp';

        $post = SocialPost::create(array_merge($data, [
            'user_id'         => $user->id,
            'tenant_id'       => $user->tenant_id,
            'title_internal'  => $data['title_internal'] ?? '',
            'status'          => 'draft',
            'approval_status' => $isMcp ? 'approved' : 'pending_review',
            'content_version' => 1,
            'idempotency_key' => Str::uuid()->toString(),
            'created_source'  => $isMcp ? 'mcp' : 'chatgpt',
        ]));

        $this->audit($request, 'linkedin_post_create', SocialPost::class, $post->id, 'low');

        return response()->json(['post' => $this->formatPost($post)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }
        if (! $post->isEditable()) {
            return response()->json(['error' => "Post cannot be edited in status '{$post->status}'."], 422);
        }

        $data = $request->validate([
            'title_internal'     => 'sometimes|nullable|string|max:255',
            'topic'              => 'sometimes|nullable|string|max:255',
            'post_body'          => 'sometimes|string|max:3000',
            'first_comment_body' => 'sometimes|nullable|string|max:1250',
            'post_type'          => ['sometimes', Rule::in(['text', 'article_link', 'image'])],
            'article_url'        => 'sometimes|nullable|url|max:2000',
            'hashtags_json'      => 'sometimes|nullable|array',
            'scheduled_at'       => 'sometimes|nullable|date|after:now',
            'timezone_display'   => 'sometimes|nullable|string|max:64',
        ]);

        $bodyChanged = isset($data['post_body']) && $data['post_body'] !== $post->post_body;

        $post->update($data);

        if ($bodyChanged) {
            $post->bumpVersion();
            // Invalidate any pending/approved confirmations
            $post->confirmations()
                ->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'expired']);
        }

        $this->audit($request, 'linkedin_post_update', SocialPost::class, $post->id, 'low');

        return response()->json(['post' => $this->formatPost($post->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }
        if (! $post->isEditable()) {
            return response()->json(['error' => "Cannot delete post in status '{$post->status}'."], 422);
        }

        $post->delete();
        $this->audit($request, 'linkedin_post_delete', SocialPost::class, $id, 'medium');

        return response()->json(['message' => 'Post deleted.']);
    }

    // ── Approval / confirmation request ──────────────────────────────────────

    /**
     * Request a publish or schedule confirmation.
     * GPT creates this; human approves it in CRM before publishing proceeds.
     */
    public function requestConfirmation(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $data = $request->validate([
            'action'       => ['required', Rule::in(['publish_now', 'schedule'])],
            'scheduled_at' => 'required_if:action,schedule|nullable|date|after:now',
            'timezone'     => 'nullable|string|max:64',
        ]);

        $confirmation = SocialPostConfirmation::createFor(
            $post,
            $data['action'],
            $data['scheduled_at'] ?? null,
            $data['timezone'] ?? 'UTC',
        );

        SocialAuditEvent::log(
            $user->id, 'publish_confirmation_requested', 'pending',
            null, $post->id,
            ['action' => $data['action']],
        );

        $this->audit($request, 'linkedin_request_confirmation', SocialPost::class, $id, 'medium');

        return response()->json([
            'confirmation_token' => $confirmation->confirmation_token,
            'expires_at'         => $confirmation->expires_at->toIso8601String(),
            'action'             => $confirmation->action,
            'message'            => 'Confirmation created. The user must approve this in the CRM before publishing proceeds.',
        ], 201);
    }

    // ── Provider-level PATCH / DELETE (published posts) ───────────────────────

    /**
     * Update a published post's body on LinkedIn.
     * Requires an active approved confirmation.
     */
    public function updatePublished(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }
        if ($post->status !== 'published' || ! $post->linkedin_post_urn) {
            return response()->json(['error' => 'Post is not published on LinkedIn.'], 422);
        }

        $data = $request->validate([
            'new_commentary'     => 'required|string|max:3000',
            'confirmation_token' => 'required|string',
        ]);

        $confirmation = $this->resolveUsableConfirmation($post, $data['confirmation_token']);
        if (! $confirmation) {
            return response()->json(['error' => 'No valid approved confirmation found. Request and approve a confirmation first.'], 403);
        }

        $account = $this->resolveAccount($user->id, $post);
        if (! $account) {
            return response()->json(['error' => 'No connected LinkedIn account found.'], 422);
        }

        $this->postsSvc->update(
            $account->access_token_encrypted,
            $post->linkedin_post_urn,
            $data['new_commentary'],
        );

        $post->update(['post_body' => $data['new_commentary']]);
        $post->bumpVersion();
        $confirmation->update(['status' => 'used']);

        SocialAuditEvent::log(
            $user->id, 'linkedin_post_updated', 'success',
            $account->id, $post->id,
            ['urn' => $post->linkedin_post_urn],
        );

        $this->audit($request, 'linkedin_update_published', SocialPost::class, $id, 'high');

        return response()->json(['message' => 'Post updated on LinkedIn.']);
    }

    /**
     * Delete a published post from LinkedIn.
     * Requires an active approved confirmation.
     */
    public function deletePublished(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }
        if ($post->status !== 'published' || ! $post->linkedin_post_urn) {
            return response()->json(['error' => 'Post is not published on LinkedIn.'], 422);
        }

        $data = $request->validate(['confirmation_token' => 'required|string']);

        $confirmation = $this->resolveUsableConfirmation($post, $data['confirmation_token']);
        if (! $confirmation) {
            return response()->json(['error' => 'No valid approved confirmation found. Request and approve a confirmation first.'], 403);
        }

        $account = $this->resolveAccount($user->id, $post);
        if (! $account) {
            return response()->json(['error' => 'No connected LinkedIn account found.'], 422);
        }

        $this->postsSvc->delete($account->access_token_encrypted, $post->linkedin_post_urn);

        $post->update(['status' => 'deleted_from_provider']);
        $confirmation->update(['status' => 'used']);

        SocialAuditEvent::log(
            $user->id, 'linkedin_post_deleted', 'success',
            $account->id, $post->id,
        );

        $this->audit($request, 'linkedin_delete_published', SocialPost::class, $id, 'high');

        return response()->json(['message' => 'Post deleted from LinkedIn.']);
    }

    /** Get live status of a published post from LinkedIn. */
    public function providerStatus(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }
        if (! $post->linkedin_post_urn) {
            return response()->json(['error' => 'Post has no LinkedIn URN.'], 422);
        }

        $account = $this->resolveAccount($user->id, $post);
        if (! $account) {
            return response()->json(['error' => 'No connected LinkedIn account.'], 422);
        }

        $data = $this->postsSvc->get($account->access_token_encrypted, $post->linkedin_post_urn);

        if ($data === null) {
            return response()->json(['exists_on_linkedin' => false, 'post_urn' => $post->linkedin_post_urn]);
        }

        return response()->json([
            'exists_on_linkedin' => true,
            'post_urn'           => $post->linkedin_post_urn,
            'post_url'           => $post->linkedin_post_url,
            'lifecycle_state'    => $data['lifecycleState'] ?? null,
        ]);
    }

    // ── MCP direct publish (bypasses confirmation gate) ──────────────────────

    /**
     * Allow MCP clients to publish or schedule a post directly, skipping the
     * request-confirmation → human-approve → click flow. Scope: social:publish.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        if ($this->apiClient($request)->source_type !== 'mcp') {
            return response()->json([
                'error' => 'Direct publish is only available to MCP/Cowork clients. Use request-confirmation flow instead.',
            ], 403);
        }

        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $id);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $data = $request->validate([
            'action'       => ['required', Rule::in(['publish_now', 'schedule'])],
            'scheduled_at' => 'required_if:action,schedule|nullable|date|after:now',
            'timezone'     => 'nullable|string|max:64',
        ]);

        if (! in_array($post->status, ['draft', 'approved'], true)) {
            return response()->json([
                'error'          => "Cannot publish a post with status '{$post->status}'.",
                'current_status' => $post->status,
            ], 422);
        }

        $post->approval_status = 'approved';

        if ($data['action'] === 'publish_now') {
            $post->status = 'publishing';
            $post->save();

            PublishLinkedInPostJob::dispatch($post);

            SocialAuditEvent::log(
                $user->id, 'linkedin_post_publish_queued', 'success',
                null, $post->id,
                ['source' => 'mcp', 'bypassed_confirmation' => true],
            );
            $this->audit($request, 'linkedin_publish_mcp', SocialPost::class, $id, 'high',
                "post_id={$id}", 'dispatched PublishLinkedInPostJob via mcp');

            return response()->json([
                'message' => 'Post queued for immediate publish.',
                'post_id' => $post->id,
                'status'  => $post->status,
            ]);
        }

        // action === schedule
        $timezone    = $data['timezone'] ?? 'UTC';
        $scheduledAt = \Carbon\Carbon::parse($data['scheduled_at'], $timezone)->utc();

        $post->scheduled_at = $scheduledAt;
        $post->status       = 'scheduled';
        $post->save();

        SocialAuditEvent::log(
            $user->id, 'linkedin_post_scheduled', 'success',
            null, $post->id,
            ['source' => 'mcp', 'bypassed_confirmation' => true, 'scheduled_at' => $scheduledAt->toIso8601String()],
        );
        $this->audit($request, 'linkedin_schedule_mcp', SocialPost::class, $id, 'high',
            "scheduled_at={$scheduledAt}", "post_id={$id}");

        return response()->json([
            'message'      => "Post scheduled for {$scheduledAt->toIso8601String()}.",
            'post_id'      => $post->id,
            'status'       => 'scheduled',
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findPost(int $userId, int $id): ?SocialPost
    {
        return SocialPost::where('id', $id)->where('user_id', $userId)->first();
    }

    private function resolveAccount(int $userId, SocialPost $post): ?SocialAccount
    {
        return SocialAccount::where('user_id', $userId)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->when($post->author_member_urn, fn ($q) =>
                $q->where('provider_account_urn', $post->author_member_urn)
            )
            ->orderByDesc('is_default')
            ->first();
    }

    private function resolveUsableConfirmation(
        SocialPost $post,
        string $token,
    ): ?SocialPostConfirmation {
        $confirmation = SocialPostConfirmation::where('confirmation_token', $token)
            ->where('social_post_id', $post->id)
            ->first();

        if (! $confirmation || ! $confirmation->isUsable()) {
            return null;
        }

        if (! $confirmation->contentMatchesPost($post)) {
            return null;
        }

        return $confirmation;
    }

    private function formatPost(SocialPost $post, bool $detailed = false): array
    {
        $base = [
            'id'              => $post->id,
            'title_internal'  => $post->title_internal,
            'post_type'       => $post->post_type,
            'status'          => $post->status,
            'approval_status' => $post->approval_status,
            'content_version' => $post->content_version,
            'scheduled_at'    => $post->scheduled_at?->toIso8601String(),
            'linkedin_post_urn' => $post->linkedin_post_urn,
            'linkedin_post_url' => $post->linkedin_post_url,
            'created_at'      => $post->created_at->toIso8601String(),
            'updated_at'      => $post->updated_at->toIso8601String(),
        ];

        if ($detailed) {
            $base['post_body']          = $post->post_body;
            $base['first_comment_body'] = $post->first_comment_body;
            $base['hashtags']           = $post->hashtags();
            $base['article_url']        = $post->article_url;
            $base['author_member_urn']  = $post->author_member_urn;
        }

        return $base;
    }
}
