<?php

use App\Services\Social\SocialProviderCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SocialProviderCatalog::definitions() as $key => $definition) {
            DB::table('social_providers')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'status' => $definition['status'],
                    'capabilities_json' => json_encode($definition['capabilities_json']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('social_providers')
            ->whereIn('key', ['drupal', 'joomla', 'tiktok', 'threads', 'pinterest', 'mastodon', 'bluesky'])
            ->delete();

        foreach (['facebook', 'instagram', 'x', 'medium', 'youtube'] as $key) {
            DB::table('social_providers')->where('key', $key)->update([
                'status' => 'coming_soon',
                'capabilities_json' => null,
                'updated_at' => now(),
            ]);
        }
    }
};
