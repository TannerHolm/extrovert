<?php

namespace Tests\Feature\Suggestions;

use App\Actions\Influencers\SaveInfluencerToList;
use App\Actions\Outreach\HandleInboundEmail;
use App\Enums\DealStatus;
use App\Enums\OutreachStatus;
use App\Models\AttributedOrder;
use App\Models\Deal;
use App\Models\Influencer;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\OutreachMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The queue is sync in tests and no ANTHROPIC_API_KEY is configured, so
 * generator jobs run inline and exercise their template fallbacks.
 */
class SuggestionGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_reachable_influencer_drafts_a_first_touch(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        app(SaveInfluencerToList::class)->handle($list, [
            'platform' => 'youtube',
            'platform_id' => 'UC123',
            'handle' => '@jane.doe',
            'profile_url' => 'https://youtube.com/@jane.doe',
            'display_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
            'follower_count' => 120000,
        ], $user);

        $this->assertDatabaseHas('suggested_actions', [
            'team_id' => $user->currentTeam->id,
            'type' => 'first_touch',
            'status' => 'pending',
        ]);

        $payload = InfluencerListEntry::firstOrFail()->suggestedActions()->firstOrFail()->payload;
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertStringContainsString('Jane Doe', $payload['body']);
        $this->assertFalse($payload['ai']);
    }

    public function test_no_first_touch_without_a_contact_email(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        app(SaveInfluencerToList::class)->handle($list, [
            'platform' => 'youtube',
            'platform_id' => 'UC456',
            'handle' => '@quiet',
            'profile_url' => 'https://youtube.com/@quiet',
        ], $user);

        $this->assertDatabaseCount('suggested_actions', 0);
    }

    public function test_the_nudger_drafts_follow_ups_for_ghosted_outreach(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);

        $ghosted = $this->contactedEntry($list->id, daysSinceOutreach: 9);
        $recent = $this->contactedEntry($list->id, daysSinceOutreach: 2);
        $replied = $this->contactedEntry($list->id, daysSinceOutreach: 9, hasInbound: true);

        $this->artisan('extrovert:suggest-follow-ups')
            ->expectsOutputToContain('Queued 1 follow-up draft(s)')
            ->assertSuccessful();

        $this->assertSame(1, $ghosted->suggestedActions()->where('type', 'follow_up')->count());
        $this->assertSame(0, $recent->suggestedActions()->count());
        $this->assertSame(0, $replied->suggestedActions()->count());

        // Re-running while a draft is still pending must not duplicate it.
        $this->artisan('extrovert:suggest-follow-ups')->assertSuccessful();
        $this->assertSame(1, $ghosted->suggestedActions()->count());
    }

    public function test_triage_is_skipped_entirely_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null, 'services.inbound_mail.webhook_token' => 'secret']);

        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = $this->contactedEntry($list->id, daysSinceOutreach: 1);
        $entry->messages()->update(['reply_token' => 'triagetoken1']);

        app(HandleInboundEmail::class)->handle(
            recipients: ['reply+triagetoken1@in.test'],
            fromEmail: 'creator@example.com',
            subject: 'Re: hello',
            body: 'Love it, let us do it!',
        );

        // Reply landed, but no triage suggestion without AI.
        $this->assertSame(OutreachStatus::Replied, $entry->fresh()->outreach_status);
        $this->assertSame(0, $entry->suggestedActions()->where('type', 'reply_triage')->count());
    }

    public function test_completing_a_deal_generates_a_stats_recap(): void
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = $this->contactedEntry($list->id, daysSinceOutreach: 1);
        $deal = Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => DealStatus::Live,
            'flat_fee_cents' => 50000,
            'commission_rate' => 10,
            'deliverables' => [
                ['type' => 'video', 'platform' => 'youtube', 'posted_url' => 'https://youtube.com/v1'],
            ],
        ]);
        AttributedOrder::factory()->create([
            'team_id' => $user->currentTeam->id,
            'deal_id' => $deal->id,
            'total_cents' => 200000,
            'is_new_customer' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('influencers.entries.deals.update', [
                'current_team' => $user->currentTeam->slug,
                'influencerList' => $list->id,
                'entry' => $entry->id,
                'deal' => $deal->id,
            ]), [
                'status' => DealStatus::Completed->value,
                'compensation_type' => 'hybrid',
                'flat_fee_cents' => 50000,
                'commission_rate' => 10,
            ])
            ->assertRedirect();

        $suggestion = $deal->suggestedActions()->where('type', 'deal_recap')->firstOrFail();

        // Spend = $500 fee + 10% of $2,000 = $700; revenue $2,000; ROI 2.86.
        $this->assertStringContainsString('$700.00', $suggestion->payload['recap']);
        $this->assertStringContainsString('$2,000.00', $suggestion->payload['recap']);
        $this->assertSame(2.86, $suggestion->payload['stats']['roi']);
    }

    private function contactedEntry(int $listId, int $daysSinceOutreach, bool $hasInbound = false): InfluencerListEntry
    {
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $listId,
            'influencer_id' => Influencer::factory()->create(['contact_email' => fake()->unique()->safeEmail()])->id,
            'outreach_status' => OutreachStatus::Contacted,
        ]);

        $entry->messages()->create([
            'direction' => OutreachMessage::DIRECTION_OUTBOUND,
            'from_email' => 'team@brand.test',
            'to_email' => $entry->influencer->contact_email,
            'subject' => 'Partnership?',
            'body' => 'Hello!',
            'status' => 'sent',
            'sent_at' => now()->subDays($daysSinceOutreach),
        ]);

        if ($hasInbound) {
            $entry->messages()->create([
                'direction' => OutreachMessage::DIRECTION_INBOUND,
                'from_email' => $entry->influencer->contact_email,
                'to_email' => 'team@brand.test',
                'subject' => 'Re: Partnership?',
                'body' => 'Interested!',
                'status' => 'received',
                'sent_at' => now()->subDays($daysSinceOutreach - 1),
            ]);
        }

        return $entry;
    }
}
