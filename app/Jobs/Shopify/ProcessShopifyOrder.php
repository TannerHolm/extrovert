<?php

namespace App\Jobs\Shopify;

use App\Actions\Shopify\AttributeShopifyOrder;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs order-to-deal matching off the webhook request path so the endpoint
 * can acknowledge Shopify immediately.
 */
class ProcessShopifyOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public Team $team, public array $payload)
    {
        //
    }

    public function handle(AttributeShopifyOrder $attributeOrder): void
    {
        $attributeOrder->handle($this->team, $this->payload);
    }
}
