<?php

namespace Tests\Unit\Services;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Services\TikTokSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokSearchServiceTest extends TestCase
{
    public function test_it_maps_users_into_search_results(): void
    {
        config(['services.rapidapi.key' => 'test-key']);

        Http::fake([
            '*/user/search*' => Http::response([
                'data' => [
                    'user_list' => [
                        [
                            'user_info' => [
                                'unique_id' => 'dancer',
                                'uid' => '555',
                                'nickname' => 'Dance Star',
                                'avatar_thumb' => 'https://img.test/dancer.jpg',
                            ],
                            'stats' => [
                                'follower_count' => 30000,
                                'avg_likes' => 900,
                                'avg_comments' => 100,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $results = app(TikTokSearchService::class)->search('dance', 5);

        $this->assertCount(1, $results);
        $this->assertSame(Platform::TikTok, $results[0]->platform);
        $this->assertSame('555', $results[0]->platformId);
        $this->assertSame('@dancer', $results[0]->handle);
        $this->assertSame('https://tiktok.com/@dancer', $results[0]->profileUrl);
        $this->assertSame(30000, $results[0]->followerCount);
        // (900 + 100) / 30000 * 100 = 3.33
        $this->assertSame(3.33, $results[0]->engagementRate);
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        config(['services.rapidapi.key' => null]);

        $this->expectException(PlatformSearchException::class);

        app(TikTokSearchService::class)->search('dance', 5);
    }

    public function test_it_throws_platform_exception_when_search_request_fails(): void
    {
        config(['services.rapidapi.key' => 'test-key']);

        Http::fake([
            '*/user/search*' => Http::response('boom', 500),
        ]);

        $this->expectException(PlatformSearchException::class);

        app(TikTokSearchService::class)->search('dance', 5);
    }
}
