<?php

namespace Tests\Feature\Reports;

use App\Mail\MonthlyCommissionReport;
use App\Models\AttributedOrder;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommissionReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_owners_of_teams_with_commissions_owed(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $deal = Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'commission_rate' => 15,
        ]);

        AttributedOrder::factory()->create([
            'team_id' => $team->id,
            'deal_id' => $deal->id,
            'total_cents' => 100000,
            'placed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
        ]);

        // A second team with no commission activity must not be emailed.
        $quietOwner = User::factory()->create();

        $this->artisan('extrovert:commission-report')
            ->expectsOutputToContain('Sent 1 commission report(s)')
            ->assertSuccessful();

        Mail::assertSent(MonthlyCommissionReport::class, function (MonthlyCommissionReport $mail) use ($owner) {
            return $mail->hasTo($owner->email)
                && $mail->rows->first()['commission_cents'] === 15000;
        });
        Mail::assertNotSent(MonthlyCommissionReport::class, fn ($mail) => $mail->hasTo($quietOwner->email));
    }

    public function test_a_specific_month_can_be_requested(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $list = InfluencerList::factory()->create(['team_id' => $team->id]);
        $entry = InfluencerListEntry::factory()->create(['influencer_list_id' => $list->id]);
        $deal = Deal::factory()->create([
            'team_id' => $team->id,
            'influencer_list_entry_id' => $entry->id,
            'commission_rate' => 10,
        ]);

        AttributedOrder::factory()->create([
            'team_id' => $team->id,
            'deal_id' => $deal->id,
            'total_cents' => 50000,
            'placed_at' => now()->parse('2026-03-15'),
        ]);

        $this->artisan('extrovert:commission-report', ['--month' => '2026-03'])
            ->expectsOutputToContain('Sent 1 commission report(s) for 2026-03')
            ->assertSuccessful();

        Mail::assertSent(MonthlyCommissionReport::class, fn (MonthlyCommissionReport $mail) => $mail->rows->first()['commission_cents'] === 5000);
    }
}
