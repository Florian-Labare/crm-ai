<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DocumentTemplate::create([
            'name' => 'Recueil Global Personne Physique 2025',
            'description' => 'Template réglementaire pour le recueil des informations client (personnes physiques)',
            'file_path' => 'templates/recueil-global-pp-2025.docx',
            'category' => 'reglementaire',
            'is_active' => true,
        ]);
    }
}
