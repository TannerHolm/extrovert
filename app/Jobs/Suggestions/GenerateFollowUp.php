<?php

namespace App\Jobs\Suggestions;

use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Models\InfluencerListEntry;
use App\Models\OutreachMessage;
use App\Services\AI\Claude;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts a gentle follow-up for an entry that was contacted but never
 * replied. The nudger command decides who qualifies; this job only writes
 * the draft into the approval queue.
 */
class GenerateFollowUp implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public InfluencerListEntry $entry)
    {
        //
    }

    public function handle(Claude $claude): void
    {
        $entry = $this->entry->fresh(['influencer', 'influencerList.team', 'messages']);

        if (
            $entry === null
            || $entry->outreach_status !== OutreachStatus::Contacted
            || $entry->influencer->contact_email === null
        ) {
            return;
        }

        $hasInbound = $entry->messages
            ->contains(fn (OutreachMessage $message) => $message->direction === OutreachMessage::DIRECTION_INBOUND);
        $lastOutbound = $entry->messages
            ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
            ->sortByDesc('sent_at')
            ->first();

        if ($hasInbound || $lastOutbound === null) {
            return;
        }

        $duplicate = $entry->suggestedActions()
            ->where('type', SuggestedActionType::FollowUp)
            ->where('status', SuggestedActionStatus::Pending)
            ->exists();

        if ($duplicate) {
            return;
        }

        $influencer = $entry->influencer;
        $team = $entry->influencerList->team;
        $name = $influencer->display_name ?? $influencer->handle;
        $daysAgo = (int) $lastOutbound->sent_at?->diffInDays(now());

        $fallback = [
            'subject' => 'Re: '.$lastOutbound->subject,
            'body' => "Hi {$name},\n\n"
                ."Just floating this back to the top of your inbox — I reached out about partnering with {$team->name} and would still love to make something happen.\n\n"
                ."If the timing isn't right, no worries at all; a quick 'not now' works too.\n\n"
                ."Best,\n{$team->name}",
        ];

        $drafted = $claude->draftJson(
            'You draft short follow-up emails for influencer outreach that got no reply. '
            .'Be brief (under 90 words), friendly, zero pressure, and reference the original ask without repeating it wholesale. '
            .'No placeholder brackets, no emoji. Return JSON: {"subject": string, "body": string}.',
            json_encode([
                'brand' => $team->name,
                'creator_name' => $name,
                'original_subject' => $lastOutbound->subject,
                'original_body' => $lastOutbound->body,
                'days_since_contact' => $daysAgo,
            ]),
        );

        $payload = (is_array($drafted) && isset($drafted['subject'], $drafted['body']))
            ? ['subject' => (string) $drafted['subject'], 'body' => (string) $drafted['body']]
            : $fallback;

        $entry->suggestedActions()->create([
            'team_id' => $team->id,
            'type' => SuggestedActionType::FollowUp,
            'payload' => [...$payload, 'to' => $influencer->contact_email, 'ai' => $drafted !== null, 'days_since_contact' => $daysAgo],
        ]);
    }
}
