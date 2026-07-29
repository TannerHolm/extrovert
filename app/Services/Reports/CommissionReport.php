<?php

namespace App\Services\Reports;

use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What's owed to whom for a month: commission_rate × revenue attributed in
 * that month, per commission-bearing deal. Always computed from attributed
 * orders — never hand-entered.
 */
class CommissionReport
{
    /**
     * @return Collection<int, array{
     *     deal_id: int,
     *     partner: string,
     *     handle: string,
     *     commission_rate: float,
     *     revenue_cents: int,
     *     commission_cents: int
     * }>
     */
    public function rows(Team $team, CarbonInterface $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return $team->deals()
            ->whereNotNull('commission_rate')
            ->with('entry.influencer')
            ->withSum(['attributedOrders as month_revenue_cents' => fn ($orders) => $orders
                ->whereBetween('placed_at', [$start, $end]),
            ], 'total_cents')
            ->get()
            ->filter(fn ($deal) => (int) $deal->month_revenue_cents > 0)
            ->map(function ($deal) {
                $revenue = (int) $deal->month_revenue_cents;
                $rate = (float) $deal->commission_rate;
                $influencer = $deal->entry->influencer;

                return [
                    'deal_id' => $deal->id,
                    'partner' => $influencer->display_name ?? $influencer->handle,
                    'handle' => $influencer->handle,
                    'commission_rate' => $rate,
                    'revenue_cents' => $revenue,
                    'commission_cents' => (int) round($revenue * $rate / 100),
                ];
            })
            ->sortByDesc('commission_cents')
            ->values();
    }

    /**
     * The same rows as a CSV document.
     */
    public function csv(Team $team, CarbonInterface $month): string
    {
        $rows = $this->rows($team, $month);

        $lines = [
            ['Partner', 'Handle', 'Deal ID', 'Commission rate (%)', 'Attributed revenue (USD)', 'Commission owed (USD)'],
            ...$rows->map(fn (array $row) => [
                $row['partner'],
                $row['handle'],
                (string) $row['deal_id'],
                number_format($row['commission_rate'], 2, '.', ''),
                number_format($row['revenue_cents'] / 100, 2, '.', ''),
                number_format($row['commission_cents'] / 100, 2, '.', ''),
            ]),
        ];

        $handle = fopen('php://temp', 'r+');

        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
