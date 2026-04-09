<?php

namespace App\Services;

use App\Contracts\PlatformSearchService;
use App\Enums\Platform;
use App\Support\InfluencerSearchResult;
use App\Exceptions\PlatformSearchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokSearchService implements PlatformSearchService
{
    public function search(string $query, int $maxResults = 10): array
    {
        $cacheKey = 'tiktok_search_'.md5($query.'_'.$maxResults);

        $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $maxResults) {
            return array_map(fn (InfluencerSearchResult $r) => $r->toArray(), $this->performSearch($query, $maxResults));
        });

        return array_map(fn (array $r) => InfluencerSearchResult::fromArray($r), $cached);
    }

    /**
     * @return array<InfluencerSearchResult>
     */
    private function performSearch(string $query, int $maxResults): array
    {
        $apiKey = config('services.rapidapi.key');
        $host = config('services.rapidapi.tiktok_host');

        if (! $apiKey) {
            throw new PlatformSearchException('TikTok API key is not configured. Please contact your administrator.');
        }

        // Search for users
        $searchResponse = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => $host,
        ])->get("https://{$host}/user/search", [
            'keyword' => $query,
            'count' => $maxResults,
        ]);

        if ($searchResponse->failed()) {
            Log::error('TikTok search failed', ['status' => $searchResponse->status(), 'body' => $searchResponse->body()]);
            throw new PlatformSearchException('TikTok search is temporarily unavailable. Please try again later.');
        }

        $users = $searchResponse->json('data.user_list', $searchResponse->json('data', []));

        return collect($users)->take($maxResults)->map(function (array $item) use ($apiKey, $host) {
            $user = $item['user_info'] ?? $item;
            $stats = $item['stats'] ?? $user['stats'] ?? [];

            $username = $user['unique_id'] ?? $user['uniqueId'] ?? $user['username'] ?? null;
            $userId = $user['uid'] ?? $user['id'] ?? $user['user_id'] ?? null;

            if (! $username) {
                return null;
            }

            $followerCount = $stats['follower_count'] ?? $stats['followerCount'] ?? $user['follower_count'] ?? null;
            $engagementRate = null;

            // Calculate engagement from stats if available
            if ($followerCount && $followerCount > 0) {
                $avgLikes = $stats['avg_likes'] ?? null;
                $avgComments = $stats['avg_comments'] ?? null;

                if ($avgLikes !== null && $avgComments !== null) {
                    $engagementRate = round((($avgLikes + $avgComments) / $followerCount) * 100, 2);
                } else {
                    // Try to get engagement from recent posts
                    $engagementRate = $this->getEngagementFromPosts($username, $followerCount, $apiKey, $host);
                }
            }

            return new InfluencerSearchResult(
                platform: Platform::TikTok,
                platformId: (string) ($userId ?? $username),
                handle: '@'.$username,
                profileUrl: 'https://tiktok.com/@'.$username,
                displayName: $user['nickname'] ?? $user['nick_name'] ?? null,
                avatarUrl: $user['avatar_thumb'] ?? $user['avatarThumb'] ?? $user['avatar'] ?? null,
                followerCount: $followerCount ? (int) $followerCount : null,
                engagementRate: $engagementRate,
                contactEmail: null, // TikTok doesn't expose email via API
                latestActivityAt: null,
            );
        })->filter()->values()->all();
    }

    private function getEngagementFromPosts(string $username, int $followerCount, string $apiKey, string $host): ?float
    {
        $response = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => $host,
        ])->get("https://{$host}/user/posts", [
            'unique_id' => $username,
            'count' => 12,
        ]);

        if ($response->failed()) {
            return null;
        }

        $posts = $response->json('data.videos', $response->json('data', []));

        if (empty($posts)) {
            return null;
        }

        $totalEngagement = 0;
        $count = 0;

        foreach ($posts as $post) {
            $stats = $post['stats'] ?? $post;
            $likes = $stats['digg_count'] ?? $stats['diggCount'] ?? $stats['likes'] ?? 0;
            $comments = $stats['comment_count'] ?? $stats['commentCount'] ?? $stats['comments'] ?? 0;
            $totalEngagement += $likes + $comments;
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        $avgEngagement = $totalEngagement / $count;

        return round(($avgEngagement / $followerCount) * 100, 2);
    }
}
