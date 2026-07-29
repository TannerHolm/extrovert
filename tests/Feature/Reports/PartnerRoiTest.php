<?php

namespace Tests\Feature\Reports;

use App\Models\AttributedOrder;
use App\Models\Deal;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRoiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A deal with $500 fee + $100 product + 10% commission, earning $2,000
     * across 2 orders (1 new customer), with 1 of 2 deliverables posted.
     */
    private function dealWithPerformance(Team $team): Deal
    {
        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $influencer = Influencer::factory()->create(['display_name' => 'Jane Doe', 'handle' => '@jane']);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
        ]);

        $deal = Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'flat_fee_cents' => 50000,
            'product_value_cents' => 10000,
            'commission_rate' => 10,
            'deliverables' => [
                ['type' => 'video', 'platform' => 'youtube', 'posted_url' => 'https://youtube.com/watch?v=1'],
                ['type' => 'post', 'platform' => 'instagram', 'posted_url' => null],
            ],
        ]);

        AttributedOrder::factory()->create([
            'team_id' => $team->id,
            'deal_id' => $deal->id,
            'total_cents' => 150000,
            'is_new_customer' => true,
            'placed_at' => now(),
        ]);
        AttributedOrder::factory()->create([
            'team_id' => $team->id,
            'deal_id' => $deal->id,
            'total_cents' => 50000,
            'is_new_customer' => false,
            'placed_at' => now(),
        ]);

        return $deal;
    }

    public function test_the_roi_report_computes_spend_revenue_and_ratios(): void
    {
        $user = User::factory()->create();
        $this->dealWithPerformance($user->currentTeam);

        $this->actingAs($user)
            ->get(route('reports.roi', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/PartnerRoi')
                ->has('deals', 1)
                ->where('deals.0.partner', 'Jane Doe')
                // spend = 50000 fee + 10000 product + 10% of 200000 revenue
                ->where('deals.0.spend_cents', 80000)
                ->where('deals.0.commission_cents', 20000)
                ->where('deals.0.revenue_cents', 200000)
                ->where('deals.0.orders', 2)
                ->where('deals.0.new_customers', 1)
                ->where('deals.0.cost_per_new_customer_cents', 80000)
                ->where('deals.0.posts', 1)
                ->where('deals.0.revenue_per_post_cents', 200000)
                ->where('deals.0.roi', 2.5)
                ->where('totals.spend_cents', 80000)
                ->where('totals.revenue_cents', 200000));
    }

    public function test_the_roi_report_only_shows_the_current_teams_deals(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $this->dealWithPerformance($otherTeam);

        $this->actingAs($user)
            ->get(route('reports.roi', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('deals', 0));
    }

    public function test_the_commission_csv_reports_the_requested_month_only(): void
    {
        $user = User::factory()->create();
        $deal = $this->dealWithPerformance($user->currentTeam);

        // An order outside the requested month must not count.
        AttributedOrder::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'total_cents' => 999999,
            'placed_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.commissions', [
                'current_team' => $user->currentTeam->slug,
                'month' => now()->format('Y-m'),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Jane Doe', $csv);
        // 10% of the $2,000 attributed this month — the old order is excluded.
        $this->assertStringContainsString('200.00', $csv);
        $this->assertStringNotContainsString('9999.99', $csv);
    }
}
