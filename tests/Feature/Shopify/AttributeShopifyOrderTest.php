<?php

namespace Tests\Feature\Shopify;

use App\Actions\Shopify\AttributeShopifyOrder;
use App\Enums\AttributionMatch;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeShopifyOrderTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $this->team->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $this->deal = Deal::factory()->create([
            'team_id' => $this->team->id,
            'influencer_list_entry_id' => $entry->id,
            'discount_code' => 'FREEDOM-JANE10',
            'ref_token' => 'janetoken123',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderPayload(array $overrides = []): array
    {
        return [
            'id' => 900001,
            'order_number' => 1042,
            'total_price' => '120.50',
            'currency' => 'USD',
            'created_at' => '2026-07-25T10:00:00-04:00',
            'customer' => ['id' => 777, 'orders_count' => 1],
            'discount_codes' => [],
            'landing_site' => '/',
            ...$overrides,
        ];
    }

    public function test_it_matches_by_discount_code_case_insensitively(): void
    {
        $order = app(AttributeShopifyOrder::class)->handle($this->team, $this->orderPayload([
            'discount_codes' => [['code' => 'freedom-jane10', 'amount' => '12.05']],
        ]));

        $this->assertNotNull($order);
        $this->assertTrue($order->deal->is($this->deal));
        $this->assertSame(AttributionMatch::DiscountCode, $order->matched_via);
        $this->assertSame(12050, $order->total_cents);
        $this->assertTrue($order->is_new_customer);
    }

    public function test_it_matches_by_ref_link(): void
    {
        $order = app(AttributeShopifyOrder::class)->handle($this->team, $this->orderPayload([
            'landing_site' => '/?ref=janetoken123&utm_source=extrovert',
        ]));

        $this->assertSame(AttributionMatch::RefLink, $order->matched_via);
    }

    public function test_it_falls_back_to_utm_content(): void
    {
        $order = app(AttributeShopifyOrder::class)->handle($this->team, $this->orderPayload([
            'landing_site' => '/products/fuel?utm_source=extrovert&utm_content=janetoken123',
        ]));

        $this->assertSame(AttributionMatch::Utm, $order->matched_via);
    }

    public function test_unmatched_orders_are_not_stored(): void
    {
        $order = app(AttributeShopifyOrder::class)->handle($this->team, $this->orderPayload([
            'discount_codes' => [['code' => 'SUMMER-SALE']],
            'landing_site' => '/?utm_source=newsletter',
        ]));

        $this->assertNull($order);
        $this->assertDatabaseCount('attributed_orders', 0);
    }

    public function test_reprocessing_the_same_order_updates_instead_of_duplicating(): void
    {
        $action = app(AttributeShopifyOrder::class);

        $action->handle($this->team, $this->orderPayload([
            'discount_codes' => [['code' => 'FREEDOM-JANE10']],
        ]));
        $action->handle($this->team, $this->orderPayload([
            'discount_codes' => [['code' => 'FREEDOM-JANE10']],
            'current_total_price' => '90.00', // partial refund reflected on orders/updated
        ]));

        $this->assertDatabaseCount('attributed_orders', 1);
        $this->assertDatabaseHas('attributed_orders', [
            'shopify_order_id' => '900001',
            'total_cents' => 9000,
        ]);
    }

    public function test_returning_customers_are_flagged(): void
    {
        $order = app(AttributeShopifyOrder::class)->handle($this->team, $this->orderPayload([
            'discount_codes' => [['code' => 'FREEDOM-JANE10']],
            'customer' => ['id' => 777, 'orders_count' => 5],
        ]));

        $this->assertFalse($order->is_new_customer);
        $this->assertSame(hash('sha256', '777'), $order->customer_hash);
    }

    public function test_codes_from_another_team_do_not_match(): void
    {
        $otherTeam = Team::factory()->create();

        $order = app(AttributeShopifyOrder::class)->handle($otherTeam, $this->orderPayload([
            'discount_codes' => [['code' => 'FREEDOM-JANE10']],
        ]));

        $this->assertNull($order);
    }
}
