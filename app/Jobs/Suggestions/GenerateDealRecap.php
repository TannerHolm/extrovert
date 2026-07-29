<?php

namespace App\Jobs\Suggestions;

use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Models\Deal;
use App\Services\AI\Claude;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Summarizes a completed deal — spend, revenue, ROI, content — into one
 * reviewable paragraph that feeds the "who do we re-book next quarter"
 * decision. Stats are computed; AI only turns them into prose.
 */
class GenerateDealRecap implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Deal $deal)
    {
        //
    }

    public function handle(Claude $claude): void
    {
        $deal = $this->deal->fresh(['entry.influencer', 'team', 'attributedOrders']);

        if ($deal === null) {
            return;
        }

        $duplicate = $deal->suggestedActions()
            ->where('type', SuggestedActionType::DealRecap)
            ->where('status', SuggestedActionStatus::Pending)
            ->exists();

        if ($duplicate) {
            return;
        }

        $influencer = $deal->entry->influencer;
        $name = $influencer->display_name ?? $influencer->handle;

        $revenue = (int) $deal->attributedOrders->sum('total_cents');
        $orders = $deal->attributedOrders->count();
        $newCustomers = $deal->attributedOrders->where('is_new_customer', true)->count();
        $commission = $deal->commission_rate !== null
            ? (int) round($revenue * ((float) $deal->commission_rate) / 100)
            : 0;
        $spend = (int) ($deal->product_value_cents ?? 0) + (int) ($deal->flat_fee_cents ?? 0) + $commission;
        $posts = collect($deal->deliverables ?? [])
            ->filter(fn (array $deliverable) => ! empty($deliverable['posted_url']))
            ->count();
        $roi = $spend > 0 ? round($revenue / $spend, 2) : null;

        $money = fn (int $cents) => '$'.number_format($cents / 100, 2);

        $fallback = sprintf(
            '%s: %d post(s) delivered. Spend %s, attributed revenue %s across %d order(s) (%d new customers)%s.',
            $name,
            $posts,
            $money($spend),
            $money($revenue),
            $orders,
            $newCustomers,
            $roi !== null ? ", ROI {$roi}×" : '',
        );

        $stats = [
            'partner' => $name,
            'handle' => $influencer->handle,
            'platform' => $influencer->platform->label(),
            'posts_delivered' => $posts,
            'spend' => $money($spend),
            'attributed_revenue' => $money($revenue),
            'orders' => $orders,
            'new_customers' => $newCustomers,
            'roi' => $roi,
        ];

        $recap = $claude->draft(
            'You write one-paragraph performance recaps of completed influencer deals for a brand\'s internal notes. '
            .'Use ONLY the numbers provided — never invent figures. End with a one-sentence re-book recommendation. '
            .'Plain text, max 90 words.',
            json_encode($stats),
        );

        $deal->suggestedActions()->create([
            'team_id' => $deal->team_id,
            'type' => SuggestedActionType::DealRecap,
            'payload' => [
                'recap' => $recap ?? $fallback,
                'stats' => $stats,
                'ai' => $recap !== null,
            ],
        ]);
    }
}
