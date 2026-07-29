<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Deal;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
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
            'deal_id' => Deal::factory(),
            'status' => AgreementStatus::Draft,
            'body_markdown' => "# Agreement\n\nTerms go here.",
            'sign_token' => Str::random(64),
            'signer_name' => fake()->name(),
            'signer_email' => fake()->safeEmail(),
            'created_by' => null,
        ];
    }
}
