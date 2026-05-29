<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_publish_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_target_id')->constrained('social_post_targets')->onDelete('cascade');
            $table->timestamp('scheduled_at');
            $table->enum('job_status', ['queued', 'processing', 'succeeded', 'failed', 'retrying', 'cancelled'])->default('queued');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->json('provider_response_sanitized_json')->nullable();
            $table->timestamps();

            $table->index(['job_status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publish_jobs');
    }
};
