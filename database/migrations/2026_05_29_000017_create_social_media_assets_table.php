<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename', 500);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 1000);
            $table->string('thumbnail_path', 1000)->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->text('caption_or_prompt_note')->nullable();
            $table->enum('rights_status', ['owned', 'licensed', 'generated', 'unknown'])->default('unknown');
            $table->enum('approval_status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_assets');
    }
};
