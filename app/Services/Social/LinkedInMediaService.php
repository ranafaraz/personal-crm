<?php

namespace App\Services\Social;

use App\Models\SocialMediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * Handles the two-step LinkedIn image upload workflow:
 * 1. Initialize upload → get uploadUrl + imageUrn
 * 2. Upload binary to pre-signed URL
 * 3. Store imageUrn on the SocialMediaAsset
 */
class LinkedInMediaService
{
    public function __construct(private LinkedInClient $client) {}

    /**
     * Upload a CRM media asset to LinkedIn.
     * Updates linkedin_media_urn and linkedin_upload_status on the asset.
     * Returns the LinkedIn image URN.
     *
     * @throws LinkedInPermanentException
     */
    public function uploadAsset(string $token, string $ownerUrn, SocialMediaAsset $asset): string
    {
        if (! str_starts_with($asset->mime_type ?? '', 'image/')) {
            throw new LinkedInPermanentException("Asset {$asset->id} is not an image (mime: {$asset->mime_type}).");
        }

        $asset->update(['linkedin_upload_status' => 'uploading']);

        try {
            ['uploadUrl' => $uploadUrl, 'imageUrn' => $imageUrn] =
                $this->client->initializeImageUpload($token, $ownerUrn);

            $fileContents = $this->readAssetFile($asset);

            $this->client->uploadImageBinary($uploadUrl, $fileContents, $asset->mime_type);

            $asset->update([
                'linkedin_media_urn'    => $imageUrn,
                'linkedin_upload_status' => 'uploaded',
            ]);

            return $imageUrn;

        } catch (\Throwable $e) {
            $asset->update(['linkedin_upload_status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Upload all approved, not-yet-uploaded assets for a post.
     * Returns map of asset_id → imageUrn.
     */
    public function uploadMissingAssetsForPost(
        string $token,
        string $ownerUrn,
        \App\Models\SocialPost $post
    ): array {
        $uploaded = [];

        $assets = $post->mediaAssets()
            ->where('approval_status', 'approved')
            ->where(fn ($q) => $q->whereNull('linkedin_media_urn')->orWhere('linkedin_upload_status', '!=', 'uploaded'))
            ->get();

        foreach ($assets as $asset) {
            $uploaded[$asset->id] = $this->uploadAsset($token, $ownerUrn, $asset);
        }

        return $uploaded;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function readAssetFile(SocialMediaAsset $asset): string
    {
        $contents = Storage::disk('public')->get($asset->storage_path);

        if ($contents === null || $contents === false) {
            throw new LinkedInPermanentException("Cannot read asset file: {$asset->storage_path}");
        }

        return $contents;
    }
}
