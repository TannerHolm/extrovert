<?php

namespace App\Jobs\Suggestions;

use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Models\InfluencerListEntry;
use App\Services\AI\Claude;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts a personalized first outreach for a freshly saved influencer,
 * using whatever platform data discovery stored. Falls back to a solid
 * template when AI is off — either way a human approves before send.
 */
class GenerateFirstTouch implements ShouldQueue
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
            || $entry->outreach_status !== OutreachStatus::None
            || $entry->influencer->contact_email === null
            || $entry->messages->isNotEmpty()
        ) {
            return;
        }

        $duplicate = $entry->suggestedActions()
            ->where('type', SuggestedActionType::FirstTouch)
            ->where('status', SuggestedActionStatus::Pending)
            ->exists();

        if ($duplicate) {
            return;
        }

        $influencer = $entry->influencer;
        $team = $entry->influencerList->team;
        $name = $influencer->display_name ?? $influencer->handle;

        $fallback = [
            'subject' => "Partnership with {$team->name}?",
            'body' => "Hi {$name},\n\n"
                ."I've been following your {$influencer->platform->label()} channel ({$influencer->handle}) and love what you're putting out."
                .($influencer->follower_count ? " The community you've built is exactly the kind of audience we care about.\n\n" : "\n\n")
                ."I work with {$team->name} and think a partnership could be a great fit. Would you be open to a quick chat about a collab?\n\n"
                ."Best,\n{$team->name}",
        ];

        $drafted = $claude->draftJson(
            'You draft first-touch influencer outreach emails for a brand. '
            .'Write a short, warm, specific email (under 150 words) that references the creator\'s actual content when data is provided. '
            .'No placeholder brackets, no hype, no emoji. '
            .'Return JSON: {"subject": string, "body": string}.',
            json_encode([
                'brand' => $team->name,
                'creator_name' => $name,
                'handle' => $influencer->handle,
                'platform' => $influencer->platform->label(),
                'follower_count' => $influencer->follower_count,
                'engagement_rate' => $influencer->engagement_rate,
                'platform_data' => $influencer->platform_data,
            ]),
        );

        $payload = (is_array($drafted) && isset($drafted['subject'], $drafted['body']))
            ? ['subject' => (string) $drafted['subject'], 'body' => (string) $drafted['body']]
            : $fallback;

        $entry->suggestedActions()->create([
            'team_id' => $team->id,
            'type' => SuggestedActionType::FirstTouch,
            'payload' => [...$payload, 'to' => $influencer->contact_email, 'ai' => $drafted !== null],
        ]);
    }
}
