<?php

namespace App\Http\Controllers\Influencers;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SaveInfluencerToListRequest;
use App\Http\Requests\Influencers\UpdateOutreachStatusRequest;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InfluencerListEntryController extends Controller
{
    /**
     * Add an influencer to a list.
     */
    public function store(SaveInfluencerToListRequest $request, string $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $influencerList = InfluencerList::findOrFail($influencerList);

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $validated = $request->validated();

        // Upsert the influencer record
        $influencer = Influencer::updateOrCreate(
            [
                'platform' => $validated['platform'],
                'platform_id' => $validated['platform_id'],
            ],
            [
                'handle' => $validated['handle'],
                'profile_url' => $validated['profile_url'],
                'display_name' => $validated['display_name'] ?? null,
                'avatar_url' => $validated['avatar_url'] ?? null,
                'follower_count' => $validated['follower_count'] ?? null,
                'engagement_rate' => $validated['engagement_rate'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'latest_activity_at' => $validated['latest_activity_at'] ?? null,
            ],
        );

        // Create the pivot entry (ignore if already exists)
        InfluencerListEntry::firstOrCreate(
            [
                'influencer_list_id' => $influencerList->id,
                'influencer_id' => $influencer->id,
            ],
            [
                'added_by' => $request->user()->id,
            ],
        );

        return back();
    }

    /**
     * Update an entry's outreach status or notes.
     */
    public function update(UpdateOutreachStatusRequest $request, string $influencerList, string $entry): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $influencerList = InfluencerList::findOrFail($influencerList);
        $entry = InfluencerListEntry::findOrFail($entry);

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless($entry->influencer_list_id === $influencerList->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $entry->update($request->validated());

        return back();
    }

    /**
     * Remove an influencer from a list.
     */
    public function destroy(Request $request, string $influencerList, string $entry): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $influencerList = InfluencerList::findOrFail($influencerList);
        $entry = InfluencerListEntry::findOrFail($entry);

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless($entry->influencer_list_id === $influencerList->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $entry->delete();

        return back();
    }
}
