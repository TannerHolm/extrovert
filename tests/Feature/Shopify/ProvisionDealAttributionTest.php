<?php

namespace Tests\Feature\Shopify;

use App\Enums\DealStatus;
use App\Jobs\Shopify\ProvisionDealAttribution;
use App\Models\Deal;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\TeamIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProvisionDealAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function dealForTeam(Team $team): Deal
    {
        $list = InfluencerList::factory()->create(['team_id' => $team->id, 'name' => 'Partners']);
        $influencer = Influencer::factory()->create(['handle' => '@jane.doe']);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
        ]);

        return Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => DealStatus::Agreed,
        ]);
    }

    public function test_it_provisions_a_ref_token_and_discount_code(): void
    {
        Http::fake([
            'example.myshopify.com/admin/api/*/price_rules.json' => Http::response([
                'price_rule' => ['id' => 5551],
            ]),
            'example.myshopify.com/admin/api/*/price_rules/5551/discount_codes.json' => Http::response([
                'discount_code' => ['id' => 7771, 'code' => 'FREEDOM-JANEDOE10'],
            ]),
        ]);

        $team = Team::factory()->create(['name' => 'Freedom Fuel']);
        TeamIntegration::factory()->create(['team_id' => $team->id]);
        $deal = $this->dealForTeam($team);

        (new ProvisionDealAttribution($deal))->handle();

        $deal->refresh();
        $this->assertNotNull($deal->ref_token);
        $this->assertSame('FREEDOM-JANEDOE10', $deal->discount_code);
        $this->assertSame('5551', $deal->shopify_price_rule_id);
        $this->assertSame('7771', $deal->shopify_discount_code_id);

        // The generated code follows BRAND-HANDLE<percent> and was sent to Shopify.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'price_rules.json')
            && $request['price_rule']['title'] === 'FREEDOM-JANEDOE10'
            && $request['price_rule']['value'] === '-10');
    }

    public function test_it_only_sets_a_ref_token_when_no_store_is_connected(): void
    {
        Http::fake();

        $team = Team::factory()->create();
        $deal = $this->dealForTeam($team);

        (new ProvisionDealAttribution($deal))->handle();

        $deal->refresh();
        $this->assertNotNull($deal->ref_token);
        $this->assertNull($deal->discount_code);
        Http::assertNothingSent();
    }

    public function test_updating_a_deal_to_agreed_dispatches_provisioning(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
        ]);

        $this->actingAs($user)
            ->patch(route('influencers.entries.deals.update', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
                'deal' => $deal->id,
            ]), [
                'status' => DealStatus::Agreed->value,
                'compensation_type' => 'flat_fee',
                'flat_fee_cents' => 50000,
            ])
            ->assertRedirect();

        Queue::assertPushed(ProvisionDealAttribution::class, fn ($job) => $job->deal->is($deal));
    }

    public function test_draft_deals_do_not_trigger_provisioning(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        $this->actingAs($user)
            ->post(route('influencers.entries.deals.store', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), [
                'compensation_type' => 'flat_fee',
                'flat_fee_cents' => 50000,
            ])
            ->assertRedirect();

        Queue::assertNotPushed(ProvisionDealAttribution::class);
    }
}
