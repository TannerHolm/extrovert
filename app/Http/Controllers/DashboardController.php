<?php

namespace App\Http\Controllers;

use App\Enums\OutreachStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        // Count entries per outreach status across all lists in a single grouped query.
        $countsByStatus = $team->influencerLists()
            ->join('influencer_list_entries', 'influencer_lists.id', '=', 'influencer_list_entries.influencer_list_id')
            ->groupBy('influencer_list_entries.outreach_status')
            ->selectRaw('influencer_list_entries.outreach_status as status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        $statusCounts = collect(OutreachStatus::cases())->map(fn (OutreachStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
            'count' => (int) $countsByStatus->get($status->value, 0),
        ])->all();

        $totalInfluencers = (int) $countsByStatus->sum();

        $activeOutreach = (int) collect([
            OutreachStatus::Contacted,
            OutreachStatus::Replied,
            OutreachStatus::Negotiating,
        ])->sum(fn (OutreachStatus $status) => $countsByStatus->get($status->value, 0));

        $confirmedPartners = (int) $countsByStatus->get(OutreachStatus::Confirmed->value, 0);

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
            ->map(function ($entry) {
                $status = OutreachStatus::tryFrom($entry->outreach_status) ?? OutreachStatus::None;

                return [
                    'id' => $entry->id,
                    'outreach_status' => $status->value,
                    'outreach_status_label' => $status->label(),
                    'outreach_status_color' => $status->color(),
                    'created_at' => $entry->created_at,
                    'display_name' => $entry->display_name,
                    'handle' => $entry->handle,
                    'avatar_url' => $entry->avatar_url,
                    'platform' => $entry->platform,
                    'list_name' => $entry->list_name,
                    'added_by_name' => $entry->added_by_name,
                ];
            });

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
