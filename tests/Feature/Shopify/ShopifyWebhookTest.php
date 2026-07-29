<?php

namespace Tests\Feature\Shopify;

use App\Jobs\Shopify\ProcessShopifyOrder;
use App\Models\Team;
use App\Models\TeamIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedHeaders(array $payload, string $secret): array
    {
        return [
            'X-Shopify-Hmac-Sha256' => base64_encode(hash_hmac('sha256', json_encode($payload), $secret, true)),
        ];
    }

    public function test_a_correctly_signed_order_webhook_is_accepted_and_queued(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        TeamIntegration::factory()->create(['team_id' => $team->id]);

        $payload = ['id' => 123456, 'total_price' => '99.00'];

        $this->postJson(
            route('webhooks.shopify', ['team' => $team->slug]),
            $payload,
            $this->signedHeaders($payload, 'shpss_test_secret'),
        )->assertOk();

        Queue::assertPushed(ProcessShopifyOrder::class, fn (ProcessShopifyOrder $job) => $job->team->is($team)
            && $job->payload['id'] === 123456);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        TeamIntegration::factory()->create(['team_id' => $team->id]);

        $payload = ['id' => 123456];

        $this->postJson(
            route('webhooks.shopify', ['team' => $team->slug]),
            $payload,
            $this->signedHeaders($payload, 'wrong_secret'),
        )->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        TeamIntegration::factory()->create(['team_id' => $team->id]);

        $this->postJson(route('webhooks.shopify', ['team' => $team->slug]), ['id' => 1])
            ->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_webhooks_for_teams_without_a_shopify_integration_are_not_found(): void
    {
        Queue::fake();

        $team = Team::factory()->create();

        $this->postJson(route('webhooks.shopify', ['team' => $team->slug]), ['id' => 1])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
