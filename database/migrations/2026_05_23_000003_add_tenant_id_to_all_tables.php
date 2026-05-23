<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'contacts', 'opportunities', 'email_accounts', 'email_templates',
        'email_messages', 'inbox_messages', 'follow_ups', 'documents',
        'contact_imports', 'suppression_list', 'tags', 'timeline_events',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('tenants')
                        ->nullOnDelete();
                });
            }
        }

        // Also handle activity_log if it exists
        if (Schema::hasTable('activity_log') && ! Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });
        }

        // Backfill tenant_id from the creating user's tenant
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    DB::statement("
                        UPDATE {$table} t
                        JOIN users u ON t.user_id = u.id
                        SET t.tenant_id = u.tenant_id
                        WHERE t.tenant_id IS NULL AND u.tenant_id IS NOT NULL
                    ");

                    continue;
                }

                DB::table($table)
                    ->whereNull('tenant_id')
                    ->whereNotNull('user_id')
                    ->orderBy('id')
                    ->chunkById(100, function ($rows) use ($table) {
                        foreach ($rows as $row) {
                            $tenantId = DB::table('users')->where('id', $row->user_id)->value('tenant_id');

                            if ($tenantId) {
                                DB::table($table)->where('id', $row->id)->update(['tenant_id' => $tenantId]);
                            }
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['tenant_id']);
                    $t->dropColumn('tenant_id');
                });
            }
        }

        if (Schema::hasTable('activity_log') && Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', fn ($t) => $t->dropColumn('tenant_id'));
        }
    }
};
