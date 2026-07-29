<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Models\TeamIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyClient
{
    public const API_VERSION = '2025-01';

    public function __construct(private readonly TeamIntegration $integration)
    {
        //
    }

    public static function for(TeamIntegration $integration): self
    {
        return new self($integration);
    }

    /**
     * The store's myshopify domain, e.g. freedomfuel.myshopify.com.
     */
    public function shopDomain(): string
    {
        return $this->integration->credentials['shop_domain'];
    }

    /**
     * The storefront base URL affiliate links should point at.
     */
    public function storefrontUrl(): string
    {
        return 'https://'.$this->shopDomain();
    }

    /**
     * Create a percentage-off price rule and a discount code under it.
     *
     * @return array{price_rule_id: string, discount_code_id: string, code: string}
     */
    public function createDiscountCode(string $code, float $percentOff): array
    {
        $priceRule = $this->request()
            ->post($this->url('price_rules.json'), [
                'price_rule' => [
                    'title' => $code,
                    'target_type' => 'line_item',
                    'target_selection' => 'all',
                    'allocation_method' => 'across',
                    'value_type' => 'percentage',
                    'value' => '-'.$percentOff,
                    'customer_selection' => 'all',
                    'starts_at' => now()->toIso8601String(),
                ],
            ]);

        $priceRuleId = (string) $this->json($priceRule, 'price_rule.id');

        $discountCode = $this->request()
            ->post($this->url("price_rules/{$priceRuleId}/discount_codes.json"), [
                'discount_code' => ['code' => $code],
            ]);

        return [
            'price_rule_id' => $priceRuleId,
            'discount_code_id' => (string) $this->json($discountCode, 'discount_code.id'),
            'code' => (string) $this->json($discountCode, 'discount_code.code'),
        ];
    }

    /**
     * Register a webhook subscription. Returns the webhook id.
     */
    public function registerWebhook(string $topic, string $address): string
    {
        $response = $this->request()->post($this->url('webhooks.json'), [
            'webhook' => [
                'topic' => $topic,
                'address' => $address,
                'format' => 'json',
            ],
        ]);

        return (string) $this->json($response, 'webhook.id');
    }

    /**
     * Currently registered webhook subscriptions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWebhooks(): array
    {
        $response = $this->request()->get($this->url('webhooks.json'));

        return $this->json($response, 'webhooks') ?? [];
    }

    /**
     * Fetch a page of orders. Returns the orders plus the page_info cursor for
     * the next page (null when exhausted).
     *
     * @param  array<string, mixed>  $params
     * @return array{orders: array<int, array<string, mixed>>, next_page_info: string|null}
     */
    public function orders(array $params = []): array
    {
        $response = $this->request()->get($this->url('orders.json'), $params);

        $this->ensureOk($response);

        return [
            'orders' => $response->json('orders') ?? [],
            'next_page_info' => $this->nextPageInfo($response),
        ];
    }

    /**
     * Verify a webhook payload signature (base64 HMAC-SHA256 of the raw body
     * using the app's API secret).
     */
    public function verifyWebhookSignature(string $rawBody, string $hmacHeader): bool
    {
        $secret = $this->integration->credentials['api_secret'] ?? null;

        if (! $secret || $hmacHeader === '') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($computed, $hmacHeader);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->integration->credentials['access_token'],
        ])->acceptJson()->timeout(15);
    }

    private function url(string $path): string
    {
        return sprintf('https://%s/admin/api/%s/%s', $this->shopDomain(), self::API_VERSION, $path);
    }

    /**
     * Extract a key from a response, failing loudly on API errors.
     */
    private function json(Response $response, string $key): mixed
    {
        $this->ensureOk($response);

        return $response->json($key);
    }

    private function ensureOk(Response $response): void
    {
        if ($response->failed()) {
            throw new ShopifyApiException(
                "Shopify API request failed ({$response->status()}): ".$response->body(),
            );
        }
    }

    /**
     * Parse the rel="next" cursor out of Shopify's Link pagination header.
     */
    private function nextPageInfo(Response $response): ?string
    {
        $link = $response->header('Link');

        if ($link === '' || ! str_contains($link, 'rel="next"')) {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (str_contains($part, 'rel="next"') && preg_match('/page_info=([^&>]+)/', $part, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
