<?php

namespace App\Services;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Support\InfluencerSearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokSearchService extends AbstractPlatformSearchService
{
    protected function cachePrefix(): string
    {
        return 'tiktok';
    }

    /**
     * @return array<InfluencerSearchResult>
     */
    protected function performSearch(string $query, int $maxResults): array
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
            Log::error('TikTok search failed', ['status' => $searchResponse->status()]);
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
            $latestActivityAt = null;

            // Calculate engagement from stats if available
            if ($followerCount && $followerCount > 0) {
                $avgLikes = $stats['avg_likes'] ?? null;
                $avgComments = $stats['avg_comments'] ?? null;

                if ($avgLikes !== null && $avgComments !== null) {
                    $engagementRate = round((($avgLikes + $avgComments) / $followerCount) * 100, 2);
                } else {
                    // Fetching recent posts is a paid call, so pull engagement and the
                    // most recent post date from the same request rather than two.
                    $posts = $this->analyzeRecentPosts($username, $followerCount, $apiKey, $host);
                    $engagementRate = $posts['rate'];
                    $latestActivityAt = $posts['latestPostAt'];
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
                latestActivityAt: $latestActivityAt,
            );
        })->filter()->values()->all();
    }

    /**
     * Sample recent posts to estimate engagement rate and find the most recent post date.
     *
     * @return array{rate: float|null, latestPostAt: string|null}
     */
    private function analyzeRecentPosts(string $username, int $followerCount, string $apiKey, string $host): array
    {
        $response = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => $host,
        ])->get("https://{$host}/user/posts", [
            'unique_id' => $username,
            'count' => 12,
        ]);

        if ($response->failed()) {
            return ['rate' => null, 'latestPostAt' => null];
        }

        $posts = $response->json('data.videos', $response->json('data', []));

        if (empty($posts)) {
            return ['rate' => null, 'latestPostAt' => null];
        }

        $totalEngagement = 0;
        $count = 0;
        $latestCreatedAt = null;

        foreach ($posts as $post) {
            $stats = $post['stats'] ?? $post;
            $likes = $stats['digg_count'] ?? $stats['diggCount'] ?? $stats['likes'] ?? 0;
            $comments = $stats['comment_count'] ?? $stats['commentCount'] ?? $stats['comments'] ?? 0;
            $totalEngagement += $likes + $comments;
            $count++;

            // TikTok exposes post timestamps as a Unix epoch under create_time.
            $createTime = (int) ($post['create_time'] ?? $post['createTime'] ?? 0);
            if ($createTime > 0 && ($latestCreatedAt === null || $createTime > $latestCreatedAt)) {
                $latestCreatedAt = $createTime;
            }
        }

        if ($count === 0) {
            return ['rate' => null, 'latestPostAt' => null];
        }

        $avgEngagement = $totalEngagement / $count;

        return [
            'rate' => round(($avgEngagement / $followerCount) * 100, 2),
            'latestPostAt' => $latestCreatedAt !== null ? date('c', $latestCreatedAt) : null,
        ];
    }
}
