<?php

namespace App\Http\Controllers\Teams;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\ConnectIntegrationRequest;
use App\Jobs\Shopify\RegisterShopifyWebhooks;
use App\Models\Team;
use App\Models\TeamIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamIntegrationController extends Controller
{
    /**
     * Connect (or reconnect) a third-party integration for the team.
     */
    public function store(ConnectIntegrationRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $integration = $team->integrations()->updateOrCreate(
            ['provider' => $request->validated('provider')],
            [
                'credentials' => $request->validated('credentials'),
                'connected_at' => now(),
            ],
        );

        if ($integration->provider === IntegrationProvider::Shopify) {
            RegisterShopifyWebhooks::dispatch($integration);
        }

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Disconnect an integration, discarding its stored credentials.
     */
    public function destroy(Request $request, Team $team, TeamIntegration $integration): RedirectResponse
    {
        Gate::authorize('update', $team);

        abort_unless($integration->team_id === $team->id, 404);

        $integration->delete();

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
