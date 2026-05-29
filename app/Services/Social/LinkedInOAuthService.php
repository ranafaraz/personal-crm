<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialOAuthApp;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInOAuthService
{
    public function authorizationUrl(SocialOAuthApp $app, string $state): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $app->client_id,
            'redirect_uri'  => $app->resolvedRedirectUri(),
            'scope'         => $app->scopes,
            'state'         => $state,
        ]);
    }

    /**
     * Exchange authorization code for access token.
     * Returns ['access_token', 'expires_in', 'refresh_token'?, 'scope']
     */
    public function exchangeCode(SocialOAuthApp $app, string $code): array
    {
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $app->resolvedRedirectUri(),
            'client_id'     => $app->client_id,
            'client_secret' => $app->client_secret_encrypted,
        ]);

        if (! $response->successful()) {
            throw new LinkedInOAuthException(
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
            // fall through
        }

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

        throw new LinkedInOAuthException('Could not resolve LinkedIn member identity. Ensure openid+profile or r_liteprofile scope is granted.');
    }

    /**
     * Create or update the SocialAccount record after successful OAuth.
     * One account per user per OAuth app.
     */
    public function storeConnection(User $user, SocialOAuthApp $app, array $tokenData, array $identity): SocialAccount
    {
        $provider = SocialProvider::where('key', 'linkedin')->firstOrFail();

        $expiresAt     = isset($tokenData['expires_in'])
            ? now()->addSeconds((int) $tokenData['expires_in'])
            : null;
        $grantedScopes = isset($tokenData['scope'])
            ? explode(',', $tokenData['scope'])
            : [];

        // If this is the first account for this user+provider, make it default
        $isFirstAccount = ! SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->exists();

        $account = SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'social_oauth_app_id' => $app->id],
            [
                'tenant_id'               => $user->tenant_id,
                'provider_id'             => $provider->id,
                'social_oauth_app_id'     => $app->id,
                'provider_account_urn'    => $identity['urn'],
                'display_name'            => $identity['display_name'],
                'access_token_encrypted'  => $tokenData['access_token'],
                'refresh_token_encrypted' => $tokenData['refresh_token'] ?? null,
                'token_expires_at'        => $expiresAt,
                'scopes_json'             => $grantedScopes,
                'status'                  => 'connected',
                'last_verified_at'        => now(),
                'is_default'              => $isFirstAccount || $app->is_default,
                'metadata_json'           => ['connected_via' => 'oauth2', 'app_label' => $app->label],
            ]
        );

        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            'linkedin_connected',
            SocialAccount::class, $account->id,
            "LinkedIn account connected via app '{$app->label}': {$identity['display_name']}"
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

        // If this was the default, promote another connected account
        if ($account->is_default) {
            $next = SocialAccount::where('user_id', $userId)
                ->where('id', '!=', $account->id)
                ->where('status', 'connected')
                ->first();
            $next?->update(['is_default' => true]);
        }

        SocialActivityLog::record(
            $userId, $tenantId,
            'linkedin_disconnected',
            SocialAccount::class, $account->id,
            'LinkedIn account disconnected by user'
        );
    }

    private function sanitizeErrorResponse(string $body): string
    {
        return preg_replace('/"access_token":"[^"]*"/', '"access_token":"[REDACTED]"', $body) ?? $body;
    }
}

class LinkedInOAuthException extends \RuntimeException {}
