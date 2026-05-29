<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained('social_posts')->onDelete('cascade');
            $table->foreignId('social_account_id')->constrained('social_accounts')->onDelete('cascade');
            $table->string('provider_key', 50);
            $table->text('platform_body')->nullable();
            $table->json('platform_metadata_json')->nullable();
            $table->enum('status', [
                'draft', 'approved', 'scheduled', 'publishing',
                'published', 'failed', 'cancelled',
            ])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('remote_post_id', 500)->nullable();
            $table->string('remote_post_url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['social_post_id', 'provider_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
    }
};
