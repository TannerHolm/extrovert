<?php

namespace App\Support;

use App\Enums\Platform;

readonly class InfluencerSearchResult
{
    public function __construct(
        public Platform $platform,
        public string $platformId,
        public string $handle,
        public string $profileUrl,
        public ?string $displayName = null,
        public ?string $avatarUrl = null,
        public ?int $followerCount = null,
        public ?float $engagementRate = null,
        public ?string $contactEmail = null,
        public ?string $latestActivityAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'platform_id' => $this->platformId,
            'handle' => $this->handle,
            'profile_url' => $this->profileUrl,
            'display_name' => $this->displayName,
            'avatar_url' => $this->avatarUrl,
            'follower_count' => $this->followerCount,
            'engagement_rate' => $this->engagementRate,
            'contact_email' => $this->contactEmail,
            'latest_activity_at' => $this->latestActivityAt,
        ];
    }
}
