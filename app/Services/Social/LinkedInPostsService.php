<?php

namespace App\Services\Social;

use App\Models\SocialMediaAsset;
use App\Models\SocialPost;

/**
 * Builds LinkedIn REST API payloads and delegates calls to LinkedInClient.
 * Supports text, article_link, image, and multi-image post types.
 */
class LinkedInPostsService
{
    public function __construct(private LinkedInClient $client) {}

    /**
     * Publish a post to LinkedIn. Returns the post URN.
     */
    public function publish(string $token, SocialPost $post, string $authorUrn): string
    {
        $payload = match ($post->post_type) {
            'text'         => $this->textPayload($post, $authorUrn),
            'article_link' => $this->articlePayload($post, $authorUrn),
            'image'        => $this->imagePayload($post, $authorUrn),
            default        => throw new LinkedInPermanentException("Unsupported post_type: {$post->post_type}"),
        };

        return $this->client->createPost($token, $payload);
    }

    /**
     * Update a published post's commentary.
     */
    public function update(string $token, string $postUrn, string $newCommentary): void
    {
        $this->client->updatePost($token, $postUrn, $newCommentary);
    }

    /**
     * Delete a published post.
     */
    public function delete(string $token, string $postUrn): void
    {
        $this->client->deletePost($token, $postUrn);
    }

    /**
     * Retrieve a published post's data from LinkedIn.
     */
    public function get(string $token, string $postUrn): ?array
    {
        return $this->client->getPost($token, $postUrn);
    }

    // ── Payload builders ──────────────────────────────────────────────────────

    private function textPayload(SocialPost $post, string $authorUrn): array
    {
        return $this->basePayload($authorUrn, $this->buildCommentary($post));
    }

    private function articlePayload(SocialPost $post, string $authorUrn): array
    {
        if (empty($post->article_url)) {
            throw new LinkedInPermanentException('Article URL required for article_link post type.');
        }

        $base = $this->basePayload($authorUrn, $this->buildCommentary($post));
        $base['content'] = [
            'article' => array_filter([
                'source'      => $post->article_url,
                'title'       => $post->title_internal ?: null,
                'description' => null,
            ]),
        ];

        return $base;
    }

    private function imagePayload(SocialPost $post, string $authorUrn): array
    {
        $assets = $post->mediaAssets()
            ->where('approval_status', 'approved')
            ->whereNotNull('linkedin_media_urn')
            ->orderByPivot('display_order')
            ->get();

        if ($assets->isEmpty()) {
            throw new LinkedInPermanentException('No LinkedIn-uploaded approved media found for image post. Upload images to LinkedIn first.');
        }

        $base = $this->basePayload($authorUrn, $this->buildCommentary($post));

        if ($assets->count() === 1) {
            $asset = $assets->first();
            $base['content'] = [
                'media' => array_filter([
                    'id'      => $asset->linkedin_media_urn,
                    'altText' => $asset->pivot->alt_text_override ?: $asset->alt_text ?: null,
                ]),
            ];
        } else {
            $base['content'] = [
                'multiImage' => [
                    'images' => $assets->map(fn (SocialMediaAsset $a) => array_filter([
                        'id'      => $a->linkedin_media_urn,
                        'altText' => $a->pivot->alt_text_override ?: $a->alt_text ?: null,
                    ]))->values()->all(),
                ],
            ];
        }

        return $base;
    }

    private function basePayload(string $authorUrn, string $commentary): array
    {
        return [
            'author'                    => $authorUrn,
            'commentary'                => $commentary,
            'visibility'                => 'PUBLIC',
            'distribution'              => [
                'feedDistribution'              => 'MAIN_FEED',
                'targetEntities'                => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState'            => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];
    }

    private function buildCommentary(SocialPost $post): string
    {
        $body = $post->post_body ?? '';
        $tags = $post->hashtagString();
        return $tags ? "{$body}\n\n{$tags}" : $body;
    }
}
