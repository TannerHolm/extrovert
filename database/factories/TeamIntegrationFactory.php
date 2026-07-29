<?php

namespace Database\Factories;

use App\Enums\IntegrationProvider;
use App\Models\Team;
use App\Models\TeamIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamIntegration>
 */
class TeamIntegrationFactory extends Factory
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
            'provider' => IntegrationProvider::Shopify,
            'credentials' => [
                'shop_domain' => 'example.myshopify.com',
                'access_token' => 'shpat_test_token',
                'api_secret' => 'shpss_test_secret',
            ],
            'settings' => [],
            'connected_at' => now(),
            'last_synced_at' => null,
        ];
    }
}
