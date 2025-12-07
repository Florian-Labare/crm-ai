<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MiaUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer le rôle MIA s'il n'existe pas
        $miaRole = Role::firstOrCreate(['name' => 'MIA']);

        // Créer 3 utilisateurs MIA de démonstration
        $mias = [
            [
                'name' => 'Dupont',
                'firstname' => 'Jean',
                'email' => 'jean.dupont@example.com',
            ],
            [
                'name' => 'Martin',
                'firstname' => 'Marie',
                'email' => 'marie.martin@example.com',
            ],
            [
                'name' => 'Bernard',
                'firstname' => 'Pierre',
                'email' => 'pierre.bernard@example.com',
            ],
        ];

        foreach ($mias as $miaData) {
            // Créer l'utilisateur s'il n'existe pas
            $user = User::firstOrCreate(
                ['email' => $miaData['email']],
                [
                    'name' => $miaData['name'],
                    'firstname' => $miaData['firstname'],
                    'password' => Hash::make('password'), // Mot de passe par défaut
                ]
            );

            // Assigner le rôle MIA
            if (!$user->hasRole('MIA')) {
                $user->assignRole('MIA');
            }

            // Créer une équipe personnelle si l'utilisateur n'en a pas
            if (!$user->ownedTeams()->where('personal_team', true)->exists()) {
                $team = Team::create([
                    'user_id' => $user->id,
                    'name' => $user->firstname . "'s Team",
                    'personal_team' => true,
                ]);

                $user->teams()->attach($team, ['role' => 'owner']);
            }

            // Ajouter l'utilisateur MIA uniquement à l'équipe de l'utilisateur ID 33
            $targetTeam = Team::where('user_id', 33)->first();

            if ($targetTeam && !$user->belongsToTeam($targetTeam)) {
                $user->teams()->attach($targetTeam, ['role' => 'admin']);
                $this->command->info("Added {$user->name} to team {$targetTeam->name}");
            }

            $this->command->info("Created MIA user: {$user->name} ({$user->email})");
        }

        $this->command->info('MIA users seeded successfully!');
    }
}
