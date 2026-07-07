<?php

namespace Tests\Unit\Services;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Services\YouTubeSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeSearchServiceTest extends TestCase
{
    public function test_it_maps_channels_into_search_results(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    ['id' => ['channelId' => 'UC123'], 'snippet' => ['channelId' => 'UC123', 'title' => 'Cool Channel']],
                ],
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UC123',
                        'snippet' => [
                            'title' => 'Cool Channel',
                            'customUrl' => '@coolchannel',
                            'thumbnails' => ['medium' => ['url' => 'https://img.test/medium.jpg']],
                            'publishedAt' => '2020-01-01T00:00:00Z',
                        ],
                        'statistics' => ['subscriberCount' => '15000'],
                        'brandingSettings' => ['channel' => []],
                        // No uploads playlist -> engagement calculation is skipped.
                        'contentDetails' => ['relatedPlaylists' => []],
                    ],
                ],
            ]),
            'www.googleapis.com/youtube/v3/*' => Http::response(['items' => []]),
        ]);

        $results = app(YouTubeSearchService::class)->search('cooking', 5);

        $this->assertCount(1, $results);
        $this->assertSame(Platform::YouTube, $results[0]->platform);
        $this->assertSame('UC123', $results[0]->platformId);
        $this->assertSame('@coolchannel', $results[0]->handle);
        $this->assertSame('https://youtube.com/channel/UC123', $results[0]->profileUrl);
        $this->assertSame(15000, $results[0]->followerCount);
    }

    public function test_it_returns_empty_array_when_no_channels_found(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response(['items' => []]),
        ]);

        $this->assertSame([], app(YouTubeSearchService::class)->search('nothing', 5));
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        config(['services.youtube.api_key' => null]);

        $this->expectException(PlatformSearchException::class);

        app(YouTubeSearchService::class)->search('cooking', 5);
    }

    public function test_it_throws_platform_exception_when_search_request_fails(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response('rate limited', 429),
        ]);

        $this->expectException(PlatformSearchException::class);

        app(YouTubeSearchService::class)->search('cooking', 5);
    }

    public function test_it_extracts_a_contact_email_from_the_channel_description(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [['id' => ['channelId' => 'UC123'], 'snippet' => ['channelId' => 'UC123']]],
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UC123',
                    'snippet' => [
                        'title' => 'Cook Co',
                        'description' => "Weekly recipes.\nBusiness inquiries: hello@cookco.tv",
                    ],
                    'statistics' => ['subscriberCount' => '15000'],
                    'brandingSettings' => ['channel' => []],
                    'contentDetails' => ['relatedPlaylists' => []],
                ]],
            ]),
            'www.googleapis.com/youtube/v3/*' => Http::response(['items' => []]),
        ]);

        $results = app(YouTubeSearchService::class)->search('cooking', 5);

        $this->assertSame('hello@cookco.tv', $results[0]->contactEmail);
    }

    public function test_it_falls_back_to_a_contact_email_in_recent_video_descriptions(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [['id' => ['channelId' => 'UC123'], 'snippet' => ['channelId' => 'UC123']]],
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UC123',
                    'snippet' => ['title' => 'Cook Co', 'description' => 'No email here.'],
                    'statistics' => ['subscriberCount' => '10000'],
                    'brandingSettings' => ['channel' => []],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU123']],
                ]],
            ]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [['contentDetails' => ['videoId' => 'v1']]],
            ]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'snippet' => ['description' => 'Sponsor me: team@studio.io'],
                    'statistics' => ['likeCount' => '200', 'commentCount' => '30'],
                ]],
            ]),
        ]);

        $results = app(YouTubeSearchService::class)->search('cooking', 5);

        $this->assertSame('team@studio.io', $results[0]->contactEmail);
        $this->assertNotNull($results[0]->engagementRate);
    }
}
