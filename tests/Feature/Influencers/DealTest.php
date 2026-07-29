<?php

namespace Tests\Feature\Influencers;

use App\Enums\CompensationType;
use App\Enums\DealStatus;
use App\Enums\TeamRole;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{list: InfluencerList, entry: InfluencerListEntry}
     */
    private function listWithEntry(Team $team): array
    {
        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        return ['list' => $list, 'entry' => $entry];
    }

    /**
     * @return array<string, mixed>
     */
    private function dealPayload(): array
    {
        return [
            'compensation_type' => CompensationType::Hybrid->value,
            'flat_fee_cents' => 50000,
            'commission_rate' => 12.5,
            'product_value_cents' => 15000,
            'deliverables' => [
                [
                    'type' => 'video',
                    'platform' => 'youtube',
                    'due_date' => now()->addWeeks(2)->toDateString(),
                    'posted_url' => null,
                ],
            ],
            'usage_rights' => 'Paid ads for 90 days',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'notes' => 'Negotiated on call',
        ];
    }

    public function test_an_owner_can_create_a_deal_for_an_entry(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);

        $this->actingAs($user)
            ->post(route('influencers.entries.deals.store', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), $this->dealPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('deals', [
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => DealStatus::Draft->value,
            'compensation_type' => CompensationType::Hybrid->value,
            'flat_fee_cents' => 50000,
            'created_by' => $user->id,
        ]);

        $deal = Deal::firstOrFail();
        $this->assertCount(1, $deal->deliverables);
        $this->assertSame('video', $deal->deliverables[0]['type']);
    }

    public function test_a_member_cannot_create_a_deal(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($team);

        $this->actingAs($member)
            ->post(route('influencers.entries.deals.store', [
                'current_team' => $team->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), $this->dealPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('deals', 0);
    }

    public function test_a_user_cannot_create_a_deal_on_another_teams_list(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($otherTeam);

        $this->actingAs($user)
            ->post(route('influencers.entries.deals.store', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), $this->dealPayload())
            ->assertNotFound();
    }

    public function test_an_owner_can_update_a_deal_status_and_terms(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);
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
                ...$this->dealPayload(),
                'status' => DealStatus::Agreed->value,
                'compensation_type' => CompensationType::FlatFee->value,
                'flat_fee_cents' => 75000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'status' => DealStatus::Agreed->value,
            'compensation_type' => CompensationType::FlatFee->value,
            'flat_fee_cents' => 75000,
        ]);
    }

    public function test_a_deal_must_belong_to_the_entry_being_updated(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);
        ['entry' => $otherEntry] = $this->listWithEntry($user->currentTeam);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $otherEntry->id,
        ]);

        $this->actingAs($user)
            ->patch(route('influencers.entries.deals.update', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
                'deal' => $deal->id,
            ]), $this->dealPayload())
            ->assertNotFound();
    }

    public function test_an_owner_can_delete_a_deal(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
        ]);

        $this->actingAs($user)
            ->delete(route('influencers.entries.deals.destroy', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
                'deal' => $deal->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('deals', ['id' => $deal->id]);
    }

    public function test_deal_validation_rejects_bad_deliverables(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);

        $this->actingAs($user)
            ->from(route('influencers.lists.show', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
            ]))
            ->post(route('influencers.entries.deals.store', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), [
                'compensation_type' => 'flat_fee',
                'deliverables' => [
                    ['type' => 'billboard', 'platform' => 'youtube'],
                ],
            ])
            ->assertSessionHasErrors(['deliverables.0.type']);

        $this->assertDatabaseCount('deals', 0);
    }

    public function test_deals_are_included_in_the_list_show_payload(): void
    {
        $user = User::factory()->create();
        ['list' => $list, 'entry' => $entry] = $this->listWithEntry($user->currentTeam);
        Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => DealStatus::Live,
        ]);

        $this->actingAs($user)
            ->get(route('influencers.lists.show', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->component('influencers/ListShow')
                ->where('entries.data.0.deals.0.status', DealStatus::Live->value)
                ->has('dealStatuses', 5)
                ->has('compensationTypes', 4));
    }
}
