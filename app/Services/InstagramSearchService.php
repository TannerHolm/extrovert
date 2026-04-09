<?php

namespace App\Services;

use App\Contracts\PlatformSearchService;
use App\Enums\Platform;
use App\Support\InfluencerSearchResult;
use App\Exceptions\PlatformSearchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramSearchService implements PlatformSearchService
{
    public function search(string $query, int $maxResults = 10): array
    {
        $cacheKey = 'instagram_search_'.md5($query.'_'.$maxResults);

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
        $host = config('services.rapidapi.instagram_host');

        if (! $apiKey) {
            throw new PlatformSearchException('Instagram API key is not configured. Please contact your administrator.');
        }

        // Search for users
        $searchResponse = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => $host,
        ])->get("https://{$host}/v1/search_users", [
            'search_query' => $query,
        ]);

        if ($searchResponse->failed()) {
            Log::error('Instagram search failed', ['status' => $searchResponse->status(), 'body' => $searchResponse->body()]);
            throw new PlatformSearchException('Instagram search is temporarily unavailable. Please try again later.');
        }

        $users = $searchResponse->json('data.items', $searchResponse->json('data.users', []));

        return collect($users)->take($maxResults)->map(function (array $user) use ($apiKey, $host) {
            $username = $user['username'] ?? $user['user']['username'] ?? null;
            $userId = $user['pk'] ?? $user['user']['pk'] ?? null;

            if (! $username) {
                return null;
            }

            // Get detailed profile info
            $profileData = $this->getProfile($username, $apiKey, $host);

            $followerCount = $profileData['follower_count'] ?? $user['follower_count'] ?? null;
            $engagementRate = null;

            if ($followerCount && $followerCount > 0 && isset($profileData['recent_posts'])) {
                $engagementRate = $this->calculateEngagementRate($profileData['recent_posts'], $followerCount);
            }

            return new InfluencerSearchResult(
                platform: Platform::Instagram,
                platformId: (string) ($userId ?? $username),
                handle: '@'.$username,
                profileUrl: 'https://instagram.com/'.$username,
                displayName: $profileData['full_name'] ?? $user['full_name'] ?? $user['user']['full_name'] ?? null,
                avatarUrl: $profileData['profile_pic_url'] ?? $user['profile_pic_url'] ?? $user['user']['profile_pic_url'] ?? null,
                followerCount: $followerCount ? (int) $followerCount : null,
                engagementRate: $engagementRate,
                contactEmail: $profileData['public_email'] ?? $profileData['business_email'] ?? null,
                latestActivityAt: $profileData['latest_post_date'] ?? null,
            );
        })->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getProfile(string $username, string $apiKey, string $host): array
    {
        $response = Http::withHeaders([
            'x-rapidapi-key' => $apiKey,
            'x-rapidapi-host' => $host,
        ])->get("https://{$host}/v1/info", [
            'username_or_id_or_url' => $username,
        ]);

        if ($response->failed()) {
            return [];
        }

        $data = $response->json('data', []);

        // Try to extract recent posts for engagement calculation
        $recentPosts = [];
        $latestPostDate = null;

        if (isset($data['edge_owner_to_timeline_media']['edges'])) {
            foreach (array_slice($data['edge_owner_to_timeline_media']['edges'], 0, 12) as $edge) {
                $node = $edge['node'] ?? [];
                $likes = $node['edge_liked_by']['count'] ?? $node['like_count'] ?? 0;
                $comments = $node['edge_media_to_comment']['count'] ?? $node['comment_count'] ?? 0;
                $recentPosts[] = ['likes' => $likes, 'comments' => $comments];

                if (! $latestPostDate && isset($node['taken_at_timestamp'])) {
                    $latestPostDate = date('c', $node['taken_at_timestamp']);
                }
            }
        }

        return [
            'full_name' => $data['full_name'] ?? null,
            'profile_pic_url' => $data['profile_pic_url_hd'] ?? $data['profile_pic_url'] ?? null,
            'follower_count' => $data['follower_count'] ?? $data['edge_followed_by']['count'] ?? null,
            'public_email' => $data['public_email'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'recent_posts' => $recentPosts,
            'latest_post_date' => $latestPostDate,
        ];
    }

    /**
     * @param  array<array{likes: int, comments: int}>  $posts
     */
    private function calculateEngagementRate(array $posts, int $followerCount): ?float
    {
        if (empty($posts) || $followerCount === 0) {
            return null;
        }

        $totalEngagement = array_sum(array_map(fn ($p) => $p['likes'] + $p['comments'], $posts));
        $avgEngagement = $totalEngagement / count($posts);

        return round(($avgEngagement / $followerCount) * 100, 2);
    }
}
