<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained('social_posts')->onDelete('cascade');
            $table->foreignId('social_media_asset_id')->constrained('social_media_assets')->onDelete('cascade');
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('alt_text_override', 500)->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_media_asset_id'], 'spm_post_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_media');
    }
};
