<?php

namespace Database\Factories;

use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Models\InfluencerListEntry;
use App\Models\SuggestedAction;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestedAction>
 */
class SuggestedActionFactory extends Factory
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
            'subject_type' => (new InfluencerListEntry)->getMorphClass(),
            'subject_id' => InfluencerListEntry::factory(),
            'type' => SuggestedActionType::FollowUp,
            'payload' => [
                'subject' => 'Following up',
                'body' => 'Just checking in!',
            ],
            'status' => SuggestedActionStatus::Pending,
        ];
    }
}
