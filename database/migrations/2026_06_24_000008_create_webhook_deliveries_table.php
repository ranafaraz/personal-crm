<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only delivery log for webhooks. No soft deletes — removed only
        // when the parent webhook is deleted.
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
            $table->string('event');
            // pending | success | failed
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['webhook_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
