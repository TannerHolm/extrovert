<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Enums\OutreachStatus;
use App\Models\Deal;
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

        // Deals strip: active deals, drafts awaiting agreement, and overdue deliverables.
        // Overdue detection reads the deliverables json, so it runs over the (small)
        // set of active deals rather than in SQL.
        $activeDeals = $team->deals()->whereIn('status', DealStatus::active())->get();

        $dealMetrics = [
            'active' => $activeDeals->count(),
            'awaiting_agreement' => $team->deals()->where('status', DealStatus::Draft)->count(),
            'overdue_deliverables' => $activeDeals->sum(fn (Deal $deal) => count($deal->overdueDeliverables())),
            'attributed_revenue_cents' => (int) $team->attributedOrders()->sum('total_cents'),
        ];

        // Spend vs revenue per deal, for the ROI scatter. Capped — the
        // dashboard is a glance, the reports page is the full picture.
        $roiPoints = $team->deals()
            ->with('entry.influencer')
            ->withSum('attributedOrders as revenue_cents', 'total_cents')
            ->limit(100)
            ->get()
            ->map(function (Deal $deal) {
                $revenue = (int) $deal->revenue_cents;
                $commission = $deal->commission_rate !== null
                    ? (int) round($revenue * ((float) $deal->commission_rate) / 100)
                    : 0;
                $spend = (int) ($deal->product_value_cents ?? 0)
                    + (int) ($deal->flat_fee_cents ?? 0)
                    + $commission;
                $influencer = $deal->entry->influencer;

                return [
                    'label' => $influencer->display_name ?? $influencer->handle,
                    'spend_cents' => $spend,
                    'revenue_cents' => $revenue,
                ];
            })
            ->filter(fn (array $point) => $point['spend_cents'] > 0 || $point['revenue_cents'] > 0)
            ->values();

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
            'dealMetrics' => $dealMetrics,
            'statusCounts' => $statusCounts,
            'recentEntries' => $recentEntries,
            'roiPoints' => $roiPoints,
        ]);
    }
}
