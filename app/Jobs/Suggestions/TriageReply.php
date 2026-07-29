<?php

namespace App\Jobs\Suggestions;

use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Models\OutreachMessage;
use App\Services\AI\Claude;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Classifies an inbound reply (interested / negotiating / declined /
 * question), suggests the pipeline status change, and drafts a response.
 * Pure-AI feature: without a key it does nothing, and the reply just sits
 * in the thread as before.
 */
class TriageReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    private const CLASSIFICATIONS = ['interested', 'negotiating', 'declined', 'question'];

    public function __construct(public OutreachMessage $message)
    {
        //
    }

    public function handle(Claude $claude): void
    {
        if (! $claude->enabled()) {
            return;
        }

        $message = $this->message->fresh(['entry.influencer', 'entry.influencerList.team', 'entry.messages']);

        if ($message === null || $message->direction !== OutreachMessage::DIRECTION_INBOUND) {
            return;
        }

        $entry = $message->entry;
        $team = $entry->influencerList->team;

        $duplicate = $entry->suggestedActions()
            ->where('type', SuggestedActionType::ReplyTriage)
            ->where('status', SuggestedActionStatus::Pending)
            ->exists();

        if ($duplicate) {
            return;
        }

        $thread = $entry->messages
            ->sortBy('created_at')
            ->map(fn (OutreachMessage $m) => strtoupper($m->direction).": {$m->subject}\n".Str::limit($m->body, 800))
            ->implode("\n---\n");

        $result = $claude->draftJson(
            'You triage replies from influencers to brand outreach. Classify the latest inbound reply and draft the brand\'s response. '
            .'classification must be one of: interested, negotiating, declined, question. '
            .'suggested_status must be one of: replied, negotiating, confirmed, declined — the pipeline stage this reply implies (use "replied" when unsure). '
            .'The drafted reply should be short, concrete, and move the deal forward; no placeholder brackets, no emoji. '
            .'Return JSON: {"classification": string, "suggested_status": string, "summary": string, "subject": string, "body": string}.',
            json_encode([
                'brand' => $team->name,
                'creator_name' => $entry->influencer->display_name ?? $entry->influencer->handle,
                'thread' => $thread,
            ]),
        );

        if (! is_array($result) || ! isset($result['classification'], $result['suggested_status'], $result['subject'], $result['body'])) {
            return;
        }

        $classification = in_array($result['classification'], self::CLASSIFICATIONS, true)
            ? $result['classification']
            : 'question';
        $suggestedStatus = OutreachStatus::tryFrom((string) $result['suggested_status']) ?? OutreachStatus::Replied;

        $entry->suggestedActions()->create([
            'team_id' => $team->id,
            'type' => SuggestedActionType::ReplyTriage,
            'payload' => [
                'classification' => $classification,
                'suggested_status' => $suggestedStatus->value,
                'summary' => (string) ($result['summary'] ?? ''),
                'subject' => (string) $result['subject'],
                'body' => (string) $result['body'],
                'to' => $message->from_email,
                'inbound_message_id' => $message->id,
                'inbound_excerpt' => Str::limit($message->body, 300),
                'ai' => true,
            ],
        ]);
    }
}
