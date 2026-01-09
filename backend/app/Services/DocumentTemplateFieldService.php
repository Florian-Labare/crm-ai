<?php

namespace App\Services;

use Illuminate\Support\Str;

class DocumentTemplateFieldService
{
    public function tableNameForPath(string $filePath): string
    {
        $baseName = pathinfo($filePath, PATHINFO_FILENAME);
        $slug = Str::slug($baseName, '_');

        return "document_{$slug}_entries";
    }

    public function normalizeVariableToColumn(string $variable): string
    {
        $normalized = str_replace(['.', '[', ']'], '_', $variable);
        $normalized = preg_replace('/_+/', '_', $normalized);
        $normalized = Str::of($normalized)->lower()->ascii()->toString();
        $normalized = preg_replace('/[^a-z0-9_]/', '_', $normalized);
        $normalized = trim($normalized, '_');

        $normalized = $this->limitColumnLength($normalized, $variable);

        return $normalized;
    }

    /**
     * Retourne un mapping variable -> colonne, avec déduplication.
     *
     * @param  string[]  $variables
     * @return array<string, string>
     */
    public function mapVariablesToColumns(array $variables): array
    {
        $mapping = [];
        $used = [];

        foreach ($variables as $variable) {
            $column = $this->normalizeVariableToColumn($variable);
            $base = $column;
            $suffix = 2;

            while (in_array($column, $used, true)) {
                $column = $this->limitColumnLength($base . '_' . $suffix, $variable);
                $suffix++;
            }

            $mapping[$variable] = $column;
            $used[] = $column;
        }

        return $mapping;
    }

    private function limitColumnLength(string $column, string $variable): string
    {
        $maxLength = 64;
        if (strlen($column) <= $maxLength) {
            return $column;
        }

        $hash = substr(sha1($variable), 0, 8);
        $suffix = '_' . $hash;
        $trimLength = $maxLength - strlen($suffix);

        return substr($column, 0, $trimLength) . $suffix;
    }

    public function labelForVariable(string $variable): string
    {
        $label = $variable;
        $suffix = '';

        // Format nouveau: enfants[0].field
        if (preg_match('/^enfants\\[(\\d+)]\\.(.+)$/', $variable, $matches)) {
            $index = (int) $matches[1] + 1;
            $label = $matches[2];
            $suffix = " (enfant {$index})";
        } elseif (str_starts_with($variable, 'clients.')) {
            $label = substr($variable, strlen('clients.'));
            $suffix = ' (client)';
        } elseif (str_starts_with($variable, 'conjoints.')) {
            $label = substr($variable, strlen('conjoints.'));
            $suffix = ' (conjoint)';
        } elseif (str_starts_with($variable, 'bae_')) {
            $parts = explode('.', $variable, 2);
            $label = $parts[1] ?? $variable;
            $suffix = ' (BAE)';
        } elseif (str_starts_with($variable, 'questionnaire_risque')) {
            $parts = explode('.', $variable, 2);
            $label = $parts[1] ?? $variable;
            $suffix = ' (questionnaire)';
        } else {
            // Format legacy: détecter les suffixes
            $result = $this->parseLegacyVariable($variable);
            $label = $result['label'];
            $suffix = $result['suffix'];
        }

        $label = str_replace(['.', '_'], ' ', $label);
        $label = Str::of($label)->replace('  ', ' ')->trim()->title()->toString();

        return $label . $suffix;
    }

