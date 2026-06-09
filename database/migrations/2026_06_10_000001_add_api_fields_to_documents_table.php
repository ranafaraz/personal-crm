<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // tenant_id was in the model's $fillable but missing from the original migration
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');

            // URL-based documents created via API (no local file upload required)
            $table->string('public_url', 2048)->nullable()->after('mime_type');

            // Make file fields nullable so API-created docs (URL-only) are valid
            $table->string('file_path')->nullable()->change();
            $table->string('file_name')->nullable()->change();
            $table->unsignedInteger('file_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'public_url']);
            $table->string('file_path')->nullable(false)->change();
            $table->string('file_name')->nullable(false)->change();
            $table->unsignedInteger('file_size')->nullable(false)->change();
        });
    }
};
