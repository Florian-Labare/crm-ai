<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer : DER (5,8,11), fiches conseil (6,9,12), fiche_ipid_sante (14),
        // devis_sante (15), immobilier (16,17), fiscalité (18,19)
        DB::table('compliance_requirements')
            ->whereIn('id', [5, 6, 8, 9, 11, 12, 14, 15, 16, 17, 18, 19])
            ->delete();

        // Ajouter : any_besoin (mandat + recueil global)
        DB::table('compliance_requirements')->insert([
            [
                'besoin' => 'any_besoin',
                'document_type' => 'mandat_recherche',
                'document_label' => 'Mandat de recherche',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'besoin' => 'any_besoin',
                'document_type' => 'recueil_global',
                'document_label' => 'Recueil Global PP',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Emprunteur
            [
                'besoin' => 'emprunteur',
                'document_type' => 'lettre_mission_emprunteur',
                'document_label' => "Rapport d'adéquation - Emprunteur",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'besoin' => 'emprunteur',
                'document_type' => 'recueil_ade',
                'document_label' => 'Recueil ADE',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        // Supprimer les enregistrements ajoutés
        DB::table('compliance_requirements')
            ->whereIn('document_type', ['mandat_recherche', 'recueil_global', 'lettre_mission_emprunteur', 'recueil_ade'])
            ->whereIn('besoin', ['any_besoin', 'emprunteur'])
            ->delete();
    }
};
