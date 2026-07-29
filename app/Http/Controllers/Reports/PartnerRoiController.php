<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\Reports\CommissionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerRoiController extends Controller
{
    /**
     * Per-deal ROI: spend (product + fee + accrued commission) against
     * attributed revenue. Sorting happens client-side; the money math lives
     * here so the frontend never re-derives it.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $deals = $team->deals()
            ->with('entry.influencer')
            ->withSum('attributedOrders as revenue_cents', 'total_cents')
            ->withCount([
                'attributedOrders as orders_count',
                'attributedOrders as new_customers_count' => fn ($orders) => $orders->where('is_new_customer', true),
            ])
            ->get()
            ->map(function (Deal $deal) {
                $revenue = (int) $deal->revenue_cents;
                $commission = $deal->commission_rate !== null
                    ? (int) round($revenue * ((float) $deal->commission_rate) / 100)
                    : 0;
                $spend = (int) ($deal->product_value_cents ?? 0)
                    + (int) ($deal->flat_fee_cents ?? 0)
                    + $commission;

                $posts = collect($deal->deliverables ?? [])
                    ->filter(fn (array $deliverable) => ! empty($deliverable['posted_url']))
                    ->count();
                $newCustomers = (int) $deal->new_customers_count;
                $influencer = $deal->entry->influencer;

                return [
                    'deal_id' => $deal->id,
                    'partner' => $influencer->display_name ?? $influencer->handle,
                    'handle' => $influencer->handle,
                    'platform' => $influencer->platform->value,
                    'avatar_url' => $influencer->avatar_url,
                    'status' => $deal->status->value,
                    'status_label' => $deal->status->label(),
                    'status_color' => $deal->status->color(),
                    'spend_cents' => $spend,
                    'commission_cents' => $commission,
                    'revenue_cents' => $revenue,
                    'orders' => (int) $deal->orders_count,
                    'new_customers' => $newCustomers,
                    'cost_per_new_customer_cents' => $newCustomers > 0 ? (int) round($spend / $newCustomers) : null,
                    'posts' => $posts,
                    'revenue_per_post_cents' => $posts > 0 ? (int) round($revenue / $posts) : null,
                    'roi' => $spend > 0 ? round($revenue / $spend, 2) : null,
                ];
            })
            ->sortByDesc('revenue_cents')
            ->values();

        return Inertia::render('reports/PartnerRoi', [
            'deals' => $deals,
            'totals' => [
                'spend_cents' => $deals->sum('spend_cents'),
                'revenue_cents' => $deals->sum('revenue_cents'),
                'orders' => $deals->sum('orders'),
                'new_customers' => $deals->sum('new_customers'),
            ],
            'month' => now()->format('Y-m'),
        ]);
    }

    /**
     * Commission CSV for a month (defaults to the current one).
     */
    public function commissionsCsv(Request $request, CommissionReport $report): StreamedResponse
    {
        $team = $request->user()->currentTeam;

        $month = $request->query('month');
        $month = $month !== null
            ? Carbon::createFromFormat('Y-m', $month)
            : now();

        abort_unless($month !== null, 422);

        $csv = $report->csv($team, $month);
        $filename = "commissions-{$team->slug}-{$month->format('Y-m')}.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }
}
