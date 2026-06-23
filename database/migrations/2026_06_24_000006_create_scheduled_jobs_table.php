<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Optional pipeline this job triggers when it runs.
            $table->foreignId('pipeline_id')->nullable()->constrained('pipelines')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // What the job does: pipeline | reminder | report | sync | custom …
            $table->string('job_type')->default('pipeline');
            // once | hourly | daily | weekly | monthly | cron
            $table->string('frequency')->default('daily');
            // Cron expression when frequency = cron.
            $table->string('cron_expression')->nullable();
            // Fixed run time when frequency = once.
            $table->dateTime('run_at')->nullable();
            // active | paused
            $table->string('status')->default('active');
            $table->json('payload')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'next_run_at']);
            $table->index(['user_id', 'pipeline_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_jobs');
    }
};
