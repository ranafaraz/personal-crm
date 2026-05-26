<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EndpointSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('authenticatedEndpointProvider')]
    public function test_authenticated_pages_load_without_server_errors(string $uri): void
    {
        $tenant = Tenant::create([
            'name' => 'Smoke Test Tenant',
            'slug' => 'smoke-test-tenant',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $this->actingAs($user)->get($uri)->assertOk();
    }

    public static function authenticatedEndpointProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'contacts' => ['/contacts'],
            'opportunities' => ['/opportunities'],
            'email accounts' => ['/email-accounts'],
            'email templates' => ['/email-templates'],
            'email signatures' => ['/email-signatures'],
            'emails' => ['/emails'],
            'compose' => ['/compose'],
            'inbox' => ['/inbox'],
            'follow ups' => ['/follow-ups'],
            'suppression list' => ['/suppression-list'],
            'imports' => ['/imports'],
            'import create' => ['/imports/create'],
            'reports' => ['/reports'],
            'audit logs' => ['/audit-logs'],
            'tags' => ['/tags'],
            'settings' => ['/settings'],
            'team settings' => ['/settings/team'],
        ];
    }
}
