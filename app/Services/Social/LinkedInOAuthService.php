<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialOAuthApp;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LinkedInOAuthService
{
    private const CAPABILITY_SCOPES = [
        'can_post'             => ['w_member_social'],
        'can_read_posts'       => ['r_member_social'],
        'can_post_analytics'   => ['r_member_postAnalytics'],
        'can_profile_analytics' => ['r_member_profileAnalytics'],
    ];

    public function __construct(private LinkedInClient $client) {}

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
            // fall through to legacy endpoint
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
     * Introspects the token to get authoritative granted scopes, computes missing
     * scopes and capability flags, then persists everything on the account.
     */
    public function storeConnection(User $user, SocialOAuthApp $app, array $tokenData, array $identity): SocialAccount
    {
        $provider = SocialProvider::where('key', 'linkedin')->firstOrFail();

        $expiresAt = isset($tokenData['expires_in'])
            ? now()->addSeconds((int) $tokenData['expires_in'])
            : null;

        // Introspect for authoritative scopes; fall back to exchange response
        $grantedScopes = $this->resolveGrantedScopes($tokenData, $app);
        $missingScopes = $this->computeMissingScopes($grantedScopes, $app);
        $capabilities  = $this->computeCapabilities($grantedScopes);

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
                'granted_scopes'          => $grantedScopes,
                'missing_scopes'          => $missingScopes,
                'capabilities'            => $capabilities,
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

    /**
     * Re-verify an existing account: introspect token, refresh scopes and capabilities.
     */
    public function verifyAccount(SocialAccount $account, SocialOAuthApp $app): array
    {
        $token = $account->access_token_encrypted;

        $introspection = $this->client->introspectToken(
            $token,
            $app->client_id,
            $app->client_secret_encrypted
        );

        if (! ($introspection['active'] ?? false)) {
            $account->update(['status' => 'reauthorization_required']);
            return ['active' => false, 'granted_scopes' => [], 'missing_scopes' => [], 'capabilities' => []];
        }

        $grantedScopes = $this->parseScopeString($introspection['scope'] ?? '');
        $missingScopes = $this->computeMissingScopes($grantedScopes, $app);
        $capabilities  = $this->computeCapabilities($grantedScopes);

        $account->update([
            'granted_scopes'  => $grantedScopes,
            'missing_scopes'  => $missingScopes,
            'capabilities'    => $capabilities,
            'last_verified_at' => now(),
            'status'          => 'connected',
        ]);

        return [
            'active'        => true,
            'granted_scopes' => $grantedScopes,
            'missing_scopes' => $missingScopes,
            'capabilities'  => $capabilities,
        ];
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

    // ── Scope / capability helpers ────────────────────────────────────────────

    private function resolveGrantedScopes(array $tokenData, SocialOAuthApp $app): array
    {
        try {
            $introspection = $this->client->introspectToken(
                $tokenData['access_token'],
                $app->client_id,
                $app->client_secret_encrypted
            );

            if (! empty($introspection['scope'])) {
                return $this->parseScopeString($introspection['scope']);
            }
        } catch (\Throwable) {
            // Non-fatal: fall back to exchange response scope
        }

        return isset($tokenData['scope'])
            ? $this->parseScopeString($tokenData['scope'])
            : [];
    }

    private function computeMissingScopes(array $grantedScopes, SocialOAuthApp $app): array
    {
        $requested = $this->parseScopeString($app->scopes ?? '');
        return array_values(array_diff($requested, $grantedScopes));
    }

    private function computeCapabilities(array $grantedScopes): array
    {
        $caps = [];
        foreach (self::CAPABILITY_SCOPES as $capability => $required) {
            $caps[$capability] = count(array_intersect($required, $grantedScopes)) === count($required);
        }
        return $caps;
    }

    private function parseScopeString(string $scopeString): array
    {
        if (empty($scopeString)) {
            return [];
        }
        // LinkedIn returns scopes space-separated or comma-separated
        $delimiters = str_contains($scopeString, ',') ? ',' : ' ';
        return array_values(array_filter(array_map('trim', explode($delimiters, $scopeString))));
    }

    private function sanitizeErrorResponse(string $body): string
    {
        return preg_replace('/"access_token":"[^"]*"/', '"access_token":"[REDACTED]"', $body) ?? $body;
    }
}

class LinkedInOAuthException extends \RuntimeException {}
