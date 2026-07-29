<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Jobs\Shopify\RegisterShopifyWebhooks;
use App\Models\Team;
use App\Models\TeamIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TeamIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_connect_a_shopify_integration(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('teams.integrations.store', ['team' => $team->slug]), [
                'provider' => 'shopify',
                'credentials' => [
                    'shop_domain' => 'freedomfuel.myshopify.com',
                    'access_token' => 'shpat_secret_value',
                    'api_secret' => 'shpss_signing_secret',
                ],
            ])
            ->assertRedirect(route('teams.edit', ['team' => $team->slug]));

        $integration = TeamIntegration::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('freedomfuel.myshopify.com', $integration->credentials['shop_domain']);
        $this->assertNotNull($integration->connected_at);

        // Credentials are encrypted at rest — the raw column must not leak the token.
        $raw = DB::table('team_integrations')->where('id', $integration->id)->value('credentials');
        $this->assertStringNotContainsString('shpat_secret_value', $raw);

        // Connecting a store subscribes it to order webhooks.
        Queue::assertPushed(RegisterShopifyWebhooks::class);
    }

    public function test_reconnecting_replaces_credentials_instead_of_duplicating(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = $user->currentTeam;
        TeamIntegration::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('teams.integrations.store', ['team' => $team->slug]), [
                'provider' => 'shopify',
                'credentials' => [
                    'shop_domain' => 'new-shop.myshopify.com',
                    'access_token' => 'shpat_new_token',
                    'api_secret' => 'shpss_new_secret',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('team_integrations', 1);
        $this->assertSame(
            'new-shop.myshopify.com',
            TeamIntegration::firstOrFail()->credentials['shop_domain'],
        );
    }

    public function test_credential_fields_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('teams.integrations.store', ['team' => $user->currentTeam->slug]), [
                'provider' => 'shopify',
                'credentials' => ['shop_domain' => 'shop.myshopify.com'],
            ])
            ->assertSessionHasErrors(['credentials.access_token']);
    }

    public function test_a_member_cannot_connect_an_integration(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->post(route('teams.integrations.store', ['team' => $team->slug]), [
                'provider' => 'shopify',
                'credentials' => [
                    'shop_domain' => 'shop.myshopify.com',
                    'access_token' => 'shpat_x',
                    'api_secret' => 'shpss_x',
                ],
            ])
            ->assertForbidden();
    }

    public function test_an_owner_can_disconnect_an_integration(): void
    {
        $user = User::factory()->create();
        $integration = TeamIntegration::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->delete(route('teams.integrations.destroy', [
                'team' => $user->currentTeam->slug,
                'integration' => $integration->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('team_integrations', ['id' => $integration->id]);
    }

    public function test_an_owner_cannot_disconnect_another_teams_integration(): void
    {
        $user = User::factory()->create();
        $otherIntegration = TeamIntegration::factory()->create();

        $this->actingAs($user)
            ->delete(route('teams.integrations.destroy', [
                'team' => $user->currentTeam->slug,
                'integration' => $otherIntegration->id,
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('team_integrations', ['id' => $otherIntegration->id]);
    }
}
