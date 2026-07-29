<?php

namespace Tests\Feature\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\TeamRole;
use App\Mail\AgreementForSignature;
use App\Models\Agreement;
use App\Models\Deal;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgreementTest extends TestCase
{
    use RefreshDatabase;

    private function dealFor(Team $team, array $dealAttributes = []): Deal
    {
        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $influencer = Influencer::factory()->create([
            'handle' => '@jane.doe',
            'display_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
        ]);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
        ]);

        return Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'flat_fee_cents' => 75000,
            ...$dealAttributes,
        ]);
    }

    public function test_an_owner_can_draft_an_agreement_from_a_deal(): void
    {
        $user = User::factory()->create();
        $deal = $this->dealFor($user->currentTeam);

        $this->actingAs($user)
            ->post(route('agreements.store', [
                'current_team' => $user->currentTeam->slug,
                'deal' => $deal->id,
            ]))
            ->assertRedirect();

        $agreement = Agreement::firstOrFail();
        $this->assertSame(AgreementStatus::Draft, $agreement->status);
        $this->assertSame('Jane Doe', $agreement->signer_name);
        $this->assertSame('jane@example.com', $agreement->signer_email);
        // Template merge resolved the deal's terms into the body.
        $this->assertStringContainsString('Jane Doe', $agreement->body_markdown);
        $this->assertStringContainsString($user->currentTeam->name, $agreement->body_markdown);
        $this->assertStringContainsString('$750.00', $agreement->body_markdown);
        $this->assertStringNotContainsString('{{', $agreement->body_markdown);

        $this->assertDatabaseHas('agreement_events', [
            'agreement_id' => $agreement->id,
            'event' => 'created',
        ]);
    }

    public function test_a_member_cannot_draft_an_agreement(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $deal = $this->dealFor($team);

        $this->actingAs($member)
            ->post(route('agreements.store', ['current_team' => $team->slug, 'deal' => $deal->id]))
            ->assertForbidden();
    }

    public function test_a_user_cannot_draft_against_another_teams_deal(): void
    {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $deal = $this->dealFor($otherTeam);

        $this->actingAs($user)
            ->post(route('agreements.store', [
                'current_team' => $user->currentTeam->slug,
                'deal' => $deal->id,
            ]))
            ->assertNotFound();
    }

    public function test_a_draft_can_be_edited_but_a_sent_agreement_cannot(): void
    {
        $user = User::factory()->create();
        $deal = $this->dealFor($user->currentTeam);
        $agreement = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
        ]);

        $this->actingAs($user)
            ->patch(route('agreements.update', [
                'current_team' => $user->currentTeam->slug,
                'agreement' => $agreement->id,
            ]), [
                'body_markdown' => '# Updated terms',
                'signer_name' => 'Jane D.',
                'signer_email' => 'jane@example.com',
            ])
            ->assertRedirect();

        $this->assertSame('# Updated terms', $agreement->fresh()->body_markdown);

        $agreement->update(['status' => AgreementStatus::Sent]);

        $this->actingAs($user)
            ->patch(route('agreements.update', [
                'current_team' => $user->currentTeam->slug,
                'agreement' => $agreement->id,
            ]), [
                'body_markdown' => '# Tampered after send',
                'signer_name' => 'Jane D.',
                'signer_email' => 'jane@example.com',
            ])
            ->assertStatus(422);
    }

    public function test_sending_snapshots_the_pdf_and_emails_the_signer(): void
    {
        Storage::fake('local');
        Mail::fake();

        $user = User::factory()->create();
        $deal = $this->dealFor($user->currentTeam);
        $agreement = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'signer_email' => 'jane@example.com',
        ]);

        $this->actingAs($user)
            ->post(route('agreements.send', [
                'current_team' => $user->currentTeam->slug,
                'agreement' => $agreement->id,
            ]))
            ->assertRedirect();

        $agreement->refresh();
        $this->assertSame(AgreementStatus::Sent, $agreement->status);
        $this->assertNotNull($agreement->pdf_path);
        $this->assertNotNull($agreement->content_hash);
        $this->assertNotNull($agreement->expires_at);

        Storage::disk('local')->assertExists($agreement->pdf_path);
        $this->assertSame(
            $agreement->content_hash,
            hash('sha256', Storage::disk('local')->get($agreement->pdf_path)),
        );

        Mail::assertSent(AgreementForSignature::class, fn (AgreementForSignature $mail) => $mail->hasTo('jane@example.com'));

        $this->assertDatabaseHas('agreement_events', [
            'agreement_id' => $agreement->id,
            'event' => 'sent',
        ]);
    }

    public function test_voiding_stops_the_agreement(): void
    {
        $user = User::factory()->create();
        $deal = $this->dealFor($user->currentTeam);
        $agreement = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'status' => AgreementStatus::Sent,
        ]);

        $this->actingAs($user)
            ->post(route('agreements.void', [
                'current_team' => $user->currentTeam->slug,
                'agreement' => $agreement->id,
            ]))
            ->assertRedirect();

        $this->assertSame(AgreementStatus::Voided, $agreement->fresh()->status);
    }

    public function test_expired_agreements_are_voided_by_the_scheduled_command(): void
    {
        $user = User::factory()->create();
        $deal = $this->dealFor($user->currentTeam);
        $expired = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'status' => AgreementStatus::Sent,
            'expires_at' => now()->subDay(),
        ]);
        $active = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'status' => AgreementStatus::Sent,
            'expires_at' => now()->addWeek(),
        ]);

        $this->artisan('extrovert:expire-agreements')->assertSuccessful();

        $this->assertSame(AgreementStatus::Voided, $expired->fresh()->status);
        $this->assertSame(AgreementStatus::Sent, $active->fresh()->status);
    }
}
