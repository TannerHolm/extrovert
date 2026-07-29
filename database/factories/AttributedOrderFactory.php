<?php

namespace Database\Factories;

use App\Enums\AttributionMatch;
use App\Models\AttributedOrder;
use App\Models\Deal;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributedOrder>
 */
class AttributedOrderFactory extends Factory
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
            'shopify_order_id' => (string) fake()->unique()->numberBetween(1_000_000, 9_999_999),
            'order_number' => (string) fake()->numberBetween(1000, 9999),
            'total_cents' => fake()->numberBetween(2000, 250000),
            'currency' => 'USD',
            'customer_hash' => hash('sha256', fake()->email()),
            'is_new_customer' => true,
            'matched_via' => AttributionMatch::DiscountCode,
            'placed_at' => now(),
            'raw_payload' => [],
        ];
    }
}
