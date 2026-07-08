<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'holm.tanner@gmail.com'],
            [
                'name' => 'Tanner Holm',
                'email' => 'holm.tanner@gmail.com',
                'password' => bcrypt('DevenirCode1!'),
                'email_verified_at' => now(),
            ],
        );

        if ($user->personalTeam() === null) {
            (new CreateTeam)->handle($user, "Tanner's Team", isPersonal: true);
        }
    }
}
