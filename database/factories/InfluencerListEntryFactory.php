<?php

namespace Database\Factories;

use App\Enums\OutreachStatus;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InfluencerListEntry>
 */
class InfluencerListEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'influencer_list_id' => InfluencerList::factory(),
            'influencer_id' => Influencer::factory(),
            'outreach_status' => OutreachStatus::None,
            'notes' => null,
            'added_by' => null,
        ];
    }
}
