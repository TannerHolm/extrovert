<?php

namespace App\Http\Controllers\Influencers;

use App\Actions\Influencers\SaveInfluencerToList;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SaveInfluencerToListRequest;
use App\Http\Requests\Influencers\UpdateOutreachStatusRequest;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InfluencerListEntryController extends Controller
{
    /**
     * Add an influencer to a list.
     */
    public function store(SaveInfluencerToListRequest $request, InfluencerList $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        app(SaveInfluencerToList::class)->handle($influencerList, $request->validated(), $request->user());

        return back();
    }

    /**
     * Update an entry's outreach status or notes.
     */
    public function update(UpdateOutreachStatusRequest $request, InfluencerList $influencerList, InfluencerListEntry $entry): RedirectResponse
    {
        $team = $request->user()->currentTeam;

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
    public function destroy(Request $request, InfluencerList $influencerList, InfluencerListEntry $entry): RedirectResponse
    {
        $team = $request->user()->currentTeam;

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
