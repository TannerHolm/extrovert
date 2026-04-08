<?php

namespace App\Services;

use App\Contracts\PlatformSearchService;
use App\Enums\Platform;
use App\Support\InfluencerSearchResult;
use App\Exceptions\PlatformSearchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeSearchService implements PlatformSearchService
{
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';

    public function search(string $query, int $maxResults = 10): array
    {
        $cacheKey = 'youtube_search_'.md5($query.'_'.$maxResults);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $maxResults) {
            return $this->performSearch($query, $maxResults);
        });
    }

    /**
     * @return array<InfluencerSearchResult>
     */
    private function performSearch(string $query, int $maxResults): array
    {
        $apiKey = config('services.youtube.api_key');

        if (! $apiKey) {
            throw new PlatformSearchException('YouTube API key is not configured. Please contact your administrator.');
        }

        // Step 1: Search for channels
        $searchResponse = Http::get(self::BASE_URL.'/search', [
            'key' => $apiKey,
            'q' => $query,
            'type' => 'channel',
            'part' => 'snippet',
            'maxResults' => $maxResults,
            'order' => 'relevance',
        ]);

        if ($searchResponse->failed()) {
            Log::error('YouTube search failed', ['status' => $searchResponse->status(), 'body' => $searchResponse->body()]);
            throw new PlatformSearchException('YouTube search is temporarily unavailable. Please try again later.');
        }

        $items = $searchResponse->json('items', []);

        if (empty($items)) {
            return [];
        }

        // Step 2: Get channel details (statistics)
        $channelIds = collect($items)->pluck('snippet.channelId')->filter()->implode(',');

        // Also try id.channelId for search results
        if (empty($channelIds)) {
            $channelIds = collect($items)->pluck('id.channelId')->filter()->implode(',');
        }

        $channelsResponse = Http::get(self::BASE_URL.'/channels', [
            'key' => $apiKey,
            'id' => $channelIds,
            'part' => 'snippet,statistics,brandingSettings,contentDetails',
        ]);

        if ($channelsResponse->failed()) {
            Log::error('YouTube channels fetch failed', ['status' => $channelsResponse->status()]);
            throw new PlatformSearchException('YouTube search is temporarily unavailable. Please try again later.');
        }

        $channels = collect($channelsResponse->json('items', []))->keyBy('id');

        // Step 3: Calculate engagement rates by sampling recent videos
        $engagementRates = $this->calculateEngagementRates($channels, $apiKey);

        return collect($items)->map(function (array $item) use ($channels, $engagementRates) {
            $channelId = $item['id']['channelId'] ?? $item['snippet']['channelId'] ?? null;

            if (! $channelId || ! $channels->has($channelId)) {
                return null;
            }

            $channel = $channels->get($channelId);
            $stats = $channel['statistics'] ?? [];
            $snippet = $channel['snippet'] ?? [];
            $branding = $channel['brandingSettings']['channel'] ?? [];

            $subscriberCount = isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null;

            return new InfluencerSearchResult(
                platform: Platform::YouTube,
                platformId: $channelId,
                handle: $snippet['customUrl'] ?? ('@'.$snippet['title']),
                profileUrl: 'https://youtube.com/channel/'.$channelId,
                displayName: $snippet['title'] ?? null,
                avatarUrl: $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
                followerCount: $subscriberCount,
                engagementRate: $engagementRates[$channelId] ?? null,
                contactEmail: null, // YouTube does not expose email via API
                latestActivityAt: $snippet['publishedAt'] ?? null,
            );
        })->filter()->values()->all();
    }

    /**
     * Sample recent videos to estimate engagement rate per channel.
     *
     * @return array<string, float> Channel ID => engagement rate
     */
    private function calculateEngagementRates($channels, string $apiKey): array
    {
        $rates = [];

        foreach ($channels as $channelId => $channel) {
            $uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            $subscriberCount = (int) ($channel['statistics']['subscriberCount'] ?? 0);

            if (! $uploadsPlaylistId || $subscriberCount === 0) {
                continue;
            }

            // Get recent video IDs from uploads playlist
            $playlistResponse = Http::get(self::BASE_URL.'/playlistItems', [
                'key' => $apiKey,
                'playlistId' => $uploadsPlaylistId,
                'part' => 'contentDetails',
                'maxResults' => 12,
            ]);

            if ($playlistResponse->failed()) {
                continue;
            }

            $videoIds = collect($playlistResponse->json('items', []))
                ->pluck('contentDetails.videoId')
                ->filter()
                ->implode(',');

            if (empty($videoIds)) {
                continue;
            }

            // Get video statistics
            $videosResponse = Http::get(self::BASE_URL.'/videos', [
                'key' => $apiKey,
                'id' => $videoIds,
                'part' => 'statistics,snippet',
            ]);

            if ($videosResponse->failed()) {
                continue;
            }

            $videos = $videosResponse->json('items', []);
            $totalEngagement = 0;
            $videoCount = 0;

            foreach ($videos as $video) {
                $likes = (int) ($video['statistics']['likeCount'] ?? 0);
                $comments = (int) ($video['statistics']['commentCount'] ?? 0);
                $totalEngagement += $likes + $comments;
                $videoCount++;
            }

            if ($videoCount > 0) {
                $avgEngagement = $totalEngagement / $videoCount;
                $rates[$channelId] = round(($avgEngagement / $subscriberCount) * 100, 2);
            }
        }

        return $rates;
    }
}
