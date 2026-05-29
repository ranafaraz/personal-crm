<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_oauth_apps')) {
            Schema::create('social_oauth_apps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('provider_key', 50)->default('linkedin');
                $table->string('label', 255);
                $table->string('client_id', 255);
                $table->text('client_secret_encrypted');
                $table->string('redirect_uri', 500)->nullable();
                $table->string('scopes', 500)->default('w_member_social openid profile email');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'provider_key']);
            });
        }

        Schema::table('social_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_accounts', 'social_oauth_app_id')) {
                // MariaDB may refuse to drop this unique if it thinks it backs the provider_id FK;
                // disable FK checks temporarily to force the drop.
                if (Schema::hasIndex('social_accounts', 'social_accounts_user_id_provider_id_unique')) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    $table->dropUnique('social_accounts_user_id_provider_id_unique');
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }

                $table->foreignId('social_oauth_app_id')
                    ->nullable()
                    ->after('provider_id')
                    ->constrained('social_oauth_apps')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('social_accounts', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('metadata_json');
            }

            if (! Schema::hasIndex('social_accounts', 'sa_user_app_unique')) {
                $table->unique(['user_id', 'social_oauth_app_id'], 'sa_user_app_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            if (Schema::hasIndex('social_accounts', 'sa_user_app_unique')) {
                $table->dropUnique('sa_user_app_unique');
            }
            if (Schema::hasColumn('social_accounts', 'social_oauth_app_id')) {
                $table->dropConstrainedForeignId('social_oauth_app_id');
            }
            if (Schema::hasColumn('social_accounts', 'is_default')) {
                $table->dropColumn('is_default');
            }
            if (! Schema::hasIndex('social_accounts', 'social_accounts_user_id_provider_id_unique')) {
                $table->unique(['user_id', 'provider_id']);
            }
        });

        Schema::dropIfExists('social_oauth_apps');
    }
};
