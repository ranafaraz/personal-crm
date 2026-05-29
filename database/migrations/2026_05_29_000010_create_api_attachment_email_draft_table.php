<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('api_attachment_email_draft');
        Schema::create('api_attachment_email_draft', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained('email_messages')->onDelete('cascade');
            $table->foreignId('api_attachment_id')->constrained('api_attachments')->onDelete('cascade');
            $table->foreignId('added_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['email_message_id', 'api_attachment_id'], 'api_att_draft_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_attachment_email_draft');
    }
};
