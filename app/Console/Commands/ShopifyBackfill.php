<?php

namespace App\Console\Commands;

use App\Actions\Shopify\AttributeShopifyOrder;
use App\Enums\IntegrationProvider;
use App\Models\Team;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Console\Command;

/**
 * Imports historical orders after a store connects, so dashboards aren't
 * empty on day one. Idempotent — re-running updates rather than duplicates.
 */
class ShopifyBackfill extends Command
{
    protected $signature = 'extrovert:shopify-backfill
        {team : The team slug to backfill}
        {--months=3 : How many months of order history to import}';

    protected $description = 'Import historical Shopify orders and attribute them to partner deals';

    public function handle(AttributeShopifyOrder $attributeOrder): int
    {
        $team = Team::where('slug', $this->argument('team'))->first();

        if ($team === null) {
            $this->error("Team '{$this->argument('team')}' not found.");

            return self::FAILURE;
        }

        $integration = $team->integrations()
            ->where('provider', IntegrationProvider::Shopify)
            ->first();

        if ($integration === null) {
            $this->error("Team '{$team->slug}' has no Shopify integration connected.");

            return self::FAILURE;
        }

        $client = ShopifyClient::for($integration);

        $params = [
            'status' => 'any',
            'limit' => 250,
            'created_at_min' => now()->subMonths((int) $this->option('months'))->toIso8601String(),
        ];

        $seen = 0;
        $attributed = 0;

        do {
            $page = $client->orders($params);

            foreach ($page['orders'] as $order) {
                $seen++;

                if ($attributeOrder->handle($team, $order) !== null) {
                    $attributed++;
                }
            }

            // Cursor pagination: subsequent requests may only carry limit + page_info.
            $params = $page['next_page_info'] !== null
                ? ['limit' => 250, 'page_info' => $page['next_page_info']]
                : null;
        } while ($params !== null);

        $integration->update(['last_synced_at' => now()]);

        $this->info("Scanned {$seen} order(s); attributed {$attributed} to partner deals.");

        return self::SUCCESS;
    }
}
