<?php

namespace Tests\Feature\Shopify;

use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\TeamIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_attributes_historical_orders(): void
    {
        $team = Team::factory()->create();
        $integration = TeamIntegration::factory()->create(['team_id' => $team->id]);
        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'discount_code' => 'FREEDOM-JANE10',
        ]);

        Http::fake([
            'example.myshopify.com/admin/api/*/orders.json*' => Http::response([
                'orders' => [
                    [
                        'id' => 1001,
                        'order_number' => 2001,
                        'total_price' => '80.00',
                        'currency' => 'USD',
                        'created_at' => '2026-06-01T09:00:00-04:00',
                        'discount_codes' => [['code' => 'FREEDOM-JANE10']],
                        'customer' => ['id' => 1, 'orders_count' => 1],
                    ],
                    [
                        'id' => 1002,
                        'order_number' => 2002,
                        'total_price' => '45.00',
                        'currency' => 'USD',
                        'created_at' => '2026-06-02T09:00:00-04:00',
                        'discount_codes' => [],
                        'landing_site' => '/',
                    ],
                ],
            ]),
        ]);

        $this->artisan('extrovert:shopify-backfill', ['team' => $team->slug, '--months' => 6])
            ->expectsOutputToContain('Scanned 2 order(s); attributed 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('attributed_orders', 1);
        $this->assertDatabaseHas('attributed_orders', [
            'team_id' => $team->id,
            'shopify_order_id' => '1001',
            'total_cents' => 8000,
        ]);

        $this->assertNotNull($integration->fresh()->last_synced_at);
    }

    public function test_it_fails_cleanly_without_an_integration(): void
    {
        $team = Team::factory()->create();

        $this->artisan('extrovert:shopify-backfill', ['team' => $team->slug])
            ->assertFailed();
    }
}
