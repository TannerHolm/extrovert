<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class VerifyTeamSendingDomain extends Command
{
    protected $signature = 'team:verify-sending-domain {team : The team slug} {--unverify : Clear the verified flag instead}';

    protected $description = "Mark a team's sending domain verified after confirming its DNS in the mail provider";

    public function handle(): int
    {
        $team = Team::where('slug', $this->argument('team'))->first();

        if (! $team) {
            $this->error("No team found with slug [{$this->argument('team')}].");

            return self::FAILURE;
        }

        if (! $team->sending_from_email) {
            $this->error("Team [{$team->name}] has no sending address configured yet.");

            return self::FAILURE;
        }

        $team->update([
            'sending_domain_verified_at' => $this->option('unverify') ? null : now(),
        ]);

        $verb = $this->option('unverify') ? 'Unverified' : 'Verified';
        $this->info("{$verb} sending domain for [{$team->name}] ({$team->sending_from_email}).");

        return self::SUCCESS;
    }
}
