<?php

namespace App\Http\Controllers;

use App\Enums\OutreachStatus;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $team = Team::where('slug', $request->route('current_team'))->firstOrFail();

        $listIds = $team->influencerLists()->pluck('id');

        // Count entries per outreach status across all lists
        $statusCounts = [];
        foreach (OutreachStatus::cases() as $status) {
            $statusCounts[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'count' => $team->influencerLists()
                    ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
                    ->where('influencer_list_entries.outreach_status', $status->value)
                    ->count(),
            ];
        }

        $totalInfluencers = $team->influencerLists()
            ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
            ->count();

        $activeOutreach = $team->influencerLists()
            ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
            ->whereIn('influencer_list_entries.outreach_status', ['contacted', 'replied', 'negotiating'])
            ->count();

        $confirmedPartners = $team->influencerLists()
            ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
            ->where('influencer_list_entries.outreach_status', 'confirmed')
            ->count();

        // Recent entries with influencer data
        $recentEntries = $team->influencerLists()
            ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
            ->join('influencers', 'influencer_list_entries.influencer_id', '=', 'influencers.id')
            ->leftJoin('users', 'influencer_list_entries.added_by', '=', 'users.id')
            ->select(
                'influencer_list_entries.id',
                'influencer_list_entries.outreach_status',
                'influencer_list_entries.created_at',
                'influencers.display_name',
                'influencers.handle',
                'influencers.avatar_url',
                'influencers.platform',
                'influencer_lists.name as list_name',
                'users.name as added_by_name',
            )
            ->orderByDesc('influencer_list_entries.created_at')
            ->limit(10)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'outreach_status' => $entry->outreach_status,
                'outreach_status_label' => OutreachStatus::from($entry->outreach_status)->label(),
                'outreach_status_color' => OutreachStatus::from($entry->outreach_status)->color(),
                'created_at' => $entry->created_at,
                'display_name' => $entry->display_name,
                'handle' => $entry->handle,
                'avatar_url' => $entry->avatar_url,
                'platform' => $entry->platform,
                'list_name' => $entry->list_name,
                'added_by_name' => $entry->added_by_name,
            ]);

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_influencers' => $totalInfluencers,
                'active_outreach' => $activeOutreach,
                'confirmed_partners' => $confirmedPartners,
            ],
            'statusCounts' => $statusCounts,
            'recentEntries' => $recentEntries,
        ]);
    }
}
