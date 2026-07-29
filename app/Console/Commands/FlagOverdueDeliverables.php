<?php

namespace App\Console\Commands;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Notifications\Deals\OverdueDeliverables;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FlagOverdueDeliverables extends Command
{
    protected $signature = 'extrovert:flag-overdue-deliverables';

    protected $description = 'Notify deal owners about deliverables past their due date with no posted URL';

    public function handle(): int
    {
        $flagged = 0;

        Deal::query()
            ->whereIn('status', DealStatus::active())
            ->whereNotNull('deliverables')
            ->with('entry.influencer', 'entry.influencerList', 'team', 'createdBy')
            ->chunkById(100, function ($deals) use (&$flagged) {
                foreach ($deals as $deal) {
                    $flagged += $this->flagDeal($deal);
                }
            });

        $this->info("Flagged {$flagged} deal(s) with overdue deliverables.");

        return self::SUCCESS;
    }

    /**
     * Notify about newly-overdue deliverables and stamp them so subsequent
     * runs don't re-notify. Returns 1 when a notification went out.
     */
    private function flagDeal(Deal $deal): int
    {
        $deliverables = collect($deal->deliverables ?? []);

        $isNewlyOverdue = fn (array $deliverable) => empty($deliverable['posted_url'])
            && empty($deliverable['overdue_notified_at'])
            && ! empty($deliverable['due_date'])
            && Carbon::parse($deliverable['due_date'])->isPast();

        $newlyOverdue = $deliverables->filter($isNewlyOverdue);

        if ($newlyOverdue->isEmpty()) {
            return 0;
        }

        $deal->update([
            'deliverables' => $deliverables->map(fn (array $deliverable) => $isNewlyOverdue($deliverable)
                ? [...$deliverable, 'overdue_notified_at' => now()->toISOString()]
                : $deliverable)->all(),
        ]);

        $deal->createdBy?->notify(new OverdueDeliverables($deal, $newlyOverdue->values()->all()));

        return 1;
    }
}
