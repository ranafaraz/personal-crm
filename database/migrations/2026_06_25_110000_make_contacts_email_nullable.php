<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix (prod 2026-06-25): POST /api/gpt/v1/contacts returned HTTP 500 when
 * `email` was omitted, because the column was NOT NULL while the controller
 * already validates `email` as nullable. Many opportunities are application
 * portals where the recipient address is not yet known, so a contact with no
 * email is a legitimate state. Make the column nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert to NOT NULL. Existing null rows would block this; backfill
        // with a placeholder before rolling back if needed.
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
