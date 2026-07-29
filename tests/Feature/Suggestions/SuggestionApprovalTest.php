<?php

namespace Tests\Feature\Suggestions;

use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionType;
use App\Enums\TeamRole;
use App\Mail\OutreachEmail;
use App\Models\Deal;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\SuggestedAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuggestionApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, entry: InfluencerListEntry, suggestion: SuggestedAction}
     */
    private function pendingFirstTouch(): array
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => Influencer::factory()->create(['contact_email' => 'jane@example.com'])->id,
        ]);

        $suggestion = SuggestedAction::factory()->create([
            'team_id' => $user->currentTeam->id,
            'subject_type' => $entry->getMorphClass(),
            'subject_id' => $entry->id,
            'type' => SuggestedActionType::FirstTouch,
            'payload' => ['subject' => 'Hi Jane', 'body' => 'Drafted body', 'to' => 'jane@example.com'],
        ]);

        return ['user' => $user, 'entry' => $entry, 'suggestion' => $suggestion];
    }

    public function test_approving_a_first_touch_sends_the_edited_draft_and_advances_the_entry(): void
    {
        Mail::fake();

        ['user' => $user, 'entry' => $entry, 'suggestion' => $suggestion] = $this->pendingFirstTouch();

        $this->actingAs($user)
            ->post(route('suggestions.approve', [
                'current_team' => $user->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]), [
                'subject' => 'Hi Jane — edited',
                'body' => 'Edited before sending.',
            ])
            ->assertRedirect();

        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail) => $mail->hasTo('jane@example.com')
            && $mail->subjectLine === 'Hi Jane — edited');

        $this->assertDatabaseHas('outreach_messages', [
            'influencer_list_entry_id' => $entry->id,
            'subject' => 'Hi Jane — edited',
            'direction' => 'outbound',
        ]);

        $this->assertSame(OutreachStatus::Contacted, $entry->fresh()->outreach_status);

        $suggestion->refresh();
        $this->assertSame('approved', $suggestion->status->value);
        $this->assertTrue($suggestion->actionedBy->is($user));
    }

    public function test_approving_a_triage_applies_the_status_and_sends_the_reply(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'influencer_id' => Influencer::factory()->create(['contact_email' => 'jane@example.com'])->id,
            'outreach_status' => OutreachStatus::Replied,
        ]);

        $suggestion = SuggestedAction::factory()->create([
            'team_id' => $user->currentTeam->id,
            'subject_type' => $entry->getMorphClass(),
            'subject_id' => $entry->id,
            'type' => SuggestedActionType::ReplyTriage,
            'payload' => [
                'classification' => 'negotiating',
                'suggested_status' => 'negotiating',
                'subject' => 'Re: rates',
                'body' => 'Here is our budget.',
                'to' => 'jane@example.com',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('suggestions.approve', [
                'current_team' => $user->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertRedirect();

        $this->assertSame(OutreachStatus::Negotiating, $entry->fresh()->outreach_status);
        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_approving_a_recap_appends_it_to_the_deal_notes(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'notes' => 'Existing note.',
        ]);

        $suggestion = SuggestedAction::factory()->create([
            'team_id' => $user->currentTeam->id,
            'subject_type' => $deal->getMorphClass(),
            'subject_id' => $deal->id,
            'type' => SuggestedActionType::DealRecap,
            'payload' => ['recap' => 'Great ROI, re-book next quarter.'],
        ]);

        $this->actingAs($user)
            ->post(route('suggestions.approve', [
                'current_team' => $user->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertRedirect();

        $this->assertSame("Existing note.\n\nGreat ROI, re-book next quarter.", $deal->fresh()->notes);
    }

    public function test_dismissing_leaves_no_side_effects(): void
    {
        Mail::fake();

        ['user' => $user, 'entry' => $entry, 'suggestion' => $suggestion] = $this->pendingFirstTouch();

        $this->actingAs($user)
            ->post(route('suggestions.dismiss', [
                'current_team' => $user->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame('dismissed', $suggestion->fresh()->status->value);
        $this->assertSame(OutreachStatus::None, $entry->fresh()->outreach_status);
    }

    public function test_an_already_actioned_suggestion_cannot_be_approved_again(): void
    {
        Mail::fake();

        ['user' => $user, 'suggestion' => $suggestion] = $this->pendingFirstTouch();
        $suggestion->update(['status' => 'approved']);

        $this->actingAs($user)
            ->post(route('suggestions.approve', [
                'current_team' => $user->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_members_cannot_action_suggestions(): void
    {
        Mail::fake();

        ['suggestion' => $suggestion] = $this->pendingFirstTouch();
        $team = $suggestion->team;

        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->post(route('suggestions.approve', [
                'current_team' => $team->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertForbidden();
    }

    public function test_suggestions_are_scoped_to_the_current_team(): void
    {
        ['suggestion' => $suggestion] = $this->pendingFirstTouch();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('suggestions.approve', [
                'current_team' => $outsider->currentTeam->slug,
                'suggestion' => $suggestion->id,
            ]))
            ->assertNotFound();

        $this->assertSame('pending', $suggestion->fresh()->status->value);
    }
}
