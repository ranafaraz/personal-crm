<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->string('type', 64);                 // country | industry | source | city | designation
            $t->string('value', 255);
            $t->string('slug', 255)->nullable();
            $t->string('meta', 255)->nullable();    // e.g. ISO code for countries
            $t->boolean('is_system')->default(false); // pre-seeded vs user-added
            $t->unsignedInteger('usage_count')->default(0);
            $t->timestamps();

            $t->unique(['tenant_id', 'type', 'value']);
            $t->index(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
