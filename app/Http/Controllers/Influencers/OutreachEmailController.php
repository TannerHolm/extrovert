<?php

namespace App\Http\Controllers\Influencers;

use App\Actions\Influencers\SendOutreachEmail;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SendOutreachEmailRequest;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use Illuminate\Http\RedirectResponse;
use Throwable;

class OutreachEmailController extends Controller
{
    /**
     * Send an outreach email to a saved influencer and log it to the thread.
     */
    public function store(
        SendOutreachEmailRequest $request,
        InfluencerList $influencerList,
        InfluencerListEntry $entry,
        SendOutreachEmail $sendOutreachEmail,
    ): RedirectResponse {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless($entry->influencer_list_id === $influencerList->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        abort_unless((bool) $entry->influencer->contact_email, 422, __('This influencer has no email address on file.'));

        try {
            $sendOutreachEmail->handle(
                $entry,
                $request->user(),
                $request->validated('subject'),
                $request->validated('body'),
            );
        } catch (Throwable) {
            return back()->withErrors(['body' => __('The email could not be sent. Please try again.')]);
        }

        return back();
    }
}
