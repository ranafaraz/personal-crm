<?php

namespace App\Services\Social;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base HTTP client for the LinkedIn REST API (v2 REST).
 * All calls use versioned headers per LinkedIn's requirements.
 */
class LinkedInClient
{
    private const REST_BASE  = 'https://api.linkedin.com/rest';
    private const LEGACY_BASE = 'https://api.linkedin.com/v2';

    private function version(): string
    {
        return config('services.linkedin.api_version', '202412');
    }

    private function headers(string $token): array
    {
        return [
            'Authorization'               => "Bearer {$token}",
            'Linkedin-Version'            => $this->version(),
            'X-Restli-Protocol-Version'   => '2.0.0',
        ];
    }

    // ── Posts ─────────────────────────────────────────────────────────────────

    /** Create a post. Returns the LinkedIn post URN from the response header. */
    public function createPost(string $token, array $payload): string
    {
        $response = Http::withHeaders($this->headers($token))
            ->contentType('application/json')
            ->post(self::REST_BASE . '/posts', $payload);

        $this->assertSuccess($response, 'LinkedIn createPost');

        $urn = $response->header('x-restli-id')
            ?: $response->header('X-RestLi-Id')
            ?: $response->json('id')
            ?: null;

        if (! $urn) {
            throw new LinkedInPermanentException('LinkedIn createPost returned no post URN.');
        }

        $this->logSuccess('createPost', ['urn' => $urn]);
        return $urn;
    }

    /** Partial-update a published post body. */
    public function updatePost(string $token, string $postUrn, string $newCommentary): void
    {
        $encodedUrn = rawurlencode($postUrn);
        $response = Http::withHeaders(array_merge($this->headers($token), [
            'Content-Type' => 'application/json',
        ]))->patch(self::REST_BASE . "/posts/{$encodedUrn}", [
            'patch' => ['$set' => ['commentary' => $newCommentary]],
        ]);

        $this->assertSuccess($response, 'LinkedIn updatePost');
        $this->logSuccess('updatePost', ['urn' => $postUrn]);
    }

    /** Delete a published post. */
    public function deletePost(string $token, string $postUrn): void
    {
        $encodedUrn = rawurlencode($postUrn);
        $response = Http::withHeaders($this->headers($token))
            ->delete(self::REST_BASE . "/posts/{$encodedUrn}");

        $this->assertSuccess($response, 'LinkedIn deletePost');
        $this->logSuccess('deletePost', ['urn' => $postUrn]);
    }

    /** Get a post by URN. Returns the decoded JSON or null if unavailable. */
    public function getPost(string $token, string $postUrn): ?array
    {
        $encodedUrn = rawurlencode($postUrn);
        $response = Http::withHeaders($this->headers($token))
            ->get(self::REST_BASE . "/posts/{$encodedUrn}");

        if ($response->status() === 404) {
            return null;
        }

        $this->assertSuccess($response, 'LinkedIn getPost');
        return $response->json();
    }

    // ── Images ────────────────────────────────────────────────────────────────

    /**
     * Initialize an image upload via the LinkedIn Images REST API.
     * Returns ['uploadUrl' => '...', 'imageUrn' => 'urn:li:image:...']
     */
    public function initializeImageUpload(string $token, string $ownerUrn): array
    {
        $response = Http::withHeaders($this->headers($token))
            ->contentType('application/json')
            ->post(self::REST_BASE . '/images?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => $ownerUrn],
            ]);

        $this->assertSuccess($response, 'LinkedIn initializeImageUpload');

        $data      = $response->json('value', []);
        $uploadUrl = $data['uploadUrl'] ?? null;
        $imageUrn  = $data['image'] ?? null;

        if (! $uploadUrl || ! $imageUrn) {
            throw new LinkedInPermanentException('LinkedIn initializeImageUpload missing uploadUrl or image URN.');
        }

