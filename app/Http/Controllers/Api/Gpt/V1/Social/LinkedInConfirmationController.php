<?php

namespace App\Http\Controllers\Api\Gpt\V1\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Jobs\PublishLinkedInPostJob;
use App\Models\SocialAuditEvent;
use App\Models\SocialPost;
use App\Models\SocialPostConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkedInConfirmationController extends GptController
{
    /** Get confirmation status by token. */
    public function show(Request $request, string $token): JsonResponse
    {
        $user = $this->apiUser($request);

        $confirmation = SocialPostConfirmation::with('post')
            ->where('confirmation_token', $token)
            ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $confirmation) {
            return response()->json(['error' => 'Confirmation not found.'], 404);
        }

        return response()->json(['confirmation' => $this->formatConfirmation($confirmation)]);
    }

    /**
     * Approve a pending confirmation.
     * Only the CRM UI (web session) should call this — not the GPT API.
     * The GPT controller is provided here for completeness but the web
     * controller in SocialStudio handles the real approval flow.
     */
    public function approve(Request $request, string $token): JsonResponse
    {
        $user = $this->apiUser($request);

        $confirmation = $this->findPending($user->id, $token);
        if (! $confirmation) {
            return response()->json(['error' => 'Confirmation not found or not pending.'], 404);
        }

        if ($confirmation->isExpired()) {
            $confirmation->update(['status' => 'expired']);
            return response()->json(['error' => 'Confirmation has expired.'], 422);
        }

        $post = $confirmation->post;

        if (! $confirmation->contentMatchesPost($post)) {
            $confirmation->update(['status' => 'expired']);
            return response()->json(['error' => 'Post content has changed since confirmation was created. Request a new confirmation.'], 422);
        }

        $confirmation->update([
            'status'      => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        SocialAuditEvent::log(
            $user->id, 'publish_confirmation_approved', 'success',
            null, $post->id,
            ['token' => $token, 'action' => $confirmation->action],
            [],
            null,
            $token,
            $user->id,
        );

        // For publish_now, immediately dispatch the job
        if ($confirmation->action === 'publish_now') {
            $target = $this->resolveOrCreateTarget($post, $user->id);
            if ($target) {
                PublishLinkedInPostJob::dispatch($target->id, $user->id);
                $confirmation->update(['status' => 'used']);
                return response()->json(['message' => 'Approved and publish job queued.', 'action' => 'publish_now']);
            }
        }

        return response()->json([
            'message'    => 'Confirmation approved.',
            'action'     => $confirmation->action,
            'scheduled_at' => $confirmation->scheduled_at?->toIso8601String(),
        ]);
    }

    /** Reject a pending confirmation. */
    public function reject(Request $request, string $token): JsonResponse
    {
        $user = $this->apiUser($request);

        $confirmation = $this->findPending($user->id, $token);
        if (! $confirmation) {
            return response()->json(['error' => 'Confirmation not found or not pending.'], 404);
        }

        $confirmation->update(['status' => 'rejected']);

        SocialAuditEvent::log(
            $user->id, 'publish_confirmation_rejected', 'success',
            null, $confirmation->social_post_id,
        );

        return response()->json(['message' => 'Confirmation rejected.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findPending(int $userId, string $token): ?SocialPostConfirmation
    {
        return SocialPostConfirmation::with('post')
            ->where('confirmation_token', $token)
            ->where('status', 'pending')
            ->whereHas('post', fn ($q) => $q->where('user_id', $userId))
            ->first();
    }

    private function resolveOrCreateTarget(SocialPost $post, int $userId): ?\App\Models\SocialPostTarget
    {
        $existing = $post->targets()->first();
        if ($existing && ! $existing->isPublished()) {
            return $existing;
        }

        $account = \App\Models\SocialAccount::where('user_id', $userId)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->orderByDesc('is_default')
            ->first();

        if (! $account) {
            return null;
        }

        return \App\Models\SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'platform'          => 'linkedin',
            'status'            => 'pending',
        ]);
    }

    private function formatConfirmation(SocialPostConfirmation $c): array
    {
        return [
            'token'                   => $c->confirmation_token,
            'action'                  => $c->action,
            'status'                  => $c->status,
            'is_usable'               => $c->isUsable(),
            'content_version_snapshot' => $c->content_version_snapshot,
            'scheduled_at'            => $c->scheduled_at?->toIso8601String(),
            'timezone'                => $c->timezone,
            'expires_at'              => $c->expires_at->toIso8601String(),
            'approved_at'             => $c->approved_at?->toIso8601String(),
        ];
    }
}
