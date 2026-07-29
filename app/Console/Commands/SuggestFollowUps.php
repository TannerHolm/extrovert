<?php

namespace App\Console\Commands;

use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Jobs\Suggestions\GenerateFollowUp;
use App\Models\InfluencerListEntry;
use App\Models\OutreachMessage;
use Illuminate\Console\Command;

/**
 * The follow-up nudger: entries contacted ≥ N days ago with no inbound
 * reply get a drafted follow-up in the approval queue. Likely the single
 * biggest reply-rate win in the plan.
 */
class SuggestFollowUps extends Command
{
    protected $signature = 'extrovert:suggest-follow-ups {--days=7 : Days since last outbound with no reply}';

    protected $description = 'Queue drafted follow-ups for contacted entries that never replied';

    public function handle(): int
    {
        $threshold = now()->subDays((int) $this->option('days'));
        $queued = 0;

        InfluencerListEntry::query()
            ->where('outreach_status', OutreachStatus::Contacted)
            ->whereHas('influencer', fn ($influencers) => $influencers->whereNotNull('contact_email'))
            // Last outreach is old enough...
            ->whereHas('messages', fn ($messages) => $messages
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->where('sent_at', '<=', $threshold))
            // ...they never wrote back...
            ->whereDoesntHave('messages', fn ($messages) => $messages
                ->where('direction', OutreachMessage::DIRECTION_INBOUND))
            // ...no outreach more recent than the threshold...
            ->whereDoesntHave('messages', fn ($messages) => $messages
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->where('sent_at', '>', $threshold))
            // ...and nothing already waiting for review.
            ->whereDoesntHave('suggestedActions', fn ($suggestions) => $suggestions
                ->where('type', SuggestedActionType::FollowUp)
                ->where('status', SuggestedActionStatus::Pending))
            ->chunkById(100, function ($entries) use (&$queued) {
                foreach ($entries as $entry) {
                    GenerateFollowUp::dispatch($entry);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} follow-up draft(s) for review.");

        return self::SUCCESS;
    }
}
