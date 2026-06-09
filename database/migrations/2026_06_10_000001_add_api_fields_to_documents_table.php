<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (!Schema::hasColumn('documents', 'public_url')) {
                $table->string('public_url', 2048)->nullable()->after('mime_type');
            }

            // Ensure file fields are nullable so URL-only API docs are valid.
            $table->string('file_path')->nullable()->change();
            $table->string('file_name')->nullable()->change();
            $table->unsignedInteger('file_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
            if (Schema::hasColumn('documents', 'public_url')) {
                $table->dropColumn('public_url');
            }
        });
    }
};
