<?php

namespace Database\Seeders;

use App\Models\Assureur;
use Illuminate\Database\Seeder;

class AssureurSeeder extends Seeder {
    public function run(): void {
        $assureurs = [
            'Allianz',
            'AXA',
            'Generali',
            'Swiss Life',
            'AG2R La Mondiale',
            'Malakoff Humanis',
            'April',
            'Cardif BNP Paribas',
            'CNP Assurances',
            'Apicil',
            'Humanis',
            'Mutex',
        ];

        foreach ($assureurs as $nom) {
            Assureur::firstOrCreate(
                ['nom' => $nom],
                ['lien_espace_client' => null]
            );
        }

        $this->command->info('Assureurs pré-peuplés avec succès.');
    }
}
