<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Jobs\Shopify\ProcessShopifyOrder;
use App\Models\Team;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    /**
     * Receive an order webhook. Verifies the HMAC signature against the
     * team's stored API secret, then queues matching — no work happens
     * inline.
     */
    public function __invoke(Request $request, Team $team): JsonResponse
    {
        $integration = $team->integrations()
            ->where('provider', IntegrationProvider::Shopify)
            ->first();

        abort_unless($integration !== null, 404);

        $signatureValid = ShopifyClient::for($integration)->verifyWebhookSignature(
            $request->getContent(),
            $request->header('X-Shopify-Hmac-Sha256', ''),
        );

        abort_unless($signatureValid, 401);

        $payload = $request->json()->all();

        if ($payload !== []) {
            ProcessShopifyOrder::dispatch($team, $payload);
        }

        return response()->json(['ok' => true]);
    }
}
