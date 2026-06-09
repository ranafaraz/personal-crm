<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Logical document identity ──────────────────────────────────────
        Schema::create('api_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 500);
            $table->string('document_type', 50)->default('other');
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->json('sensitive_warnings')->nullable();
            // Points to the latest ApiDocumentVersion; no FK constraint (circular).
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('user_id');
        });

        // ── 2. Immutable version snapshots (never overwritten) ────────────────
        Schema::create('api_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_document_id')
                  ->constrained('api_documents')->onDelete('cascade');
            $table->unsignedSmallInteger('version_number'); // 1, 2, 3 …
            $table->string('original_filename', 500);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable();  // sha256 hex; null for URL-only
            $table->string('storage_path', 1000)->nullable(); // local disk path
            $table->string('public_url', 2048)->nullable();   // external URL (optional)
            $table->string('upload_source', 20)->default('multipart'); // multipart|url|agent
            $table->text('version_notes')->nullable();
            $table->unsignedBigInteger('uploaded_by_api_client_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['api_document_id', 'version_number']);
            $table->index('api_document_id');
        });

        // ── 3. Polymorphic many-to-many entity links ──────────────────────────
        Schema::create('api_document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_document_id')
                  ->constrained('api_documents')->onDelete('cascade');
            $table->string('entity_type', 50); // opportunity|contact|email_draft|follow_up
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('linked_by_api_client_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['api_document_id', 'entity_type', 'entity_id'], 'api_doc_links_unique');
            $table->index(['entity_type', 'entity_id']);
            $table->index('api_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_document_links');
        Schema::dropIfExists('api_document_versions');
        Schema::dropIfExists('api_documents');
    }
};
