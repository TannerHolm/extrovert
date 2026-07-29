<?php

namespace App\Jobs\Shopify;

use App\Enums\IntegrationProvider;
use App\Models\Deal;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Gives an agreed deal its attribution handles: a partner ref token (local)
 * and a Shopify discount code (via the Admin API) when a store is connected.
 */
class ProvisionDealAttribution implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Deal $deal)
    {
        //
    }

    public function handle(): void
    {
        $deal = $this->deal->fresh(['team', 'entry.influencer']);

        if ($deal === null) {
            return;
        }

        if ($deal->ref_token === null) {
            $deal->update(['ref_token' => Str::lower(Str::random(16))]);
        }

        if ($deal->discount_code !== null) {
            return;
        }

        $integration = $deal->team->integrations()
            ->where('provider', IntegrationProvider::Shopify)
            ->first();

        if ($integration === null) {
            return;
        }

        $percent = (float) ($integration->settings['discount_percent'] ?? 10);
        $code = $this->buildCode($deal, $percent);

        $created = ShopifyClient::for($integration)->createDiscountCode($code, $percent);

        $deal->update([
            'discount_code' => $created['code'],
            'shopify_price_rule_id' => $created['price_rule_id'],
            'shopify_discount_code_id' => $created['discount_code_id'],
        ]);
    }

    /**
     * Build a partner code like FREEDOM-JANE10, unique within the team.
     */
    private function buildCode(Deal $deal, float $percent): string
    {
        $brand = Str::of($deal->team->name)->before(' ')->slug('')->upper()->limit(10, '');
        $handle = Str::of($deal->entry->influencer->handle)->replace('@', '')->slug('')->upper()->limit(12, '');
        $suffix = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        $code = "{$brand}-{$handle}{$suffix}";

        $taken = Deal::where('team_id', $deal->team_id)
            ->where('discount_code', $code)
            ->whereKeyNot($deal->id)
            ->exists();

        return $taken ? "{$code}-{$deal->id}" : $code;
    }
}
