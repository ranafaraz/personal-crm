<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $scopes;

    public function __construct()
    {
        $this->clientId     = config('services.linkedin.client_id');
        $this->clientSecret = config('services.linkedin.client_secret');
        $this->redirectUri  = config('services.linkedin.redirect');
        $this->scopes       = config('services.linkedin.scopes', 'w_member_social openid profile email');
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => $this->scopes,
            'state'         => $state,
        ]);
    }

    /**
     * Exchange authorization code for access token.
     * Returns ['access_token', 'expires_in', 'refresh_token'?, 'scope']
     * Throws on failure.
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'LinkedIn token exchange failed: ' . $this->sanitizeErrorResponse($response->body())
            );
        }

        return $response->json();
    }

    /**
     * Resolve the Person URN and display name for the token owner.
     * Tries OpenID userinfo first, falls back to /v2/me.
     */
    public function resolveMemberIdentity(string $accessToken): array
    {
        // Try OpenID Connect userinfo (requires openid + profile scope)
        try {
            $userinfo = Http::withToken($accessToken)
                ->get('https://api.linkedin.com/v2/userinfo');

            if ($userinfo->successful()) {
                $data = $userinfo->json();
                $sub  = $data['sub'] ?? null;
                if ($sub) {
                    return [
                        'urn'          => "urn:li:person:{$sub}",
                        'display_name' => trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? '')) ?: ($data['name'] ?? null),
                    ];
                }
            }
        } catch (ConnectionException) {
            // fall through to /v2/me
        }

        // Fallback: /v2/me (requires r_liteprofile or r_basicprofile)
        $me = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get('https://api.linkedin.com/v2/me', [
                'projection' => '(id,localizedFirstName,localizedLastName)',
            ]);

        if ($me->successful()) {
            $data = $me->json();
            $id   = $data['id'] ?? null;
            if ($id) {
                return [
                    'urn'          => "urn:li:person:{$id}",
                    'display_name' => trim(($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? '')) ?: null,
                ];
            }
        }

        throw new \RuntimeException('Could not resolve LinkedIn member identity. Ensure openid+profile or r_liteprofile scope is granted.');
    }

    /**
     * Create or update the SocialAccount record after successful OAuth.
     */
    public function storeConnection(User $user, array $tokenData, array $identity): SocialAccount
    {
        $provider = SocialProvider::where('key', 'linkedin')->firstOrFail();

        $expiresAt = isset($tokenData['expires_in'])
            ? now()->addSeconds((int) $tokenData['expires_in'])
            : null;

        $grantedScopes = isset($tokenData['scope'])
            ? explode(',', $tokenData['scope'])
            : [];

        $account = SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider_id' => $provider->id],
            [
                'tenant_id'               => $user->tenant_id,
                'provider_account_urn'    => $identity['urn'],
                'display_name'            => $identity['display_name'],
                'access_token_encrypted'  => $tokenData['access_token'],
                'refresh_token_encrypted' => $tokenData['refresh_token'] ?? null,
                'token_expires_at'        => $expiresAt,
                'scopes_json'             => $grantedScopes,
                'status'                  => 'connected',
                'last_verified_at'        => now(),
                'metadata_json'           => ['connected_via' => 'oauth2'],
            ]
        );

        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            'linkedin_connected',
            SocialAccount::class, $account->id,
            "LinkedIn account connected: {$identity['display_name']}"
        );

        return $account;
    }

    public function disconnect(SocialAccount $account): void
    {
        $userId   = $account->user_id;
        $tenantId = $account->tenant_id;

        $account->update([
            'status'                  => 'disconnected',
            'access_token_encrypted'  => null,
            'refresh_token_encrypted' => null,
            'token_expires_at'        => null,
        ]);

        SocialActivityLog::record(
            $userId, $tenantId,
            'linkedin_disconnected',
            SocialAccount::class, $account->id,
            'LinkedIn account disconnected by user'
        );
    }

    /** Strip any token values from an error response body before logging. */
    private function sanitizeErrorResponse(string $body): string
    {
        return preg_replace('/"access_token":"[^"]*"/', '"access_token":"[REDACTED]"', $body) ?? $body;
    }
}
