<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Créer l'utilisateur admin par défaut
        User::firstOrCreate(
            ['email' => 'admin@courtier.fr'],
            [
                'name' => 'Admin',
                'firstname' => 'Courtier',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Appeler les seeders dans l'ordre
        $this->call([
            RoleSeeder::class,        // Rôles et permissions
            TeamSeeder::class,        // Équipe par défaut (ID=1)
            MiaUserSeeder::class,     // Utilisateurs MIA
            DocumentTemplateSeeder::class, // Templates de documents
        ]);

        $this->command->info('Base de données initialisée avec succès !');
        $this->command->info('Connexion: admin@courtier.fr / password');
    }
}
