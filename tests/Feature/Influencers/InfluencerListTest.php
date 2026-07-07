<?php

namespace Tests\Feature\Influencers;

use App\Enums\TeamRole;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InfluencerListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a team with an owner and a member, returning [$team, $owner, $member].
     *
     * @return array{0: Team, 1: User, 2: User}
     */
    private function teamWithMembers(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        return [$team, $owner, $member];
    }

    public function test_the_lists_index_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('influencers.lists.index', ['current_team' => $user->currentTeam->slug]))
            ->assertOk();
    }

    public function test_an_owner_can_create_a_list(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('influencers.lists.store', ['current_team' => $team->slug]), [
                'name' => 'Summer Campaign',
                'description' => 'Q3 creators',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('influencer_lists', [
            'team_id' => $team->id,
            'name' => 'Summer Campaign',
        ]);
    }

    public function test_creating_a_list_with_an_influencer_adds_the_influencer_to_it(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('influencers.lists.store', ['current_team' => $team->slug]), [
                'name' => 'Saved From Discover',
                'influencer' => [
                    'platform' => 'youtube',
                    'platform_id' => 'UC777',
                    'handle' => '@creator',
                    'profile_url' => 'https://youtube.com/channel/UC777',
                ],
            ])
            ->assertRedirect();

        $list = InfluencerList::where(['team_id' => $team->id, 'name' => 'Saved From Discover'])->firstOrFail();
        $influencer = Influencer::where('platform_id', 'UC777')->firstOrFail();

        $this->assertDatabaseHas('influencer_list_entries', [
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
            'added_by' => $user->id,
        ]);
    }

    public function test_a_member_cannot_create_a_list(): void
    {
        [$team, , $member] = $this->teamWithMembers();

        $this->actingAs($member)
            ->post(route('influencers.lists.store', ['current_team' => $team->slug]), [
                'name' => 'Blocked List',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('influencer_lists', ['name' => 'Blocked List']);
    }

    public function test_an_owner_can_view_their_teams_list(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->get(route('influencers.lists.show', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id]))
            ->assertOk();
    }

    public function test_list_view_returns_entries_as_a_nested_paginator(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        $this->actingAs($user)
            ->get(route('influencers.lists.show', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('influencers/ListShow')
                ->has('entries.data', 1)
                ->has('entries.meta.last_page')
                ->has('entries.meta.total')
                ->has('entries.links.next')
            );
    }

    public function test_a_user_cannot_view_another_teams_list(): void
    {
        $user = User::factory()->create();
        $otherList = InfluencerList::factory()->create();

        $this->actingAs($user)
            ->get(route('influencers.lists.show', ['current_team' => $user->currentTeam->slug, 'influencerList' => $otherList->id]))
            ->assertNotFound();
    }

    public function test_an_owner_can_update_a_list(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Old']);

        $this->actingAs($user)
            ->patch(route('influencers.lists.update', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id]), [
                'name' => 'New Name',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('influencer_lists', ['id' => $list->id, 'name' => 'New Name']);
    }

    public function test_an_owner_can_delete_a_list(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->delete(route('influencers.lists.destroy', ['current_team' => $user->currentTeam->slug, 'influencerList' => $list->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('influencer_lists', ['id' => $list->id]);
    }

    public function test_a_user_cannot_update_another_teams_list(): void
    {
        $user = User::factory()->create();
        $otherList = InfluencerList::factory()->create(['name' => 'Untouched']);

        $this->actingAs($user)
            ->patch(route('influencers.lists.update', ['current_team' => $user->currentTeam->slug, 'influencerList' => $otherList->id]), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('influencer_lists', ['id' => $otherList->id, 'name' => 'Untouched']);
    }
}
