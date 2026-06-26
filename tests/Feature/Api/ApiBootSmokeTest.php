<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiBootSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_ok(): void
    {
        $this->getJson('/api/gpt/v1/health')
             ->assertOk()
             ->assertJsonPath('status', 'ok')
             ->assertJsonPath('checks.database', 'ok')
             ->assertJsonPath('checks.personal_access_tokens', 'ok');
    }

    public function test_authed_endpoint_rejects_missing_key(): void
    {
        $this->getJson('/api/gpt/v1/me')
             ->assertStatus(401)
             ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_authed_endpoint_rejects_bad_key(): void
    {
        $this->withHeader('X-Api-Key', 'invalid-key')
             ->getJson('/api/gpt/v1/me')
             ->assertStatus(401);
    }

    public function test_authed_endpoint_works_with_valid_key(): void
    {
        [$user, $rawToken] = $this->makeApiToken(['contacts:read', 'documents:read']);

        $this->withHeader('X-Api-Key', $rawToken)
             ->getJson('/api/gpt/v1/me')
             ->assertOk()
             ->assertJsonPath('user.email', $user->email);
    }

    // -------------------------------------------------------------------------

    protected function makeApiToken(array $scopes): array
    {
        $tenant = Tenant::create([
            'name'   => 'Smoke Test Tenant',
            'slug'   => 'smoke-' . Str::random(6),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);

        $client = ApiClient::create([
            'user_id'     => $user->id,
            'name'        => 'Test Client',
            'source_type' => 'gpt',
            'scopes'      => $scopes,
            'is_active'   => true,
        ]);

        $raw = 'pocrm_test_' . Str::random(40);

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $user->id,
            'name'          => 'Test Token',
            'token_hash'    => hash('sha256', $raw),
            'token_prefix'  => substr($raw, 0, 16),
            'is_active'     => true,
            'expires_at'    => now()->addYear(),
        ]);

        return [$user, $raw];
    }
}
