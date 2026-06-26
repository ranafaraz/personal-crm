<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Models\ApiDocument;
use App\Models\ApiDocumentLink;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentAttachTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private string $rawToken;
    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        [$this->user, $this->rawToken] = $this->makeApiToken(
            ['documents:read', 'documents:write', 'opportunities:read']
        );

        $this->opportunity = Opportunity::create([
            'user_id'   => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'title'     => 'Test Opportunity',
            'status'    => 'active',
        ]);
    }

    // -------------------------------------------------------------------------
    // base64 upload
    // -------------------------------------------------------------------------

    public function test_base64_upload_with_opportunity_id_creates_document_and_link(): void
    {
        $pdfBytes  = "%PDF-1.4 fake pdf content for test";
        $base64    = base64_encode($pdfBytes);

        $response = $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson('/api/gpt/v1/documents', [
                'name'           => 'Test Proposal',
                'filename'       => 'proposal.pdf',
                'file_base64'    => $base64,
                'document_type'  => 'proposal',
                'opportunity_id' => $this->opportunity->id,
            ]);

        $response->assertCreated()
                 ->assertJsonPath('data.name', 'Test Proposal')
                 ->assertJsonPath('data.document_type', 'proposal');

        $docId = $response->json('data.document_id');

        $this->assertDatabaseHas('api_documents', [
            'id'      => $docId,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('api_document_links', [
            'api_document_id' => $docId,
            'entity_type'     => 'opportunity',
            'entity_id'       => $this->opportunity->id,
        ]);
    }

    public function test_base64_upload_appears_in_opportunity_documents_list(): void
    {
        $base64 = base64_encode("%PDF-1.4 fake pdf");

        $uploadResponse = $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson('/api/gpt/v1/documents', [
                'name'           => 'Linked Doc',
                'filename'       => 'doc.pdf',
                'file_base64'    => $base64,
                'opportunity_id' => $this->opportunity->id,
            ]);

        $uploadResponse->assertCreated();
        $docId = $uploadResponse->json('data.document_id');

        $listResponse = $this->withHeader('X-Api-Key', $this->rawToken)
            ->getJson("/api/gpt/v1/opportunities/{$this->opportunity->id}/documents");

        $listResponse->assertOk()
                     ->assertJsonPath('count', 1)
                     ->assertJsonPath('data.0.document_id', $docId);
    }

    // -------------------------------------------------------------------------
    // source_url fetch
    // -------------------------------------------------------------------------

    public function test_source_url_fetch_creates_document_with_remote_fetch_source(): void
    {
        Http::fake([
            'https://example.com/report.pdf' => Http::response(
                "%PDF-1.4 fake pdf bytes",
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $response = $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson('/api/gpt/v1/documents', [
                'name'           => 'Remote Report',
                'source_url'     => 'https://example.com/report.pdf',
                'document_type'  => 'report',
                'opportunity_id' => $this->opportunity->id,
            ]);

        $response->assertCreated()
                 ->assertJsonPath('data.name', 'Remote Report');

        $docId = $response->json('data.document_id');

        $this->assertDatabaseHas('api_document_links', [
            'api_document_id' => $docId,
            'entity_type'     => 'opportunity',
            'entity_id'       => $this->opportunity->id,
        ]);

        $version = \App\Models\ApiDocumentVersion::where('api_document_id', $docId)->first();
        $this->assertSame('remote_fetch', $version->upload_source);
        $this->assertNotNull($version->storage_path);
        $this->assertNotNull($version->checksum);
    }

    public function test_source_url_rejects_unreachable_url(): void
    {
        Http::fake([
            'https://dead.example.com/file.pdf' => Http::response('', 404),
        ]);

        $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson('/api/gpt/v1/documents', [
                'name'       => 'Dead URL',
                'source_url' => 'https://dead.example.com/file.pdf',
            ])
            ->assertStatus(422)
            ->assertJsonPath('field', 'source_url');
    }

    // -------------------------------------------------------------------------
    // entity-scoped convenience route (POST /opportunities/{id}/documents)
    // -------------------------------------------------------------------------

    public function test_opportunity_scoped_store_creates_link(): void
    {
        Http::fake([
            'https://example.com/cv.pdf' => Http::response("%PDF-1.4 cv", 200),
        ]);

        $response = $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson("/api/gpt/v1/opportunities/{$this->opportunity->id}/documents", [
                'name'       => 'CV via scoped route',
                'source_url' => 'https://example.com/cv.pdf',
            ]);

        $response->assertCreated();
        $docId = $response->json('data.document_id');

        $this->assertDatabaseHas('api_document_links', [
            'api_document_id' => $docId,
            'entity_type'     => 'opportunity',
            'entity_id'       => $this->opportunity->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // download_url in response
    // -------------------------------------------------------------------------

    public function test_get_document_returns_download_url(): void
    {
        Http::fake([
            'https://example.com/proposal.pdf' => Http::response("%PDF-1.4 proposal", 200),
        ]);

        $uploadResponse = $this->withHeader('X-Api-Key', $this->rawToken)
            ->postJson('/api/gpt/v1/documents', [
                'name'       => 'Proposal',
                'source_url' => 'https://example.com/proposal.pdf',
            ]);

        $docId = $uploadResponse->json('data.document_id');

        $getResponse = $this->withHeader('X-Api-Key', $this->rawToken)
            ->getJson("/api/gpt/v1/documents/{$docId}");

        $getResponse->assertOk();
        $downloadUrl = $getResponse->json('data.current_version.download_url');
        $this->assertNotNull($downloadUrl);
        $this->assertStringContainsString("/api/gpt/v1/documents/{$docId}/download", $downloadUrl);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeApiToken(array $scopes): array
    {
        $tenant = Tenant::create([
            'name'   => 'Doc Test Tenant',
            'slug'   => 'doc-test-' . Str::random(6),
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
