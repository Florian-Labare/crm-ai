<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MIAUsersSeeder extends Seeder
{
    /**
     * Run the database seeder.
     * Crée des utilisateurs avec le rôle MIA (Chargé de clientèle)
     * pour peupler les selects de prise de rendez-vous et génération DER
     */
    public function run(): void
    {
        // Créer le rôle MIA s'il n'existe pas
        $miaRole = Role::firstOrCreate(['name' => 'MIA']);

        // Liste des chargés de clientèle à créer
        $chargesClientele = [
            [
                'name' => 'Sophie Martin',
                'firstname' => 'Sophie',
                'email' => 'sophie.martin@crm-ai.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Thomas Dubois',
                'firstname' => 'Thomas',
                'email' => 'thomas.dubois@crm-ai.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Julie Rousseau',
                'firstname' => 'Julie',
                'email' => 'julie.rousseau@crm-ai.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Pierre Lefevre',
                'firstname' => 'Pierre',
                'email' => 'pierre.lefevre@crm-ai.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Marie Blanc',
                'firstname' => 'Marie',
                'email' => 'marie.blanc@crm-ai.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($chargesClientele as $userData) {
            // Créer l'utilisateur s'il n'existe pas déjà
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'firstname' => $userData['firstname'],
                    'password' => $userData['password'],
                    'email_verified_at' => now(),
                ]
            );

            // Assigner le rôle MIA
            if (!$user->hasRole('MIA')) {
                $user->assignRole($miaRole);
            }

            // Créer une team personnelle pour l'utilisateur si elle n'existe pas
            if (!Team::where('user_id', $user->id)->where('personal_team', true)->exists()) {
                $team = Team::create([
                    'user_id' => $user->id,
                    'name' => $user->name . "'s Team",
                    'personal_team' => true,
                ]);

                // Attacher l'utilisateur à sa team
                $user->teams()->syncWithoutDetaching([$team->id => ['role' => 'admin']]);
            }

            $this->command->info("✓ Utilisateur MIA créé: {$user->name} ({$user->email})");
        }

        $this->command->info("\n✅ " . count($chargesClientele) . " chargés de clientèle (MIA) créés avec succès!");
    }
}
