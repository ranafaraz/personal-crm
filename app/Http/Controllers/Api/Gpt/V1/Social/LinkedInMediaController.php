<?php

namespace App\Http\Controllers\Api\Gpt\V1\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\SocialAccount;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Services\Social\LinkedInMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkedInMediaController extends GptController
{
    public function __construct(private LinkedInMediaService $mediaSvc) {}

    /** List media assets for a post. */
    public function index(Request $request, int $postId): JsonResponse
    {
        $post = $this->findPost($this->apiUser($request)->id, $postId);
        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $assets = $post->mediaAssets()->get()->map(fn ($a) => $this->formatAsset($a));

        return response()->json(['assets' => $assets]);
    }

    /**
     * Upload a pending CRM asset to LinkedIn.
     * This initializes the LinkedIn upload and pushes the binary.
     * The linkedin_media_urn is stored on the asset so it can be used in post payloads.
     */
    public function uploadToLinkedIn(Request $request, int $postId, int $assetId): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $postId);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $asset = SocialMediaAsset::where('id', $assetId)
            ->where('user_id', $user->id)
            ->first();

        if (! $asset) {
            return response()->json(['error' => 'Media asset not found.'], 404);
        }

        if ($asset->approval_status !== 'approved') {
            return response()->json(['error' => 'Asset must be approved before uploading to LinkedIn.'], 422);
        }

        $account = $this->resolveAccount($user->id, $post);
        if (! $account) {
            return response()->json(['error' => 'No connected LinkedIn account found.'], 422);
        }

        $imageUrn = $this->mediaSvc->uploadAsset(
            $account->access_token_encrypted,
            $account->provider_account_urn,
            $asset,
        );

        $this->audit($request, 'linkedin_media_upload', SocialMediaAsset::class, $assetId, 'medium');

        return response()->json([
            'asset_id'          => $assetId,
            'linkedin_media_urn' => $imageUrn,
            'upload_status'     => 'uploaded',
        ]);
    }

    /** Associate an existing asset with a post (pivot). */
    public function attach(Request $request, int $postId): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $postId);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $data = $request->validate([
            'asset_id'         => 'required|integer',
            'display_order'    => 'nullable|integer|min:0',
            'is_featured'      => 'nullable|boolean',
            'alt_text_override' => 'nullable|string|max:1000',
        ]);

        $asset = SocialMediaAsset::where('id', $data['asset_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $asset) {
            return response()->json(['error' => 'Asset not found.'], 404);
        }

        $post->mediaAssets()->syncWithoutDetaching([
            $asset->id => [
                'display_order'    => $data['display_order'] ?? 0,
                'is_featured'      => $data['is_featured'] ?? false,
                'alt_text_override' => $data['alt_text_override'] ?? null,
            ],
        ]);

        return response()->json(['message' => 'Asset attached to post.']);
    }

    /** Detach an asset from a post. */
    public function detach(Request $request, int $postId, int $assetId): JsonResponse
    {
        $user = $this->apiUser($request);
        $post = $this->findPost($user->id, $postId);

        if (! $post) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        $post->mediaAssets()->detach($assetId);

        return response()->json(['message' => 'Asset detached from post.']);
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

    private function formatAsset(SocialMediaAsset $a): array
    {
        return [
            'id'                  => $a->id,
            'filename'            => $a->filename,
            'mime_type'           => $a->mime_type,
            'approval_status'     => $a->approval_status,
            'linkedin_media_urn'  => $a->linkedin_media_urn,
            'linkedin_upload_status' => $a->linkedin_upload_status,
            'display_order'       => $a->pivot->display_order ?? 0,
            'is_featured'         => (bool) ($a->pivot->is_featured ?? false),
            'alt_text'            => $a->pivot->alt_text_override ?: $a->alt_text,
        ];
    }
}
