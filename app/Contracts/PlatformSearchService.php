<?php

namespace App\Contracts;

use App\Support\InfluencerSearchResult;

interface PlatformSearchService
{
    /**
     * Search for influencers on the platform.
     *
     * @return array<InfluencerSearchResult>
     */
    public function search(string $query, int $maxResults = 10): array;
}
