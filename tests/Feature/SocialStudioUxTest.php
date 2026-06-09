<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialStudioUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_connected_wordpress_site(): void
    {
        $user = $this->user();
        $this->wordpressAccount($user);

        $this->actingAs($user)
            ->get(route('social-studio.dashboard'))
            ->assertOk()
            ->assertSee('DEXDEVS Blog')
            ->assertSee('WordPress');
    }

    public function test_create_content_page_uses_local_editor_without_tinymce_cloud(): void
    {
        $user = $this->user();
        $this->wordpressAccount($user);

        $this->actingAs($user)
            ->get(route('social-studio.posts.create'))
            ->assertOk()
            ->assertSee('Content Workspace')
            ->assertSee('Publish Targets')
            ->assertDontSee('cdn.tiny.cloud', false)
            ->assertDontSee('tinymce', false);
    }

    public function test_user_can_connect_multiple_manual_accounts_for_same_provider(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('social-studio.connections.manual.store'), [
            'provider_key' => 'facebook',
            'display_name' => 'DEXDEVS Facebook Page',
            'account_identifier' => 'page-1',
            'access_token' => 'token-one',
        ])->assertRedirect(route('social-studio.connections'));

        $this->actingAs($user)->post(route('social-studio.connections.manual.store'), [
            'provider_key' => 'facebook',
            'display_name' => 'Hiring Facebook Page',
            'account_identifier' => 'page-2',
            'access_token' => 'token-two',
        ])->assertRedirect(route('social-studio.connections'));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'display_name' => 'DEXDEVS Facebook Page',
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'display_name' => 'Hiring Facebook Page',
            'status' => 'connected',
        ]);

        $this->actingAs($user)
            ->get(route('social-studio.connections'))
            ->assertOk()
            ->assertSee('Facebook')
            ->assertSee('DEXDEVS Facebook Page')
            ->assertSee('Hiring Facebook Page');
    }

    public function test_manual_non_publish_channels_do_not_appear_as_scheduler_targets(): void
    {
        $user = $this->user();
        $this->wordpressAccount($user);

        $provider = SocialProvider::where('key', 'facebook')->firstOrFail();
        SocialAccount::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_account_urn' => 'page-1',
            'display_name' => 'DEXDEVS Facebook Page',
            'access_token_encrypted' => 'token-one',
            'status' => 'connected',
            'capabilities' => ['profile', 'manual_connection'],
        ]);

        $this->actingAs($user)
            ->get(route('social-studio.posts.create'))
            ->assertOk()
            ->assertSee('DEXDEVS Blog')
            ->assertDontSee('DEXDEVS Facebook Page');
    }

    public function test_insights_show_wordpress_publish_activity(): void
    {
        $user = $this->user();
        $account = $this->wordpressAccount($user);

        $post = SocialPost::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'title_internal' => 'Published WP Post',
            'post_body' => '<p>Published content</p>',
            'status' => 'published',
            'approval_status' => 'approved',
        ]);

        SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'provider_key' => 'wordpress',
            'platform_body' => $post->post_body,
            'status' => 'published',
            'remote_post_id' => '123',
            'remote_post_url' => 'https://blog.dexdevs.com/published-wp-post/',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('social-studio.insights'))
            ->assertOk()
            ->assertSee('WordPress')
            ->assertSee('Published WP Post')
            ->assertSee('https://blog.dexdevs.com');
    }

    private function user(): User
    {
        $tenant = Tenant::create([
            'name' => 'Social Studio UX Tenant',
            'slug' => 'social-studio-ux-' . uniqid(),
            'status' => 'active',
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
    }

    private function wordpressAccount(User $user): SocialAccount
    {
        $provider = SocialProvider::updateOrCreate(
            ['key' => 'wordpress'],
            ['name' => 'WordPress', 'status' => 'enabled', 'capabilities_json' => ['html']],
        );

        return SocialAccount::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_account_urn' => 'https://blog.dexdevs.com',
            'display_name' => 'DEXDEVS Blog',
            'public_profile_url' => 'https://blog.dexdevs.com',
            'access_token_encrypted' => 'application-password',
            'status' => 'connected',
            'metadata_json' => [
                'site_url' => 'https://blog.dexdevs.com',
                'api_base' => 'https://blog.dexdevs.com/wp-json/wp/v2',
                'username' => 'dexdevs_admin',
            ],
        ]);
    }
}
