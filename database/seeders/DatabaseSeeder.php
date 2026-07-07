<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\InfluencerList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUser('Tanner Holm', 'holm.tanner@gmail.com');
        $tanner = $this->seedUser('Tanner Holm', 'tanner.holm@freedomfuel.us');
        $chris = $this->seedUser('Chris Miller', 'chris.miller@freedomfuel.us');

        // A shared team so Tanner and Chris collaborate on the same lists.
        $this->seedSharedTeam('Freedom Fuel', [
            $tanner->id => TeamRole::Owner,
            $chris->id => TeamRole::Admin,
        ]);
    }

    /**
     * Create a user (with a personal team) unless one already exists for the email.
     */
    private function seedUser(string $name, string $email, string $password = 'password123!'): User
    {
        return User::where('email', $email)->first()
            ?? User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt($password),
            ]);
    }

    /**
     * Create a shared (non-personal) team, add the given members with their roles,
     * make it each member's current team, and give it a starter list.
     *
     * @param  array<int, TeamRole>  $members  user id => role
     */
    private function seedSharedTeam(string $name, array $members): void
    {
        $team = Team::firstOrCreate(['name' => $name], ['is_personal' => false]);

        foreach ($members as $userId => $role) {
            $team->memberships()->updateOrCreate(
                ['user_id' => $userId],
                ['role' => $role],
            );
        }

        // Point each member's current team at the shared team so it is their default.
        User::whereIn('id', array_keys($members))->get()
            ->each(fn (User $user) => $user->switchTeam($team));

        // A starter list everyone on the team can work from.
        InfluencerList::firstOrCreate(
            ['team_id' => $team->id, 'name' => 'Prospects'],
            ['description' => 'Shared outreach pipeline for the team.'],
        );
    }
}
