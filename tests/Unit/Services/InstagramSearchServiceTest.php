<?php

namespace Tests\Unit\Services;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Services\InstagramSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramSearchServiceTest extends TestCase
{
    public function test_it_maps_users_into_search_results(): void
    {
        config(['services.rapidapi.key' => 'test-key']);

        Http::fake([
            '*/v1/search_users*' => Http::response([
                'data' => [
                    'items' => [
                        ['username' => 'foodie', 'pk' => '999', 'full_name' => 'Food Lover', 'profile_pic_url' => 'https://img.test/foodie.jpg'],
                    ],
                ],
            ]),
            '*/v1/info*' => Http::response([
                'data' => [
                    'full_name' => 'Food Lover',
                    'profile_pic_url' => 'https://img.test/foodie-hd.jpg',
                    'follower_count' => 20000,
                    'public_email' => 'hi@foodie.test',
                    'edge_owner_to_timeline_media' => [
                        'edges' => [
                            ['node' => [
                                'edge_liked_by' => ['count' => 500],
                                'edge_media_to_comment' => ['count' => 50],
                                'taken_at_timestamp' => 1700000000,
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $results = app(InstagramSearchService::class)->search('food', 5);

        $this->assertCount(1, $results);
        $this->assertSame(Platform::Instagram, $results[0]->platform);
        $this->assertSame('999', $results[0]->platformId);
        $this->assertSame('@foodie', $results[0]->handle);
        $this->assertSame('https://instagram.com/foodie', $results[0]->profileUrl);
        $this->assertSame(20000, $results[0]->followerCount);
        $this->assertSame('hi@foodie.test', $results[0]->contactEmail);
        // (500 + 50) / 20000 * 100 = 2.75
        $this->assertSame(2.75, $results[0]->engagementRate);
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        config(['services.rapidapi.key' => null]);

        $this->expectException(PlatformSearchException::class);

        app(InstagramSearchService::class)->search('food', 5);
    }

    public function test_it_throws_platform_exception_when_search_request_fails(): void
    {
        config(['services.rapidapi.key' => 'test-key']);

        Http::fake([
            '*/v1/search_users*' => Http::response('boom', 500),
        ]);

        $this->expectException(PlatformSearchException::class);

        app(InstagramSearchService::class)->search('food', 5);
    }
}
