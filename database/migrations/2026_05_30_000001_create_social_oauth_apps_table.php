<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        // Add oauth_app_id and is_default to social_accounts
        Schema::table('social_accounts', function (Blueprint $table) {
            // Drop the unique constraint that prevents multiple accounts per provider
            $table->dropUnique(['user_id', 'provider_id']);

            $table->foreignId('social_oauth_app_id')
                ->nullable()
                ->after('provider_id')
                ->constrained('social_oauth_apps')
                ->nullOnDelete();

            $table->boolean('is_default')->default(false)->after('metadata_json');

            // New unique: one account per user per oauth app
            $table->unique(['user_id', 'social_oauth_app_id'], 'sa_user_app_unique');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropUnique('sa_user_app_unique');
            $table->dropConstrainedForeignId('social_oauth_app_id');
            $table->dropColumn('is_default');
            $table->unique(['user_id', 'provider_id']);
        });

        Schema::dropIfExists('social_oauth_apps');
    }
};
