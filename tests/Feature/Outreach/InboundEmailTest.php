<?php

namespace Tests\Feature\Outreach;

use App\Actions\Outreach\HandleInboundEmail;
use App\Enums\OutreachStatus;
use App\Jobs\Outreach\ProcessInboundEmail;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\OutreachMessage;
use App\Models\User;
use App\Notifications\Outreach\InboundReplyReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboundEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, entry: InfluencerListEntry, message: OutreachMessage}
     */
    private function outboundThread(OutreachStatus $status = OutreachStatus::Contacted): array
    {
        $user = User::factory()->create();
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create([
            'influencer_list_id' => $list->id,
            'outreach_status' => $status,
            'added_by' => $user->id,
        ]);

        $message = $entry->messages()->create([
            'user_id' => $user->id,
            'direction' => OutreachMessage::DIRECTION_OUTBOUND,
            'from_email' => 'outreach@brand.test',
            'to_email' => 'creator@example.com',
            'subject' => 'Partnership?',
            'body' => 'Hello!',
            'reply_token' => 'abc123token',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return ['user' => $user, 'entry' => $entry, 'message' => $message];
    }

    public function test_the_webhook_is_hidden_when_no_token_is_configured(): void
    {
        config(['services.inbound_mail.webhook_token' => null]);

        $this->postJson(route('webhooks.inbound-email'), ['items' => []])
            ->assertNotFound();
    }

    public function test_the_webhook_rejects_a_bad_token(): void
    {
        Queue::fake();
        config(['services.inbound_mail.webhook_token' => 'inbound-secret']);

        $this->postJson(route('webhooks.inbound-email'), ['items' => []], ['X-Webhook-Token' => 'wrong'])
            ->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_a_brevo_shaped_payload_is_normalized_and_queued(): void
    {
        Queue::fake();
        config(['services.inbound_mail.webhook_token' => 'inbound-secret']);

        $this->postJson(route('webhooks.inbound-email'), [
            'items' => [[
                'MessageId' => '<msg-1@mail.example>',
                'From' => ['Name' => 'Jane', 'Address' => 'creator@example.com'],
                'To' => [['Name' => null, 'Address' => 'reply+abc123token@in.extrovert.test']],
                'Recipients' => ['reply+abc123token@in.extrovert.test'],
                'Subject' => 'Re: Partnership?',
                'RawTextBody' => 'Sounds great, let us talk!',
            ]],
        ], ['X-Webhook-Token' => 'inbound-secret'])
            ->assertOk()
            ->assertJson(['queued' => 1]);

        Queue::assertPushed(ProcessInboundEmail::class, fn (ProcessInboundEmail $job) => $job->recipients === ['reply+abc123token@in.extrovert.test']
            && $job->fromEmail === 'creator@example.com'
            && $job->subject === 'Re: Partnership?'
            && $job->body === 'Sounds great, let us talk!'
            && $job->providerMessageId === '<msg-1@mail.example>');
    }

    public function test_a_matched_reply_lands_in_the_thread_and_advances_the_status(): void
    {
        Notification::fake();

        ['user' => $user, 'entry' => $entry, 'message' => $original] = $this->outboundThread();

        $inbound = app(HandleInboundEmail::class)->handle(
            recipients: ['reply+abc123token@in.extrovert.test'],
            fromEmail: 'creator@example.com',
            subject: 'Re: Partnership?',
            body: 'Yes! Interested.',
            providerMessageId: '<msg-2@mail.example>',
        );

        $this->assertNotNull($inbound);
        $this->assertDatabaseHas('outreach_messages', [
            'influencer_list_entry_id' => $entry->id,
            'direction' => 'inbound',
            'from_email' => 'creator@example.com',
            'status' => 'received',
            'body' => 'Yes! Interested.',
        ]);

        $this->assertSame(OutreachStatus::Replied, $entry->fresh()->outreach_status);

        Notification::assertSentTo($user, InboundReplyReceived::class, fn (InboundReplyReceived $notification) => $notification->message->is($inbound));
    }

    public function test_a_reply_does_not_downgrade_a_negotiating_entry(): void
    {
        Notification::fake();

        ['entry' => $entry] = $this->outboundThread(OutreachStatus::Negotiating);

        app(HandleInboundEmail::class)->handle(
            recipients: ['reply+abc123token@in.extrovert.test'],
            fromEmail: 'creator@example.com',
            subject: null,
            body: 'Circling back.',
        );

        $this->assertSame(OutreachStatus::Negotiating, $entry->fresh()->outreach_status);
    }

    public function test_duplicate_webhook_deliveries_do_not_duplicate_the_message(): void
    {
        Notification::fake();

        ['entry' => $entry] = $this->outboundThread();

        $handler = app(HandleInboundEmail::class);

        foreach (range(1, 2) as $attempt) {
            $handler->handle(
                recipients: ['reply+abc123token@in.extrovert.test'],
                fromEmail: 'creator@example.com',
                subject: 'Re: Partnership?',
                body: 'Same message, retried delivery.',
                providerMessageId: '<msg-3@mail.example>',
            );
        }

        $this->assertSame(1, $entry->messages()->where('direction', 'inbound')->count());
    }

    public function test_unmatched_mail_lands_in_the_review_queue(): void
    {
        Notification::fake();

        app(HandleInboundEmail::class)->handle(
            recipients: ['reply+unknowntoken@in.extrovert.test'],
            fromEmail: 'stranger@example.com',
            subject: 'Hello?',
            body: 'Is anyone there?',
            rawPayload: ['Subject' => 'Hello?'],
        );

        $this->assertDatabaseCount('outreach_messages', 0);
        $this->assertDatabaseHas('unmatched_inbound_emails', [
            'from_email' => 'stranger@example.com',
            'subject' => 'Hello?',
        ]);

        Notification::assertNothingSent();
    }

    public function test_a_missing_subject_falls_back_to_the_original_thread_subject(): void
    {
        Notification::fake();

        ['entry' => $entry] = $this->outboundThread();

        app(HandleInboundEmail::class)->handle(
            recipients: ['reply+abc123token@in.extrovert.test'],
            fromEmail: 'creator@example.com',
            subject: null,
            body: 'Quick reply from my phone',
        );

        $this->assertDatabaseHas('outreach_messages', [
            'influencer_list_entry_id' => $entry->id,
            'direction' => 'inbound',
            'subject' => 'Re: Partnership?',
        ]);
    }
}
