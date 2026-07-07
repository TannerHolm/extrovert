<?php

namespace App\Actions\Influencers;

use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\User;

class SaveInfluencerToList
{
    /**
     * Upsert the influencer and attach it to the list (a no-op if already present).
     *
     * @param  array<string, mixed>  $data  Validated influencer attributes.
     */
    public function handle(InfluencerList $list, array $data, User $addedBy): InfluencerListEntry
    {
        $influencer = Influencer::updateOrCreate(
            [
                'platform' => $data['platform'],
                'platform_id' => $data['platform_id'],
            ],
            [
                'handle' => $data['handle'],
                'profile_url' => $data['profile_url'],
                'display_name' => $data['display_name'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'follower_count' => $data['follower_count'] ?? null,
                'engagement_rate' => $data['engagement_rate'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'latest_activity_at' => $data['latest_activity_at'] ?? null,
            ],
        );

        return InfluencerListEntry::firstOrCreate(
            [
                'influencer_list_id' => $list->id,
                'influencer_id' => $influencer->id,
            ],
            [
                'added_by' => $addedBy->id,
            ],
        );
    }
}
