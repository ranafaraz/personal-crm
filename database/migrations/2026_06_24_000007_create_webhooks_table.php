<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            // Event names this endpoint subscribes to (e.g. ["contact.created","proposal.sent"]).
            $table->json('events')->nullable();
            // Optional shared secret used to sign delivery payloads.
            $table->string('secret')->nullable();
            // active | paused
            $table->string('status')->default('active');
            $table->dateTime('last_triggered_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
