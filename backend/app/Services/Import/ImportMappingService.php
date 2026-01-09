<?php

namespace App\Services\Import;

use App\Models\ImportMapping;
use Illuminate\Support\Str;

class ImportMappingService
{
    private const CLIENT_FIELDS = [
        'nom' => ['nom', 'name', 'last_name', 'lastname', 'nom de famille', 'family name'],
        'prenom' => ['prenom', 'prénom', 'first_name', 'firstname', 'given name'],
        'email' => ['email', 'e-mail', 'mail', 'adresse email', 'courriel'],
        'telephone' => ['telephone', 'téléphone', 'tel', 'phone', 'mobile', 'portable', 'numero tel'],
        'date_naissance' => ['date_naissance', 'date de naissance', 'birth_date', 'birthdate', 'dob', 'né le', 'naissance'],
        'lieu_naissance' => ['lieu_naissance', 'lieu de naissance', 'birthplace', 'né à'],
        'nationalite' => ['nationalite', 'nationalité', 'nationality'],
        'adresse' => ['adresse', 'address', 'rue', 'street', 'domicile'],
        'code_postal' => ['code_postal', 'code postal', 'cp', 'postal_code', 'zip', 'zipcode'],
        'ville' => ['ville', 'city', 'commune', 'localité', 'town'],
        'civilite' => ['civilite', 'civilité', 'title', 'titre', 'mr', 'mme'],
        'situation_matrimoniale' => ['situation_matrimoniale', 'situation matrimoniale', 'marital_status', 'état civil', 'statut marital'],
        'profession' => ['profession', 'job', 'métier', 'occupation', 'emploi', 'travail'],
        'situation_actuelle' => ['situation_actuelle', 'situation actuelle', 'status', 'situation professionnelle'],
        'revenus_annuels' => ['revenus_annuels', 'revenus', 'income', 'salaire', 'revenue', 'revenu annuel'],
        'fumeur' => ['fumeur', 'smoker', 'tabac', 'fume'],
        'chef_entreprise' => ['chef_entreprise', 'chef entreprise', 'entrepreneur', 'dirigeant', 'ceo', 'gérant'],
    ];

    private const CONJOINT_FIELDS = [
        'conjoint_nom' => ['nom conjoint', 'conjoint nom', 'spouse_name', 'nom epoux', 'nom épouse'],
        'conjoint_prenom' => ['prenom conjoint', 'conjoint prenom', 'spouse_firstname'],
        'conjoint_date_naissance' => ['date naissance conjoint', 'conjoint date naissance', 'spouse_birthdate'],
        'conjoint_profession' => ['profession conjoint', 'conjoint profession', 'spouse_job'],
    ];

    private const ENFANT_FIELDS = [
        'enfant_nom' => ['nom enfant', 'enfant nom', 'child_name'],
        'enfant_prenom' => ['prenom enfant', 'enfant prenom', 'child_firstname'],
        'enfant_date_naissance' => ['date naissance enfant', 'enfant date naissance', 'child_birthdate'],
    ];

    public function suggestMappings(array $sourceColumns): array
    {
        $suggestions = [];
        $allFields = array_merge(
            self::CLIENT_FIELDS,
            self::CONJOINT_FIELDS,
            self::ENFANT_FIELDS
        );

        foreach ($sourceColumns as $sourceColumn) {
            $normalizedSource = $this->normalizeColumnName($sourceColumn);
            $bestMatch = null;
            $bestScore = 0;

            foreach ($allFields as $targetField => $aliases) {
                foreach ($aliases as $alias) {
                    $normalizedAlias = $this->normalizeColumnName($alias);
                    $score = $this->calculateSimilarity($normalizedSource, $normalizedAlias);

                    if ($score > $bestScore && $score >= 0.6) {
                        $bestScore = $score;
                        $bestMatch = $targetField;
                    }
                }
            }

            $suggestions[$sourceColumn] = [
                'suggested_field' => $bestMatch,
                'confidence' => $bestScore,
                'source_column' => $sourceColumn,
            ];
        }

        return $suggestions;
    }

    public function applyMapping(array $rawData, array $columnMappings): array
    {
        $mappedData = [];

        foreach ($columnMappings as $sourceColumn => $targetField) {
            if (empty($targetField) || !isset($rawData[$sourceColumn])) {
                continue;
            }

            $value = $rawData[$sourceColumn];

            if (str_starts_with($targetField, 'conjoint_')) {
                $field = str_replace('conjoint_', '', $targetField);
                if (!isset($mappedData['conjoint'])) {
                    $mappedData['conjoint'] = [];
                }
                $mappedData['conjoint'][$field] = $value;
            } elseif (str_starts_with($targetField, 'enfant_')) {
                $field = str_replace('enfant_', '', $targetField);
                if (!isset($mappedData['enfants'])) {
                    $mappedData['enfants'] = [];
                }
                if (!isset($mappedData['enfants'][0])) {
                    $mappedData['enfants'][0] = [];
                }
                $mappedData['enfants'][0][$field] = $value;
            } else {
                $mappedData[$targetField] = $value;
            }
        }

        return $mappedData;
    }

    public function createMapping(int $teamId, string $name, string $sourceType, array $columnMappings, ?array $defaultValues = null): ImportMapping
    {
        return ImportMapping::create([
            'team_id' => $teamId,
            'name' => $name,
            'source_type' => $sourceType,
            'column_mappings' => $columnMappings,
            'default_values' => $defaultValues,
        ]);
    }

    public function updateMapping(ImportMapping $mapping, array $data): ImportMapping
    {
        $mapping->update($data);

        return $mapping->fresh();
    }

    public function getTeamMappings(int $teamId): \Illuminate\Database\Eloquent\Collection
    {
        return ImportMapping::where('team_id', $teamId)
            ->orderBy('name')
            ->get();
    }

    public function getAvailableTargetFields(): array
    {
        return [
            'client' => array_keys(self::CLIENT_FIELDS),
            'conjoint' => array_keys(self::CONJOINT_FIELDS),
            'enfant' => array_keys(self::ENFANT_FIELDS),
        ];
    }

    public function validateMapping(array $columnMappings): array
    {
        $errors = [];
        $validFields = array_merge(
            array_keys(self::CLIENT_FIELDS),
            array_keys(self::CONJOINT_FIELDS),
            array_keys(self::ENFANT_FIELDS)
        );

        foreach ($columnMappings as $source => $target) {
            if (!empty($target) && !in_array($target, $validFields)) {
                $errors[] = "Champ cible invalide: {$target} pour la colonne {$source}";
            }
        }

        return $errors;
    }

    private function normalizeColumnName(string $name): string
    {
        $normalized = Str::lower($name);
        $normalized = Str::ascii($normalized);
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        if ($str1 === $str2) {
            return 1.0;
        }

        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            return 0.9;
        }

        $levenshtein = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));

        if ($maxLen === 0) {
            return 1.0;
        }

        $levenshteinScore = 1 - ($levenshtein / $maxLen);

        similar_text($str1, $str2, $percent);
        $similarTextScore = $percent / 100;

        return ($levenshteinScore + $similarTextScore) / 2;
    }
}
