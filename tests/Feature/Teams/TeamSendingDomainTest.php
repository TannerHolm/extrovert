<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSendingDomainTest extends TestCase
{
    use RefreshDatabase;

    private function teamWith(User $owner): Team
    {
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        return $team;
    }

    public function test_an_owner_can_set_the_sending_identity(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWith($user);

        $this->actingAs($user)
            ->patch(route('teams.sending.update', $team), [
                'sending_from_email' => 'outreach@freedomfuel.us',
                'sending_from_name' => 'Freedom Fuel',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'sending_from_email' => 'outreach@freedomfuel.us',
            'sending_from_name' => 'Freedom Fuel',
            'sending_domain_verified_at' => null,
        ]);
    }

    public function test_changing_the_from_email_clears_verification(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWith($user);
        $team->update(['sending_from_email' => 'outreach@freedomfuel.us', 'sending_domain_verified_at' => now()]);

        $this->actingAs($user)
            ->patch(route('teams.sending.update', $team), [
                'sending_from_email' => 'hello@freedomfuel.us',
                'sending_from_name' => 'Freedom Fuel',
            ])
            ->assertRedirect();

        $this->assertNull($team->fresh()->sending_domain_verified_at);
    }

    public function test_updating_the_same_email_keeps_verification(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWith($user);
        $team->update(['sending_from_email' => 'outreach@freedomfuel.us', 'sending_domain_verified_at' => now()]);

        $this->actingAs($user)
            ->patch(route('teams.sending.update', $team), [
                'sending_from_email' => 'outreach@freedomfuel.us',
                'sending_from_name' => 'Freedom Fuel Team',
            ]);

        $this->assertNotNull($team->fresh()->sending_domain_verified_at);
    }

    public function test_a_member_cannot_update_the_sending_identity(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->teamWith($owner);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->patch(route('teams.sending.update', $team), [
                'sending_from_email' => 'sneaky@evil.test',
            ])
            ->assertForbidden();
    }

    public function test_sending_from_resolves_to_the_verified_domain_or_the_app_default(): void
    {
        config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Extrovert']);

        $team = Team::factory()->create();
        $this->assertSame('hello@example.com', $team->sendingFrom()['address']);

        // Set but unverified -> still falls back to the app default.
        $team->update(['sending_from_email' => 'outreach@freedomfuel.us', 'sending_from_name' => 'Freedom Fuel']);
        $this->assertSame('hello@example.com', $team->fresh()->sendingFrom()['address']);

        // Verified -> uses the team domain.
        $team->update(['sending_domain_verified_at' => now()]);
        $this->assertSame('outreach@freedomfuel.us', $team->fresh()->sendingFrom()['address']);
    }

    public function test_the_verify_command_marks_the_domain_verified(): void
    {
        $team = Team::factory()->create(['slug' => 'freedom-fuel', 'sending_from_email' => 'outreach@freedomfuel.us']);

        $this->artisan('team:verify-sending-domain', ['team' => 'freedom-fuel'])
            ->assertSuccessful();

        $this->assertTrue($team->fresh()->hasVerifiedSendingDomain());
    }
}
