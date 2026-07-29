<?php

namespace App\Actions\Shopify;

use App\Enums\AttributionMatch;
use App\Models\AttributedOrder;
use App\Models\Deal;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Matches a Shopify order payload to a partner deal and records it. The same
 * logic serves live webhooks and historical backfill, and is idempotent per
 * (team, shopify order id).
 */
class AttributeShopifyOrder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Team $team, array $payload): ?AttributedOrder
    {
        $orderId = $payload['id'] ?? null;

        if ($orderId === null) {
            return null;
        }

        [$deal, $matchedVia] = $this->matchDeal($team, $payload);

        if ($deal === null) {
            return null;
        }

        $total = $payload['current_total_price'] ?? $payload['total_price'] ?? '0';
        $customer = $payload['customer'] ?? null;

        return AttributedOrder::updateOrCreate(
            ['team_id' => $team->id, 'shopify_order_id' => (string) $orderId],
            [
                'deal_id' => $deal->id,
                'order_number' => isset($payload['order_number']) ? (string) $payload['order_number'] : null,
                'total_cents' => (int) round(((float) $total) * 100),
                'currency' => $payload['currency'] ?? 'USD',
                'customer_hash' => $this->customerHash($customer),
                'is_new_customer' => $this->isNewCustomer($customer),
                'matched_via' => $matchedVia,
                'placed_at' => isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : null,
                'raw_payload' => $payload,
            ],
        );
    }

    /**
     * Try the strongest signal first: explicit discount code, then the ref
     * link, then UTM parameters.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: Deal|null, 1: AttributionMatch|null}
     */
    private function matchDeal(Team $team, array $payload): array
    {
        $codes = collect($payload['discount_codes'] ?? [])
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => Str::upper($code));

        if ($codes->isNotEmpty()) {
            $deal = Deal::where('team_id', $team->id)->whereIn('discount_code', $codes)->first();

            if ($deal) {
                return [$deal, AttributionMatch::DiscountCode];
            }
        }

        $landingSite = (string) ($payload['landing_site'] ?? '');
        $noteRef = collect($payload['note_attributes'] ?? [])
            ->first(fn ($attribute) => ($attribute['name'] ?? '') === 'ref')['value'] ?? null;

        $refToken = $noteRef ?? $this->queryParam($landingSite, 'ref');

        if ($refToken) {
            $deal = Deal::where('team_id', $team->id)->where('ref_token', $refToken)->first();

            if ($deal) {
                return [$deal, AttributionMatch::RefLink];
            }
        }

        $utmToken = $this->queryParam($landingSite, 'utm_content');

        if ($utmToken) {
            $deal = Deal::where('team_id', $team->id)->where('ref_token', $utmToken)->first();

            if ($deal) {
                return [$deal, AttributionMatch::Utm];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>|null  $customer
     */
    private function customerHash(?array $customer): ?string
    {
        $identifier = Arr::get($customer ?? [], 'id') ?? Arr::get($customer ?? [], 'email');

        return $identifier !== null ? hash('sha256', (string) $identifier) : null;
    }

    /**
     * Shopify includes the customer's lifetime order count in the payload, so
     * new-vs-returning needs no extra API call or customer table.
     *
     * @param  array<string, mixed>|null  $customer
     */
    private function isNewCustomer(?array $customer): bool
    {
        return (int) Arr::get($customer ?? [], 'orders_count', 1) <= 1;
    }

    private function queryParam(string $url, string $key): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $params);

        $value = $params[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
