<?php

namespace App\Jobs\Shopify;

use App\Models\TeamIntegration;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Subscribes the connected store to the order webhooks attribution needs.
 * Runs after connect; safe to re-run (already-registered topics are skipped).
 */
class RegisterShopifyWebhooks implements ShouldQueue
{
    use Queueable;

    private const TOPICS = ['orders/create', 'orders/updated'];

    public int $tries = 3;

    public function __construct(public TeamIntegration $integration)
    {
        //
    }

    public function handle(): void
    {
        $integration = $this->integration->fresh('team');

        if ($integration === null) {
            return;
        }

        $client = ShopifyClient::for($integration);
        $address = route('webhooks.shopify', ['team' => $integration->team->slug]);

        $registered = collect($client->listWebhooks())
            ->map(fn (array $webhook) => [$webhook['topic'] ?? '', $webhook['address'] ?? '']);

        foreach (self::TOPICS as $topic) {
            if (! $registered->contains(fn (array $pair) => $pair === [$topic, $address])) {
                $client->registerWebhook($topic, $address);
            }
        }
    }
}
