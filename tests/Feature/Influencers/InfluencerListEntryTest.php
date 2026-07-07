<?php

namespace Tests\Feature\Influencers;

use App\Enums\OutreachStatus;
use App\Enums\TeamRole;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfluencerListEntryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function influencerPayload(): array
    {
        return [
            'platform' => 'youtube',
            'platform_id' => 'UC999',
            'handle' => '@creator',
            'profile_url' => 'https://youtube.com/channel/UC999',
        ];
    }

    public function test_an_owner_can_add_an_influencer_to_a_list(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('influencers.entries.store', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id]), $this->influencerPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('influencers', ['platform' => 'youtube', 'platform_id' => 'UC999']);
        $influencer = Influencer::where('platform_id', 'UC999')->firstOrFail();
        $this->assertDatabaseHas('influencer_list_entries', [
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
            'added_by' => $user->id,
        ]);
    }

    public function test_a_member_cannot_add_an_influencer_to_a_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $list = InfluencerList::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('influencers.entries.store', ['current_team' => $team->slug, 'influencerList' => $list->id]), $this->influencerPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('influencers', ['platform_id' => 'UC999']);
    }

    public function test_an_owner_can_update_an_entry_status(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        $this->actingAs($user)
            ->patch(route('influencers.entries.update', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id, 'entry' => $entry->id]), [
                'outreach_status' => OutreachStatus::Contacted->value,
                'notes' => 'Sent intro email',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('influencer_list_entries', [
            'id' => $entry->id,
            'outreach_status' => 'contacted',
            'notes' => 'Sent intro email',
        ]);
    }

    public function test_an_owner_can_remove_an_entry(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        $this->actingAs($user)
            ->delete(route('influencers.entries.destroy', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id, 'entry' => $entry->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('influencer_list_entries', ['id' => $entry->id]);
    }

    public function test_a_user_cannot_add_an_influencer_to_another_teams_list(): void
    {
        $user = User::factory()->create();
        $otherList = InfluencerList::factory()->create();

        $this->actingAs($user)
            ->post(route('influencers.entries.store', ['current_team' => $user->currentTeam->slug, 'influencerList' => $otherList->id]), $this->influencerPayload())
            ->assertNotFound();
    }

    public function test_a_user_cannot_update_an_entry_in_another_teams_list(): void
    {
        $user = User::factory()->create();
        $otherList = InfluencerList::factory()->create();
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $otherList->id]);

        $this->actingAs($user)
            ->patch(route('influencers.entries.update', ['current_team' => $user->currentTeam->slug, 'influencerList' => $otherList->id, 'entry' => $entry->id]), [
                'outreach_status' => OutreachStatus::Confirmed->value,
            ])
            ->assertNotFound();
    }
}
