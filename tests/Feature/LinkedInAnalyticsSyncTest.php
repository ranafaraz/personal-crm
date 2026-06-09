<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialAnalyticsSnapshot;
use App\Models\SocialProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkedInAnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_sync_fetches_linkedin_member_analytics_immediately(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/memberFollowersCount')) {
                return Http::response([
                    'elements' => [
                        ['memberFollowersCount' => 321],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/memberCreatorPostAnalytics')) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $values = [
                    'IMPRESSION' => 1200,
                    'MEMBERS_REACHED' => 900,
                    'LINK_CLICKS' => 44,
                    'REACTION' => 25,
                    'COMMENT' => 7,
                    'RESHARE' => 3,
                    'POST_SAVE' => 5,
                    'POST_SEND' => 2,
                    'FOLLOWER_GAINED_FROM_CONTENT' => 6,
                    'PROFILE_VIEW_FROM_CONTENT' => 11,
                ];

                return Http::response([
                    'elements' => [
                        [
                            'metricType' => $query['queryType'] ?? 'IMPRESSION',
                            'count' => $values[$query['queryType'] ?? 'IMPRESSION'] ?? 0,
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        $user = $this->user();
        $provider = SocialProvider::where('key', 'linkedin')->firstOrFail();

        SocialAccount::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_account_urn' => 'urn:li:person:test',
            'display_name' => 'Rana LinkedIn',
            'access_token_encrypted' => 'linkedin-token',
            'status' => 'connected',
        ]);

        $this->actingAs($user)
            ->post(route('social-studio.insights.sync'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('social_analytics_snapshots', [
            'analytics_scope' => 'follower',
            'metric_name' => 'followerCount',
            'metric_value' => 321,
        ]);

        $this->assertDatabaseHas('social_analytics_snapshots', [
            'analytics_scope' => 'aggregate',
            'metric_name' => 'impressionCount',
            'metric_value' => 1200,
        ]);

        $this->assertSame(11, SocialAnalyticsSnapshot::count());
    }

    private function user(): User
    {
        $tenant = Tenant::create([
            'name' => 'LinkedIn Analytics Tenant',
            'slug' => 'linkedin-analytics-' . uniqid(),
            'status' => 'active',
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
    }
}