        return ['uploadUrl' => $uploadUrl, 'imageUrn' => $imageUrn];
    }

    /**
     * Upload the raw image binary to LinkedIn's provided upload URL.
     * No auth header — LinkedIn's upload URL is pre-signed.
     */
    public function uploadImageBinary(string $uploadUrl, string $fileContents, string $mimeType): void
    {
        $response = Http::withHeaders(['Content-Type' => $mimeType])
            ->withBody($fileContents, $mimeType)
            ->put($uploadUrl);

        if (! in_array($response->status(), [200, 201])) {
            throw new \RuntimeException('LinkedIn image binary upload failed: HTTP ' . $response->status());
        }
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    /**
     * Member post statistics for a specific post URN.
     * Returns the raw JSON from LinkedIn.
     */
    public function getPostStatistics(string $token, string $postUrn): array
    {
        $response = Http::withHeaders($this->headers($token))
            ->get(self::REST_BASE . '/memberPostStatistics', [
                'q'    => 'member',
                'post' => $postUrn,
            ]);

        if ($response->status() === 403) {
            throw new LinkedInPermissionException('Missing r_member_postAnalytics permission.');
        }

        $this->assertSuccess($response, 'LinkedIn getPostStatistics');
        return $response->json('elements', []);
    }

    /**
     * Aggregate member post statistics (all posts).
     */
    public function getAggregatePostStatistics(string $token, array $params = []): array
    {
        $response = Http::withHeaders($this->headers($token))
            ->get(self::REST_BASE . '/memberPostStatistics', array_merge(['q' => 'member'], $params));

        if ($response->status() === 403) {
            throw new LinkedInPermissionException('Missing r_member_postAnalytics permission.');
        }

        $this->assertSuccess($response, 'LinkedIn getAggregatePostStatistics');
        return $response->json('elements', []);
    }

    /**
     * Member follower statistics.
     */
    public function getFollowerStatistics(string $token, array $params = []): array
    {
        $response = Http::withHeaders($this->headers($token))
            ->get(self::REST_BASE . '/memberFollowerStatistics', array_merge(['q' => 'member'], $params));

        if ($response->status() === 403) {
            throw new LinkedInPermissionException('Missing r_member_profileAnalytics permission.');
        }

        $this->assertSuccess($response, 'LinkedIn getFollowerStatistics');
        return $response->json('elements', []);
    }

    // ── Token introspection (legacy v2 endpoint) ───────────────────────────────

    /**
     * Introspect a token to get active scopes and validity.
     * Returns ['active' => bool, 'scope' => string, 'expires_at' => int]
     */
    public function introspectToken(string $token, string $clientId, string $clientSecret): array
    {
        $response = Http::asForm()->post(self::LEGACY_BASE . '/introspectToken', [
            'token'         => $token,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $response->successful()) {
            return ['active' => false, 'scope' => '', 'expires_at' => 0];
        }

        return $response->json();
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function assertSuccess(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body   = $this->sanitize($response->body());

        if ($status === 401) {
            throw new LinkedInAuthException("{$context} — token rejected (401): {$body}");
        }
        if ($status === 403) {
            throw new LinkedInPermissionException("{$context} — permission denied (403): {$body}");
        }
        if (in_array($status, [400, 422])) {
            throw new LinkedInPermanentException("{$context} — bad request ({$status}): {$body}");
        }
        if ($status === 429) {
            throw new LinkedInRateLimitException("{$context} — rate limited (429).");
        }

        throw new \RuntimeException("{$context} — provider error ({$status}): {$body}");
    }

    private function sanitize(string $text): string
    {
        return preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $text) ?? $text;
    }

    private function logSuccess(string $method, array $context): void
    {
        Log::info("LinkedInClient::{$method}", $context);
    }
}

class LinkedInAuthException extends \RuntimeException {}
class LinkedInPermissionException extends \RuntimeException {}
class LinkedInRateLimitException extends \RuntimeException {}
class LinkedInPermanentException extends \RuntimeException {}
