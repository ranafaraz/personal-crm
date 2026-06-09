<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('api_attachments', 'file_path')) {
                $table->string('file_path', 2000)->nullable()->after('public_url');
            }
            if (!Schema::hasColumn('api_attachments', 'storage_disk')) {
                $table->string('storage_disk', 50)->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('api_attachments', 'storage_disk')) {
                $table->dropColumn('storage_disk');
            }
            if (Schema::hasColumn('api_attachments', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });
    }
};
