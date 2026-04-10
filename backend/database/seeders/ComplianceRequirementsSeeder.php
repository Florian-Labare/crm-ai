<?php

namespace Database\Seeders;

use App\Models\ComplianceRequirement;
use Illuminate\Database\Seeder;

class ComplianceRequirementsSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $requirements = [
            // Documents globaux (requis pour tous les clients)
            [
                'besoin' => 'global',
                'document_type' => 'cni',
                'document_label' => "Carte d'identité",
                'category' => 'identity',
                'is_mandatory' => true,
                'priority' => 1,
            ],
            [
                'besoin' => 'global',
                'document_type' => 'avis_imposition_n1',
                'document_label' => "Avis d'imposition N-1",
                'category' => 'fiscal',
                'is_mandatory' => true,
                'priority' => 2,
            ],
            [
                'besoin' => 'global',
                'document_type' => 'rib',
                'document_label' => 'RIB',
                'category' => 'banking',
                'is_mandatory' => true,
                'priority' => 3,
            ],

            // Prévoyance
            [
                'besoin' => 'prevoyance',
                'document_type' => 'lettre_mission_prevoyance',
                'document_label' => "Rapport d'adéquation - Prévoyance",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 10,
            ],
            [
                'besoin' => 'prevoyance',
                'document_type' => 'der_prevoyance',
                'document_label' => 'DER - Prévoyance',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 11,
            ],
            [
                'besoin' => 'prevoyance',
                'document_type' => 'fiche_conseil_prevoyance',
                'document_label' => 'Fiche conseil - Prévoyance',
                'category' => 'regulatory',
                'is_mandatory' => false,
                'priority' => 12,
            ],

            // Retraite
            [
                'besoin' => 'retraite',
                'document_type' => 'lettre_mission_retraite',
                'document_label' => "Rapport d'adéquation - Retraite",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 20,
            ],
            [
                'besoin' => 'retraite',
                'document_type' => 'der_retraite',
                'document_label' => 'DER - Retraite',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 21,
            ],
            [
                'besoin' => 'retraite',
                'document_type' => 'fiche_conseil_retraite',
                'document_label' => 'Fiche conseil - Retraite',
                'category' => 'regulatory',
                'is_mandatory' => false,
                'priority' => 22,
            ],

            // Épargne
            [
                'besoin' => 'epargne',
                'document_type' => 'lettre_mission_epargne',
                'document_label' => "Rapport d'adéquation - Épargne",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 30,
            ],
            [
                'besoin' => 'epargne',
                'document_type' => 'der_epargne',
                'document_label' => 'DER - Épargne',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 31,
            ],
            [
                'besoin' => 'epargne',
                'document_type' => 'fiche_conseil_epargne',
                'document_label' => 'Fiche conseil - Épargne',
                'category' => 'regulatory',
                'is_mandatory' => false,
                'priority' => 32,
            ],

            // Santé
            [
                'besoin' => 'sante',
                'document_type' => 'lettre_mission_sante',
                'document_label' => "Rapport d'adéquation - Santé",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 40,
            ],
            [
                'besoin' => 'sante',
                'document_type' => 'fiche_ipid_sante',
                'document_label' => 'Fiche IPID - Santé',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 41,
            ],
            [
                'besoin' => 'sante',
                'document_type' => 'devis_sante',
                'document_label' => 'Devis - Santé',
                'category' => 'regulatory',
                'is_mandatory' => false,
                'priority' => 42,
            ],

            // Immobilier
            [
                'besoin' => 'immobilier',
                'document_type' => 'lettre_mission_immobilier',
                'document_label' => "Rapport d'adéquation - Immobilier",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 50,
            ],
            [
                'besoin' => 'immobilier',
                'document_type' => 'der_immobilier',
                'document_label' => 'DER - Immobilier',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 51,
            ],

            // Fiscalité
            [
                'besoin' => 'fiscalite',
                'document_type' => 'lettre_mission_fiscalite',
                'document_label' => "Rapport d'adéquation - Fiscalité",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 60,
            ],
            [
                'besoin' => 'fiscalite',
                'document_type' => 'der_fiscalite',
                'document_label' => 'DER - Fiscalité',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 61,
            ],

            // any_besoin — Mandat & Mission (requis pour tout client avec au moins un besoin)
            [
                'besoin' => 'any_besoin',
                'document_type' => 'mandat_recherche',
                'document_label' => 'Mandat de recherche',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 1,
            ],
            [
                'besoin' => 'any_besoin',
                'document_type' => 'recueil_global',
                'document_label' => 'Recueil Global PP',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 2,
            ],
            [
                'besoin' => 'any_besoin',
                'document_type' => 'der_signe',
                'document_label' => 'DER signé',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 3,
            ],

            // Emprunteur
            [
                'besoin' => 'emprunteur',
                'document_type' => 'lettre_mission_emprunteur',
                'document_label' => "Rapport d'adéquation - Emprunteur",
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 1,
            ],
            [
                'besoin' => 'emprunteur',
                'document_type' => 'recueil_ade',
                'document_label' => 'Recueil ADE',
                'category' => 'regulatory',
                'is_mandatory' => true,
                'priority' => 2,
            ],
        ];

        foreach ($requirements as $requirement) {
            ComplianceRequirement::updateOrCreate(
                [
                    'besoin' => $requirement['besoin'],
                    'document_type' => $requirement['document_type'],
                ],
                $requirement
            );
        }
    }
}
