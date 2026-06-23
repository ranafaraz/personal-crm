<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only execution log for pipelines. No soft deletes — runs are
        // historical records, removed only when their parent pipeline is deleted.
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            // pending | running | succeeded | failed | cancelled
            $table->string('status')->default('pending');
            // manual | scheduled | webhook | api — what triggered this run.
            $table->string('trigger_source')->default('manual');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->longText('error')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['pipeline_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
