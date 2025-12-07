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
        // Utiliser updateOrCreate pour éviter les doublons
        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/recueil-global-pp-2025.docx'],
            [
                'name' => 'Recueil Global Personne Physique 2025',
                'description' => 'Template réglementaire pour le recueil des informations client (personnes physiques)',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/Template Mandat.docx'],
            [
                'name' => 'Mandat Santé Prévoyance Épargne Retraite ADE PP',
                'description' => 'Template de mandat pour la santé, prévoyance, épargne et retraite (personnes physiques)',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        // === 5 TEMPLATES RC (RECUEIL CLIENT) - NETTOYÉS ET OPTIMISÉS ===

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/rc-assurance-vie.docx'],
            [
                'name' => 'RC Assurance Vie / Capitalisation',
                'description' => 'Recueil des besoins et analyse d\'adéquation pour l\'assurance vie et les contrats de capitalisation',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/rc-emprunteur.docx'],
            [
                'name' => 'RC Prévoyance Emprunteur',
                'description' => 'Recueil des besoins pour la prévoyance emprunteur (assurance de prêt)',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/rc-per.docx'],
            [
                'name' => 'RC Plan Épargne Retraite (PER)',
                'description' => 'Recueil des besoins et analyse d\'adéquation pour le Plan Épargne Retraite',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/rc-prevoyance.docx'],
            [
                'name' => 'RC Prévoyance',
                'description' => 'Recueil des besoins pour les contrats de prévoyance individuelle',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/rc-sante.docx'],
            [
                'name' => 'RC Santé / Complémentaire Santé',
                'description' => 'Recueil des besoins pour les complémentaires santé et mutuelles',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );

        DocumentTemplate::updateOrCreate(
            ['file_path' => 'templates/recueil-ade.docx'],
            [
                'name' => 'Recueil ADE (Analyse des Éléments)',
                'description' => 'Recueil complet des informations client et conjoint pour analyse détaillée',
                'category' => 'reglementaire',
                'is_active' => true,
            ]
        );
    }
}
