<?php

namespace App\Services;

use App\Contracts\PlatformSearchService;
use App\Enums\Platform;
use App\Support\InfluencerSearchResult;

class PlatformSearchManager
{
    public function driver(Platform $platform): PlatformSearchService
    {
        return match ($platform) {
            Platform::YouTube => app(YouTubeSearchService::class),
            Platform::Instagram => app(InstagramSearchService::class),
            Platform::TikTok => app(TikTokSearchService::class),
        };
    }

    /**
     * @return array<InfluencerSearchResult>
     */
    public function search(Platform $platform, string $query, int $maxResults = 10): array
    {
        return $this->driver($platform)->search($query, $maxResults);
    }
}
