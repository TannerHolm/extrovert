<?php

namespace Database\Factories;

use App\Enums\CompensationType;
use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'influencer_list_entry_id' => InfluencerListEntry::factory(),
            'status' => DealStatus::Draft,
            'compensation_type' => CompensationType::FlatFee,
            'flat_fee_cents' => 50000,
            'commission_rate' => null,
            'product_value_cents' => null,
            'deliverables' => [],
            'usage_rights' => null,
            'starts_at' => null,
            'ends_at' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /**
     * A deal whose list entry belongs to the same team as the deal.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn () => [
            'team_id' => $team->id,
            'influencer_list_entry_id' => InfluencerListEntry::factory()->for(
                InfluencerList::factory()->for($team),
                'influencerList',
            ),
        ]);
    }
}
