<?php

namespace Database\Seeders;

use App\Models\ComplianceRequirement;
use Illuminate\Database\Seeder;

class ComplianceRequirementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
                'document_type' => 'avis_imposition',
                'document_label' => "Avis d'imposition",
                'category' => 'fiscal',
                'is_mandatory' => true,
                'priority' => 2,
            ],

            // Prévoyance
            [
                'besoin' => 'prevoyance',
                'document_type' => 'lettre_mission_prevoyance',
                'document_label' => 'Lettre de mission - Prévoyance',
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
                'document_label' => 'Lettre de mission - Retraite',
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
                'document_label' => 'Lettre de mission - Épargne',
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
                'document_label' => 'Lettre de mission - Santé',
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
                'document_label' => 'Lettre de mission - Immobilier',
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
                'document_label' => 'Lettre de mission - Fiscalité',
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
