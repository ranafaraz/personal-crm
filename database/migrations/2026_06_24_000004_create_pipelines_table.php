<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // manual | scheduled | webhook — how the pipeline is intended to be triggered.
            $table->string('trigger_type')->default('manual');
            // active | paused | archived
            $table->string('status')->default('active');
            // Ordered list of step definitions (each an arbitrary object describing an action).
            $table->json('steps')->nullable();
            // Arbitrary pipeline-level configuration.
            $table->json('config')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
