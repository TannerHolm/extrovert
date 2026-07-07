<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Influencer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Influencer>
 */
class InfluencerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->randomElement(Platform::cases());
        $username = fake()->unique()->userName();

        return [
            'platform' => $platform,
            'platform_id' => (string) fake()->unique()->numerify('##########'),
            'handle' => '@'.$username,
            'profile_url' => $platform->baseUrl().'/'.$username,
            'display_name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(),
            'follower_count' => fake()->numberBetween(1_000, 5_000_000),
            'engagement_rate' => fake()->randomFloat(2, 0, 20),
            'contact_email' => fake()->safeEmail(),
            'latest_activity_at' => now(),
            'platform_data' => null,
        ];
    }
}
