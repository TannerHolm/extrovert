<?php

namespace Tests\Feature\Agreements;

use App\Actions\Agreements\SendAgreement;
use App\Enums\AgreementStatus;
use App\Enums\DealStatus;
use App\Jobs\Shopify\ProvisionDealAttribution;
use App\Mail\AgreementExecuted;
use App\Models\Agreement;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A sent agreement with a real snapshot on the fake disk.
     */
    private function sentAgreement(): Agreement
    {
        Storage::fake('local');
        Mail::fake();

        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => DealStatus::Draft,
        ]);

        $agreement = Agreement::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'signer_name' => 'Jane Doe',
            'signer_email' => 'jane@example.com',
            'created_by' => $user->id,
        ]);

        app(SendAgreement::class)->handle($agreement);
        Mail::fake(); // reset: the tests below only care about post-sign mail

        return $agreement->refresh();
    }

    public function test_the_sign_page_renders_and_marks_the_agreement_viewed(): void
    {
        $agreement = $this->sentAgreement();

        $this->get(route('sign.show', ['token' => $agreement->sign_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('sign/Show')
                ->where('agreement.signable', true)
                ->where('agreement.signer_name', 'Jane Doe'));

        $agreement->refresh();
        $this->assertSame(AgreementStatus::Viewed, $agreement->status);
        $this->assertNotNull($agreement->viewed_at);
        $this->assertDatabaseHas('agreement_events', [
            'agreement_id' => $agreement->id,
            'event' => 'viewed',
        ]);
    }

    public function test_signing_executes_the_agreement_and_advances_the_deal(): void
    {
        Queue::fake();

        $agreement = $this->sentAgreement();

        $this->post(route('sign.submit', ['token' => $agreement->sign_token]), [
            'typed_name' => 'Jane Doe',
            'consent' => true,
        ])->assertRedirect();

        $agreement->refresh();
        $this->assertSame(AgreementStatus::Signed, $agreement->status);
        $this->assertNotNull($agreement->signed_at);
        $this->assertSame('Jane Doe', $agreement->signature_payload['typed_name']);
        $this->assertTrue($agreement->signature_payload['consented_to_electronic_signature']);
        $this->assertNotNull($agreement->signed_ip);

        // Executed copy exists and both parties were emailed.
        Storage::disk('local')->assertExists($agreement->signed_pdf_path);
        Mail::assertSent(AgreementExecuted::class, 2);

        // Paper signed → deal agreed, attribution provisioning queued.
        $this->assertSame(DealStatus::Agreed, $agreement->deal->status);
        Queue::assertPushed(ProvisionDealAttribution::class);

        $this->assertDatabaseHas('agreement_events', [
            'agreement_id' => $agreement->id,
            'event' => 'signed',
        ]);
    }

    public function test_consent_is_required_to_sign(): void
    {
        $agreement = $this->sentAgreement();

        $this->post(route('sign.submit', ['token' => $agreement->sign_token]), [
            'typed_name' => 'Jane Doe',
            'consent' => false,
        ])->assertSessionHasErrors(['consent']);

        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
    }

    public function test_an_expired_link_cannot_be_signed(): void
    {
        $agreement = $this->sentAgreement();
        $agreement->update(['expires_at' => now()->subDay()]);

        $this->post(route('sign.submit', ['token' => $agreement->sign_token]), [
            'typed_name' => 'Jane Doe',
            'consent' => true,
        ])->assertStatus(410);
    }

    public function test_a_voided_agreement_cannot_be_signed(): void
    {
        $agreement = $this->sentAgreement();
        $agreement->update(['status' => AgreementStatus::Voided]);

        $this->post(route('sign.submit', ['token' => $agreement->sign_token]), [
            'typed_name' => 'Jane Doe',
            'consent' => true,
        ])->assertStatus(410);
    }

    public function test_a_tampered_snapshot_fails_integrity_verification(): void
    {
        $agreement = $this->sentAgreement();

        // Someone swaps the stored snapshot after send.
        Storage::disk('local')->put($agreement->pdf_path, 'tampered-bytes');

        $this->post(route('sign.submit', ['token' => $agreement->sign_token]), [
            'typed_name' => 'Jane Doe',
            'consent' => true,
        ])->assertStatus(409);

        $this->assertNotSame(AgreementStatus::Signed, $agreement->fresh()->status);
    }

    public function test_the_signer_can_decline(): void
    {
        $agreement = $this->sentAgreement();

        $this->post(route('sign.decline', ['token' => $agreement->sign_token]))
            ->assertRedirect();

        $this->assertSame(AgreementStatus::Declined, $agreement->fresh()->status);
        $this->assertDatabaseHas('agreement_events', [
            'agreement_id' => $agreement->id,
            'event' => 'declined',
        ]);
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->get(route('sign.show', ['token' => str_repeat('x', 64)]))
            ->assertNotFound();
    }
}
