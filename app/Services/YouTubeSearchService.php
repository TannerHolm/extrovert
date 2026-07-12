<?php

namespace App\Services;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Support\InfluencerSearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeSearchService extends AbstractPlatformSearchService
{
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';

    protected function cachePrefix(): string
    {
        return 'youtube';
    }

    /**
     * @return array<InfluencerSearchResult>
     */
    protected function performSearch(string $query, int $maxResults): array
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
            Log::error('YouTube search failed', ['status' => $searchResponse->status()]);
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

        // Step 3: Sample recent videos for engagement rate and any listed contact email.
        $videoAnalysis = $this->analyzeRecentVideos($channels, $apiKey);

        return collect($items)->map(function (array $item) use ($channels, $videoAnalysis) {
            $channelId = $item['id']['channelId'] ?? $item['snippet']['channelId'] ?? null;

            if (! $channelId || ! $channels->has($channelId)) {
                return null;
            }

            $channel = $channels->get($channelId);
            $stats = $channel['statistics'] ?? [];
            $snippet = $channel['snippet'] ?? [];
            $branding = $channel['brandingSettings']['channel'] ?? [];

            $subscriberCount = isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null;

            // YouTube's API does not expose the gated "business email" field, but creators
            // often list a contact email in their About text or recent video descriptions.
            $contactEmail = $this->extractEmail($snippet['description'] ?? '', $branding['description'] ?? '')
                ?? ($videoAnalysis[$channelId]['email'] ?? null);

            return new InfluencerSearchResult(
                platform: Platform::YouTube,
                platformId: $channelId,
                handle: $snippet['customUrl'] ?? ('@'.$snippet['title']),
                profileUrl: 'https://youtube.com/channel/'.$channelId,
                displayName: $snippet['title'] ?? null,
                avatarUrl: $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
                followerCount: $subscriberCount,
                engagementRate: $videoAnalysis[$channelId]['rate'] ?? null,
                contactEmail: $contactEmail,
                // The date of the channel's most recent upload — not `snippet.publishedAt`,
                // which is the channel *creation* date and makes dormant channels look active.
                latestActivityAt: $videoAnalysis[$channelId]['latestVideoAt'] ?? null,
            );
        })->filter()->values()->all();
    }

    /**
     * Sample recent videos per channel to estimate engagement rate, find a listed contact
     * email, and determine when the channel most recently uploaded.
     *
     * @return array<string, array{rate: float|null, email: string|null, latestVideoAt: string|null}>
     */
    private function analyzeRecentVideos($channels, string $apiKey): array
    {
        $analysis = [];

        foreach ($channels as $channelId => $channel) {
            $uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            $subscriberCount = (int) ($channel['statistics']['subscriberCount'] ?? 0);

            if (! $uploadsPlaylistId) {
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

            // Get video statistics and descriptions
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
            $email = null;
            $latestVideoAt = null;

            foreach ($videos as $video) {
                $likes = (int) ($video['statistics']['likeCount'] ?? 0);
                $comments = (int) ($video['statistics']['commentCount'] ?? 0);
                $totalEngagement += $likes + $comments;
                $videoCount++;

                $email ??= $this->extractEmail($video['snippet']['description'] ?? '');

                // Uploads aren't guaranteed to come back newest-first, so track the max.
                $publishedAt = $video['snippet']['publishedAt'] ?? null;
                if ($publishedAt && ($latestVideoAt === null || $publishedAt > $latestVideoAt)) {
                    $latestVideoAt = $publishedAt;
                }
            }

            $rate = null;
            if ($videoCount > 0 && $subscriberCount > 0) {
                $rate = round(($totalEngagement / $videoCount / $subscriberCount) * 100, 2);
            }

            $analysis[$channelId] = ['rate' => $rate, 'email' => $email, 'latestVideoAt' => $latestVideoAt];
        }

        return $analysis;
    }

    /**
     * Extract the first email address found in the given text fragments, if any.
     */
    private function extractEmail(string ...$texts): ?string
    {
        foreach ($texts as $text) {
            if ($text !== '' && preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,63}/', $text, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }
}
