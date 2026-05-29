<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title_internal', 500);
            $table->string('topic', 255)->nullable();
            $table->text('post_body');
            $table->json('platform_variant_json')->nullable();
            $table->enum('post_type', ['text', 'image', 'article_link', 'video_placeholder'])->default('text');
            $table->string('article_url', 2048)->nullable();
            $table->text('source_notes')->nullable();
            $table->json('source_links_json')->nullable();
            $table->json('hashtags_json')->nullable();
            $table->string('call_to_action', 500)->nullable();
            $table->enum('status', [
                'idea', 'draft', 'ready_for_review', 'approved',
                'scheduled', 'publishing', 'published', 'failed', 'cancelled', 'archived',
            ])->default('draft');
            $table->enum('approval_status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone_display', 100)->default('Asia/Karachi');
            $table->enum('created_source', ['manual', 'chatgpt', 'template', 'import'])->default('manual');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
