<?php

namespace App\Services;

use App\Contracts\PlatformSearchService;
use App\Support\InfluencerSearchResult;
use Illuminate\Support\Facades\Cache;

abstract class AbstractPlatformSearchService implements PlatformSearchService
{
    /**
     * How long platform search results are cached, in minutes.
     */
    protected const CACHE_TTL_MINUTES = 15;

    /**
     * Search for influencers on the platform, caching the mapped results.
     *
     * @return array<InfluencerSearchResult>
     */
    final public function search(string $query, int $maxResults = 10): array
    {
        $cacheKey = $this->cachePrefix().'_search_'.md5($query.'_'.$maxResults);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(static::CACHE_TTL_MINUTES),
            fn () => $this->performSearch($query, $maxResults),
        );
    }

    /**
     * The cache key prefix that uniquely identifies this platform.
     */
    abstract protected function cachePrefix(): string;

    /**
     * Perform the actual (uncached) platform search.
     *
     * @return array<InfluencerSearchResult>
     */
    abstract protected function performSearch(string $query, int $maxResults): array;
}
