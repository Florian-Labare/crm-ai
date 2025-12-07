<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeder.
     * Crée des clients de démonstration pour peupler la base
     */
    public function run(): void
    {
        // Récupérer tous les utilisateurs MIA
        $miaUsers = User::role('MIA')->get();

        if ($miaUsers->isEmpty()) {
            $this->command->warn('⚠️  Aucun utilisateur MIA trouvé. Exécutez d\'abord MIAUsersSeeder.');
            return;
        }

        // Clients de démonstration
        $clientsData = [
            [
                'nom' => 'DUPONT',
                'prenom' => 'Jean',
                'email' => 'jean.dupont@example.com',
                'telephone' => '0601020304',
                'date_naissance' => '1975-03-15',
                'profession' => 'Ingénieur',
            ],
            [
                'nom' => 'MARTIN',
                'prenom' => 'Sophie',
                'email' => 'sophie.martin@example.com',
                'telephone' => '0612345678',
                'date_naissance' => '1982-07-22',
                'profession' => 'Chef de projet',
            ],
            [
                'nom' => 'BERNARD',
                'prenom' => 'Pierre',
                'email' => 'pierre.bernard@example.com',
                'telephone' => '0623456789',
                'date_naissance' => '1968-11-30',
                'profession' => 'Médecin',
            ],
            [
                'nom' => 'DUBOIS',
                'prenom' => 'Marie',
                'email' => 'marie.dubois@example.com',
                'telephone' => '0634567890',
                'date_naissance' => '1990-05-18',
                'profession' => 'Avocate',
            ],
            [
                'nom' => 'LEROY',
                'prenom' => 'Thomas',
                'email' => 'thomas.leroy@example.com',
                'telephone' => '0645678901',
                'date_naissance' => '1985-09-08',
                'profession' => 'Architecte',
            ],
            [
                'nom' => 'MOREAU',
                'prenom' => 'Julie',
                'email' => 'julie.moreau@example.com',
                'telephone' => '0656789012',
                'date_naissance' => '1978-12-25',
                'profession' => 'Enseignante',
            ],
            [
                'nom' => 'SIMON',
                'prenom' => 'François',
                'email' => 'francois.simon@example.com',
                'telephone' => '0667890123',
                'date_naissance' => '1972-04-14',
                'profession' => 'Entrepreneur',
            ],
            [
                'nom' => 'LAURENT',
                'prenom' => 'Christine',
                'email' => 'christine.laurent@example.com',
                'telephone' => '0678901234',
                'date_naissance' => '1987-08-03',
                'profession' => 'Pharmacienne',
            ],
        ];

        // Répartir les clients entre les utilisateurs MIA
        foreach ($clientsData as $index => $clientData) {
            // Sélectionner un utilisateur MIA (distribution circulaire)
            $miaUser = $miaUsers[$index % $miaUsers->count()];

            // Récupérer la team de l'utilisateur
            $team = Team::where('user_id', $miaUser->id)->where('personal_team', true)->first();

            if (!$team) {
                $this->command->warn("⚠️  Pas de team trouvée pour {$miaUser->name}, skip client {$clientData['nom']}");
                continue;
            }

            // Préparer les données du client
            $clientAttributes = array_merge($clientData, [
                'user_id' => $miaUser->id,
                'adresse' => fake()->streetAddress(),
                'code_postal' => fake()->postcode(),
                'ville' => fake()->city(),
            ]);

            // Ajouter team_id seulement si la colonne existe
            if (Schema::hasColumn('clients', 'team_id')) {
                $clientAttributes['team_id'] = $team->id;
            }

            // Créer ou mettre à jour le client
            $client = Client::updateOrCreate(
                ['email' => $clientData['email']],
                $clientAttributes
            );

            $this->command->info("✓ Client créé: {$client->prenom} {$client->nom} (assigné à {$miaUser->name})");
        }

        $this->command->info("\n✅ " . count($clientsData) . " clients de démonstration créés!");
    }
}
