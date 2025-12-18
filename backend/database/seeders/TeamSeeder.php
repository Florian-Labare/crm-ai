<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Seed the teams table with a default team.
     */
    public function run(): void
    {
        // Créer une équipe par défaut si elle n'existe pas
        $team = Team::firstOrCreate(
            ['id' => 1],
            [
                'user_id' => 1,
                'name' => 'Équipe principale',
                'personal_team' => true,
            ]
        );

        // Associer tous les utilisateurs existants à cette équipe
        $users = User::all();
        foreach ($users as $user) {
            if (!$team->users()->where('user_id', $user->id)->exists()) {
                $team->users()->attach($user->id, ['role' => 'member']);
            }
        }
    }
}
