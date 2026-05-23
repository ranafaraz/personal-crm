<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 64);
            $table->uuid('notification_id')->nullable()->index();
            $table->string('channel', 32);
            $table->string('status', 16); // sent | failed | skipped
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
