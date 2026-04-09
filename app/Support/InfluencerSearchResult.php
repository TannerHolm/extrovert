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
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            platform: Platform::from($data['platform']),
            platformId: $data['platform_id'],
            handle: $data['handle'],
            profileUrl: $data['profile_url'],
            displayName: $data['display_name'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            followerCount: $data['follower_count'] ?? null,
            engagementRate: $data['engagement_rate'] ?? null,
            contactEmail: $data['contact_email'] ?? null,
            latestActivityAt: $data['latest_activity_at'] ?? null,
        );
    }

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
