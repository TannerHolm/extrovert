<?php

namespace Tests\Feature\Influencers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InfluencerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function teamSlug(User $user): string
    {
        return $user->currentTeam->slug;
    }

    public function test_the_search_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('influencers.search', ['current_team' => $this->teamSlug($user)]))
            ->assertOk();
    }

    public function test_guests_are_redirected_from_the_search_page(): void
    {
        $user = User::factory()->create();

        $this->get(route('influencers.search', ['current_team' => $this->teamSlug($user)]))
            ->assertRedirect(route('login'));
    }

    public function test_it_returns_search_results_as_json(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    ['id' => ['channelId' => 'UC123'], 'snippet' => ['channelId' => 'UC123', 'title' => 'Cool Channel']],
                ],
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UC123',
                    'snippet' => ['title' => 'Cool Channel', 'customUrl' => '@coolchannel'],
                    'statistics' => ['subscriberCount' => '15000'],
                    'brandingSettings' => ['channel' => []],
                    'contentDetails' => ['relatedPlaylists' => []],
                ]],
            ]),
            'www.googleapis.com/youtube/v3/*' => Http::response(['items' => []]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('influencers.search.results', [
                'current_team' => $this->teamSlug($user),
                'platform' => 'youtube',
                'query' => 'cooking',
            ]))
            ->assertOk()
            ->assertJsonStructure(['results' => [['platform', 'platform_id', 'handle', 'profile_url']]])
            ->assertJsonPath('results.0.platform_id', 'UC123');
    }

    public function test_it_returns_503_when_the_platform_search_fails(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response('rate limited', 429),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('influencers.search.results', [
                'current_team' => $this->teamSlug($user),
                'platform' => 'youtube',
                'query' => 'cooking',
            ]))
            ->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    public function test_it_validates_the_search_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('influencers.search.results', [
                'current_team' => $this->teamSlug($user),
                'platform' => 'youtube',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('query');
    }

    public function test_hidden_platforms_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['instagram', 'tiktok'] as $platform) {
            $this->actingAs($user)
                ->getJson(route('influencers.search.results', [
                    'current_team' => $this->teamSlug($user),
                    'platform' => $platform,
                    'query' => 'cooking',
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('platform');
        }
    }

    public function test_only_available_platforms_are_offered_on_the_search_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('influencers.search', ['current_team' => $this->teamSlug($user)]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('influencers/Search')
                ->has('platforms', 1)
                ->where('platforms.0.value', 'youtube')
            );
    }

    public function test_a_user_cannot_search_within_a_team_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();

        $this->actingAs($user)
            ->get(route('influencers.search', ['current_team' => $otherTeam->slug]))
            ->assertForbidden();
    }
}