    /**
     * Parse les variables au format legacy pour extraire le label et le suffixe contextuel
     */
    private function parseLegacyVariable(string $variable): array
    {
        $lower = strtolower($variable);

        // Champs spécifiques avec labels clairs
        $specificLabels = $this->getSpecificLabel($variable);
        if ($specificLabels) {
            return $specificLabels;
        }

        // Conjoint: nomconjoint, prenomconjoint, etc.
        if (str_ends_with($lower, 'conjoint')) {
            $base = substr($variable, 0, -8);
            $fieldSuffix = $this->getFieldTypeSuffix($base);
            return ['label' => $base, 'suffix' => $fieldSuffix . ' (conjoint)'];
        }

        // Enfants: nomprenomenfant1, datenaissanceenfant11, fiscalcharge1, etc.
        if (preg_match('/^(.+?)enfant(\d+)$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (enfant {$m[2]})"];
        }
        if (preg_match('/^fiscalcharge(\d+)$/i', $variable, $m)) {
            return ['label' => 'Fiscalement à charge', 'suffix' => " (enfant {$m[1]})"];
        }

        // Actifs financiers: nature1financier, etablissementfinancier1, etc.
        if (preg_match('/^(.+?)(\d+)financier$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (actif financier {$m[2]})"];
        }
        if (preg_match('/^(.+?)financier(\d+)$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (actif financier {$m[2]})"];
        }

        // Biens immobiliers: designation4immo, detenteur4immo, etc.
        if (preg_match('/^(.+?)(\d+)immo$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (bien immo {$m[2]})"];
        }
        if (preg_match('/^(.+?)immo(\d+)$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (bien immo {$m[2]})"];
        }

        // Passifs/Emprunts: preteur1passif, capitalrestantdu1, etc.
        if (preg_match('/^(.+?)(\d+)passif$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (emprunt {$m[2]})"];
        }
        if (preg_match('/^preteur(\d+)$/i', $variable, $m)) {
            return ['label' => 'Prêteur', 'suffix' => " (emprunt {$m[1]})"];
        }
        if (preg_match('/^periodicite(\d+)$/i', $variable, $m)) {
            return ['label' => 'Périodicité', 'suffix' => " (emprunt {$m[1]})"];
        }
        if (preg_match('/^montantremboursement(\d+)$/i', $variable, $m)) {
            return ['label' => 'Montant remboursement', 'suffix' => " (emprunt {$m[1]})"];
        }
        if (preg_match('/^capitalrestantdu(\d+)$/i', $variable, $m)) {
            return ['label' => 'Capital restant dû', 'suffix' => " (emprunt {$m[1]})"];
        }
        if (preg_match('/^(passifdureerestant|capitalrestantcourir|dureeresteacourri)(\d+)$/i', $variable, $m)) {
            return ['label' => 'Durée restante', 'suffix' => " (emprunt {$m[2]})"];
        }

        // Autres épargnes: epargneautre7, epargnedesignation8, etc.
        if (preg_match('/^epargne(.+?)(\d+)$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (autre épargne {$m[2]})"];
        }
        if (preg_match('/^(.+?)autre(\d+)$/i', $variable, $m)) {
            return ['label' => $m[1], 'suffix' => " (autre épargne {$m[2]})"];
        }

        // Nature d'emprunt: natureA, natureB, natureC, etc.
        if (preg_match('/^nature([A-E])$/i', $variable, $m)) {
            $index = ord(strtoupper($m[1])) - ord('A') + 1;
            return ['label' => 'Nature', 'suffix' => " (emprunt {$index})"];
        }
        if (preg_match('/^periodicite([A-E])$/i', $variable, $m)) {
            $index = ord(strtoupper($m[1])) - ord('A') + 1;
            return ['label' => 'Périodicité', 'suffix' => " (charge {$index})"];
        }
        if (preg_match('/^montant([A-E])$/i', $variable, $m)) {
            $index = ord(strtoupper($m[1])) - ord('A') + 1;
            return ['label' => 'Montant', 'suffix' => " (charge {$index})"];
        }

        // Questionnaire risque: opcvmdominanteactionoperation, etc.
        if (preg_match('/operation|opert|real/i', $variable) && !preg_match('/montant/i', $variable)) {
            return ['label' => $variable, 'suffix' => ' - opérations'];
        }
        if (preg_match('/montant.*annuel|montannuel|montaannuel/i', $variable)) {
            return ['label' => $variable, 'suffix' => ' - montant annuel'];
        }

        return ['label' => $variable, 'suffix' => ''];
    }

    /**
     * Retourne un label spécifique pour certaines variables connues
     */
    private function getSpecificLabel(string $variable): ?array
    {
        $map = [
            // Client - Identité
            'nom' => ['label' => 'Nom', 'suffix' => ''],
            'prenom' => ['label' => 'Prénom', 'suffix' => ''],
            'nomjeunefille' => ['label' => 'Nom de jeune fille', 'suffix' => ''],
            'datenaissance' => ['label' => 'Date de naissance', 'suffix' => ''],
            'lieunaissance' => ['label' => 'Lieu de naissance', 'suffix' => ''],
            'nationalite' => ['label' => 'Nationalité', 'suffix' => ''],
            'situationmatrimoniale' => ['label' => 'Situation matrimoniale', 'suffix' => ''],

            // Client - Coordonnées
            'adresse' => ['label' => 'Adresse', 'suffix' => ''],
            'codepostal' => ['label' => 'Code postal', 'suffix' => ''],
            'ville' => ['label' => 'Ville', 'suffix' => ''],
            'email' => ['label' => 'Email', 'suffix' => ''],
            'numerotel' => ['label' => 'Téléphone', 'suffix' => ''],

            // Client - Professionnel
            'situationactuelle' => ['label' => 'Situation professionnelle', 'suffix' => ''],
            'professionn' => ['label' => 'Profession', 'suffix' => ''],
            'chefentreprisee' => ['label' => 'Chef d\'entreprise', 'suffix' => ''],

            // Conjoint - Identité
            'nomconjoint' => ['label' => 'Nom', 'suffix' => ' (conjoint)'],
            'prenomconjoint' => ['label' => 'Prénom', 'suffix' => ' (conjoint)'],
            'nomjeunefilleconjoint' => ['label' => 'Nom de jeune fille', 'suffix' => ' (conjoint)'],
            'datenaissanceconjoint' => ['label' => 'Date de naissance', 'suffix' => ' (conjoint)'],
            'lieunaissanceconjoint' => ['label' => 'Lieu de naissance', 'suffix' => ' (conjoint)'],
            'nationaliteconjoint' => ['label' => 'Nationalité', 'suffix' => ' (conjoint)'],

            // Conjoint - Coordonnées
            'adresseconjoint' => ['label' => 'Adresse', 'suffix' => ' (conjoint)'],
            'codepostalconjoint' => ['label' => 'Code postal', 'suffix' => ' (conjoint)'],
            'villeconjoint' => ['label' => 'Ville', 'suffix' => ' (conjoint)'],

            // Conjoint - Professionnel
            'actuelleconjointsituation' => ['label' => 'Situation professionnelle', 'suffix' => ' (conjoint)'],
            'professionconjointnn' => ['label' => 'Profession', 'suffix' => ' (conjoint)'],
            'chefentrepriseconjoint' => ['label' => 'Chef d\'entreprise', 'suffix' => ' (conjoint)'],

            // Retraite
            'ageretraitedepart' => ['label' => 'Âge départ retraite', 'suffix' => ''],
            'ageretraitedepartconjoint' => ['label' => 'Âge départ retraite', 'suffix' => ' (conjoint)'],
            'siretraiteconjoint' => ['label' => 'Retraité', 'suffix' => ' (conjoint)'],
            'siretraitedateeven' => ['label' => 'Date événement retraite', 'suffix' => ''],

            // Enfants
            'nomprenomenfant1' => ['label' => 'Nom et prénom', 'suffix' => ' (enfant 1)'],
            'datenaissanceenfant11' => ['label' => 'Date de naissance', 'suffix' => ' (enfant 1)'],
            'fiscalcharge1' => ['label' => 'À charge fiscalement', 'suffix' => ' (enfant 1)'],
            'nomprenomenfant2' => ['label' => 'Nom et prénom', 'suffix' => ' (enfant 2)'],
            'datenaissanceenfant2' => ['label' => 'Date de naissance', 'suffix' => ' (enfant 2)'],
            'fiscalcharge2' => ['label' => 'À charge fiscalement', 'suffix' => ' (enfant 2)'],
            'nomprenomenfant3' => ['label' => 'Nom et prénom', 'suffix' => ' (enfant 3)'],
            'datenaissanceenfant3' => ['label' => 'Date de naissance', 'suffix' => ' (enfant 3)'],
            'fiscalcharge3' => ['label' => 'À charge fiscalement', 'suffix' => ' (enfant 3)'],

            // Totaux
            'totalcharges' => ['label' => 'Total charges', 'suffix' => ''],
            'epargneautres' => ['label' => 'Total autres épargnes', 'suffix' => ''],

            // Dates document
            'Date' => ['label' => 'Date du document', 'suffix' => ''],
            'Datedocgener' => ['label' => 'Date de génération', 'suffix' => ''],
        ];

        $lower = strtolower($variable);
        foreach ($map as $key => $value) {
            if (strtolower($key) === $lower) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Retourne un suffixe de type de champ pour les champs composés
     */
    private function getFieldTypeSuffix(string $fieldName): string
    {
        $lower = strtolower($fieldName);

        if (str_contains($lower, 'datenaissance')) {
            return ' - date';
        }
        if (str_contains($lower, 'lieunaissance')) {
            return ' - lieu';
        }
        if (str_contains($lower, 'codepostal')) {
            return ' - code postal';
        }
        if (str_contains($lower, 'ville')) {
            return ' - ville';
        }
        if (str_contains($lower, 'adresse') && !str_contains($lower, 'code') && !str_contains($lower, 'ville')) {
            return ' - adresse';
        }

        return '';
    }
}
