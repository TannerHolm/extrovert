<?php

namespace Tests\Feature\Influencers;

use App\Enums\OutreachStatus;
use App\Enums\TeamRole;
use App\Mail\OutreachEmail;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutreachEmailTest extends TestCase
{
    use RefreshDatabase;

    private function entryFor(User $user, ?string $email = 'creator@example.com', OutreachStatus $status = OutreachStatus::None): InfluencerListEntry
    {
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $influencer = Influencer::factory()->create(['contact_email' => $email]);

        return InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => $influencer->id,
            'outreach_status' => $status,
        ]);
    }

    private function sendRoute(User $user, InfluencerListEntry $entry): string
    {
        return route('influencers.entries.emails.store', [
            'current_team' => $user->currentTeam->slug,
            'influencerList' => $entry->influencer_list_id,
            'entry' => $entry->id,
        ]);
    }

    public function test_an_owner_can_send_and_log_an_outreach_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $this->actingAs($user)
            ->post($this->sendRoute($user, $entry), [
                'subject' => 'Partnership?',
                'body' => 'Hi there, let us collaborate.',
            ])
            ->assertRedirect();

        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail) => $mail->hasTo('creator@example.com')
            && $mail->hasReplyTo($user->email));

        $this->assertDatabaseHas('outreach_messages', [
            'influencer_list_entry_id' => $entry->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'to_email' => 'creator@example.com',
            'subject' => 'Partnership?',
            'status' => 'sent',
        ]);

        // A fresh entry advances into the pipeline once contacted.
        $this->assertSame(OutreachStatus::Contacted, $entry->fresh()->outreach_status);
    }

    public function test_sending_does_not_downgrade_an_existing_status(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $entry = $this->entryFor($user, status: OutreachStatus::Negotiating);

        $this->actingAs($user)
            ->post($this->sendRoute($user, $entry), ['subject' => 'Following up', 'body' => 'Any thoughts?'])
            ->assertRedirect();

        $this->assertSame(OutreachStatus::Negotiating, $entry->fresh()->outreach_status);
    }

    public function test_sending_requires_a_subject_and_body(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $this->actingAs($user)
            ->post($this->sendRoute($user, $entry), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        Mail::assertNothingSent();
    }

    public function test_cannot_email_an_influencer_without_an_address(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $entry = $this->entryFor($user, email: null);

        $this->actingAs($user)
            ->post($this->sendRoute($user, $entry), ['subject' => 'Hi', 'body' => 'Hello'])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_a_member_cannot_send_outreach_email(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => Influencer::factory()->create(['contact_email' => 'creator@example.com'])->id,
        ]);

        $this->actingAs($member)
            ->post(route('influencers.entries.emails.store', [
                'current_team' => $team->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
            ]), ['subject' => 'Hi', 'body' => 'Hello'])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_cannot_email_into_another_teams_list(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $otherList = InfluencerList::factory()->create();
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $otherList->id,
            'influencer_id' => Influencer::factory()->create(['contact_email' => 'creator@example.com'])->id,
        ]);

        $this->actingAs($user)
            ->post(route('influencers.entries.emails.store', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $otherList->id,
                'entry' => $entry->id,
            ]), ['subject' => 'Hi', 'body' => 'Hello'])
            ->assertNotFound();

        Mail::assertNothingSent();
    }
}
