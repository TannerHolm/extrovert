<?php

namespace App\Http\Controllers\Influencers;

use App\Enums\OutreachStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SaveInfluencerListRequest;
use App\Models\InfluencerList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfluencerListController extends Controller
{
    /**
     * Display all influencer lists for the current team.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('influencers/Lists', [
            'lists' => $team->influencerLists()
                ->withCount('entries')
                ->orderBy('name')
                ->get()
                ->map(fn (InfluencerList $list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'entries_count' => $list->entries_count,
                    'created_at' => $list->created_at->toISOString(),
                    'updated_at' => $list->updated_at->toISOString(),
                ]),
        ]);
    }

    /**
     * Store a new influencer list.
     */
    public function store(SaveInfluencerListRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $list = $team->influencerLists()->create($request->validated());

        return to_route('influencers.lists.show', [
            'current_team' => $team->slug,
            'influencerList' => $list->id,
        ]);
    }

    /**
     * Display a specific influencer list with its entries.
     */
    public function show(Request $request, InfluencerList $influencerList): Response
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);

        $status = $request->input('status');

        $entries = $influencerList->entries()
            ->with('influencer', 'addedBy')
            ->when($status && $status !== 'all', fn ($q) => $q->where('outreach_status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn ($entry) => [
                'id' => $entry->id,
                'outreach_status' => $entry->outreach_status->value,
                'outreach_status_label' => $entry->outreach_status->label(),
                'outreach_status_color' => $entry->outreach_status->color(),
                'notes' => $entry->notes,
                'added_by' => $entry->addedBy ? [
                    'id' => $entry->addedBy->id,
                    'name' => $entry->addedBy->name,
                ] : null,
                'created_at' => $entry->created_at->toISOString(),
                'influencer' => [
                    'id' => $entry->influencer->id,
                    'platform' => $entry->influencer->platform->value,
                    'platform_label' => $entry->influencer->platform->label(),
                    'handle' => $entry->influencer->handle,
                    'profile_url' => $entry->influencer->profile_url,
                    'display_name' => $entry->influencer->display_name,
                    'avatar_url' => $entry->influencer->avatar_url,
                    'follower_count' => $entry->influencer->follower_count,
                    'engagement_rate' => $entry->influencer->engagement_rate,
                    'contact_email' => $entry->influencer->contact_email,
                    'latest_activity_at' => $entry->influencer->latest_activity_at?->toISOString(),
                ],
            ]);

        return Inertia::render('influencers/ListShow', [
            'list' => [
                'id' => $influencerList->id,
                'name' => $influencerList->name,
                'description' => $influencerList->description,
            ],
            'entries' => $entries,
            'filters' => ['status' => $status ?? 'all'],
            'outreachStatuses' => collect(OutreachStatus::cases())->map(fn (OutreachStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
        ]);
    }

    /**
     * Update an influencer list.
     */
    public function update(SaveInfluencerListRequest $request, InfluencerList $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $influencerList->update($request->validated());

        return back();
    }

    /**
     * Delete an influencer list.
     */
    public function destroy(Request $request, InfluencerList $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $influencerList->delete();

        return to_route('influencers.lists.index', ['current_team' => $team->slug]);
    }
}
