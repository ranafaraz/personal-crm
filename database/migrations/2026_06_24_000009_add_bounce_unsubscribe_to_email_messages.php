<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->timestamp('bounced_at')->nullable()->after('failed_at');
            $table->timestamp('unsubscribed_at')->nullable()->after('bounced_at');
            $table->string('bounce_type', 30)->nullable()->after('unsubscribed_at'); // hard, soft, auto_reply
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['bounced_at', 'unsubscribed_at', 'bounce_type']);
        });
    }
};
