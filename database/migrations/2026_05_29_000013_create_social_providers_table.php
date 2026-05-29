<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->enum('status', ['enabled', 'coming_soon', 'disabled'])->default('coming_soon');
            $table->json('capabilities_json')->nullable();
            $table->timestamps();
        });

        DB::table('social_providers')->insert([
            [
                'key'               => 'linkedin',
                'name'              => 'LinkedIn',
                'status'            => 'enabled',
                'capabilities_json' => json_encode(['text', 'image', 'article_link']),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'wordpress',
                'name'              => 'WordPress',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'facebook',
                'name'              => 'Facebook',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'instagram',
                'name'              => 'Instagram',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'x',
                'name'              => 'X / Twitter',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'medium',
                'name'              => 'Medium',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'key'               => 'youtube',
                'name'              => 'YouTube',
                'status'            => 'coming_soon',
                'capabilities_json' => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('social_providers');
    }
};
