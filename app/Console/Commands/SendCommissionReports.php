<?php

namespace App\Console\Commands;

use App\Enums\TeamRole;
use App\Mail\MonthlyCommissionReport;
use App\Models\Team;
use App\Services\Reports\CommissionReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Monthly "what's owed to whom" summary, mailed to each team's owner with
 * the CSV attached. Runs on the 1st for the previous month.
 */
class SendCommissionReports extends Command
{
    protected $signature = 'extrovert:commission-report
        {--month= : Report month as YYYY-MM (defaults to last month)}';

    protected $description = 'Email each team a commission summary for the month';

    public function handle(CommissionReport $report): int
    {
        $option = $this->option('month');
        $month = $option !== null
            ? Carbon::createFromFormat('Y-m', $option)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $sent = 0;

        Team::query()->chunkById(50, function ($teams) use ($report, $month, &$sent) {
            foreach ($teams as $team) {
                $rows = $report->rows($team, $month);

                if ($rows->isEmpty()) {
                    continue;
                }

                $owners = $team->members()
                    ->wherePivot('role', TeamRole::Owner->value)
                    ->get();

                foreach ($owners as $owner) {
                    Mail::to($owner->email)->send(new MonthlyCommissionReport(
                        team: $team,
                        month: $month,
                        rows: $rows,
                        csv: $report->csv($team, $month),
                    ));
                    $sent++;
                }
            }
        });

        $this->info("Sent {$sent} commission report(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
