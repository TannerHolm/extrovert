<?php

namespace Tests\Feature\Influencers;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\User;
use App\Notifications\Deals\OverdueDeliverables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FlagOverdueDeliverablesTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeal(User $user, DealStatus $status, array $deliverables): Deal
    {
        $list = InfluencerList::factory()->create(['team_id' => $user->currentTeam->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);

        return Deal::factory()->create([
            'team_id' => $user->currentTeam->id,
            'influencer_list_entry_id' => $entry->id,
            'status' => $status,
            'deliverables' => $deliverables,
            'created_by' => $user->id,
        ]);
    }

    public function test_it_notifies_the_deal_owner_about_overdue_deliverables(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $deal = $this->makeDeal($user, DealStatus::Live, [
            ['type' => 'video', 'platform' => 'youtube', 'due_date' => now()->subDays(3)->toDateString(), 'posted_url' => null],
            ['type' => 'post', 'platform' => 'instagram', 'due_date' => now()->addDays(3)->toDateString(), 'posted_url' => null],
        ]);

        $this->artisan('extrovert:flag-overdue-deliverables')->assertSuccessful();

        Notification::assertSentTo($user, OverdueDeliverables::class, function (OverdueDeliverables $notification) use ($deal) {
            return $notification->deal->is($deal) && count($notification->deliverables) === 1;
        });

        // The overdue deliverable is stamped so the next run stays quiet.
        $this->assertNotEmpty($deal->fresh()->deliverables[0]['overdue_notified_at'] ?? null);
        $this->assertArrayNotHasKey('overdue_notified_at', $deal->fresh()->deliverables[1]);
    }

    public function test_it_does_not_renotify_already_flagged_deliverables(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->makeDeal($user, DealStatus::Live, [
            [
                'type' => 'video',
                'platform' => 'youtube',
                'due_date' => now()->subDays(3)->toDateString(),
                'posted_url' => null,
                'overdue_notified_at' => now()->subDay()->toISOString(),
            ],
        ]);

        $this->artisan('extrovert:flag-overdue-deliverables')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_it_ignores_draft_deals_and_posted_deliverables(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->makeDeal($user, DealStatus::Draft, [
            ['type' => 'video', 'platform' => 'youtube', 'due_date' => now()->subDays(3)->toDateString(), 'posted_url' => null],
        ]);
        $this->makeDeal($user, DealStatus::Live, [
            ['type' => 'post', 'platform' => 'instagram', 'due_date' => now()->subDays(3)->toDateString(), 'posted_url' => 'https://instagram.com/p/abc'],
        ]);

        $this->artisan('extrovert:flag-overdue-deliverables')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
