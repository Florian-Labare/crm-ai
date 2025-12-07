<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Role Seeder
 *
 * Crée les rôles pour l'application
 */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer le rôle MIA (Mandataire d'Intermédiaire en Assurance)
        Role::firstOrCreate(['name' => 'MIA'], [
            'guard_name' => 'web',
        ]);

        $this->command->info('✅ Rôle MIA créé avec succès');
    }
}
