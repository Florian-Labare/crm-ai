<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportValidationService
{
    private const REQUIRED_FIELDS = ['nom', 'prenom'];

    /**
     * Field validators by field name pattern
     * Supports regex patterns for flexible matching
     */
    private const FIELD_VALIDATORS = [
        'email' => 'validateEmail',
        'telephone' => 'validatePhone',
        'date_naissance' => 'validateDate',
        'date_situation_matrimoniale' => 'validateDate',
        'date_evenement_professionnel' => 'validateDate',
        'date_ouverture' => 'validateDate',
        'code_postal' => 'validatePostalCode',
        'civilite' => 'validateCivilite',
        'situation_matrimoniale' => 'validateSituationMatrimoniale',
        'revenus_annuels' => 'validateNumeric',
        'revenu_montant' => 'validateNumeric',
        'actif_valeur' => 'validateNumeric',
        'bien_valeur_actuelle' => 'validateNumeric',
        'bien_valeur_acquisition' => 'validateNumeric',
        'passif_montant_remboursement' => 'validateNumeric',
        'passif_capital_restant' => 'validateNumeric',
        'epargne_valeur' => 'validateNumeric',
    ];

    /**
     * Field normalizers by field name or pattern
     */
    private const FIELD_NORMALIZERS = [
        // Identity fields
        'civilite' => 'normalizeCivilite',
        'nom' => 'normalizeName',
        'nom_jeune_fille' => 'normalizeName',
        'prenom' => 'normalizeName',
        'lieu_naissance' => 'normalizeName',
        'ville' => 'normalizeName',
        'nationalite' => 'normalizeName',

        // Contact fields
        'email' => 'normalizeEmail',
        'telephone' => 'normalizePhone',
        'code_postal' => 'normalizePostalCode',
        'adresse' => 'normalizeAddress',

        // Date fields
        'date_naissance' => 'normalizeDate',
        'date_situation_matrimoniale' => 'normalizeDate',
        'date_evenement_professionnel' => 'normalizeDate',
        'date_ouverture' => 'normalizeDate',

        // Numeric fields
        'revenus_annuels' => 'normalizeNumber',
        'revenu_montant' => 'normalizeNumber',
        'actif_valeur' => 'normalizeNumber',
        'bien_valeur_actuelle' => 'normalizeNumber',
        'bien_valeur_acquisition' => 'normalizeNumber',
        'bien_annee_acquisition' => 'normalizeYear',
        'passif_montant_remboursement' => 'normalizeNumber',
        'passif_capital_restant' => 'normalizeNumber',
        'passif_duree_restante' => 'normalizeInteger',
        'epargne_valeur' => 'normalizeNumber',

        // Boolean fields
        'fumeur' => 'normalizeBoolean',
        'chef_entreprise' => 'normalizeBoolean',
        'travailleur_independant' => 'normalizeBoolean',
        'mandataire_social' => 'normalizeBoolean',
        'risques_professionnels' => 'normalizeBoolean',
        'activites_sportives' => 'normalizeBoolean',
        'fiscalement_a_charge' => 'normalizeBoolean',
        'garde_alternee' => 'normalizeBoolean',

        // Situation fields
        'situation_matrimoniale' => 'normalizeSituationMatrimoniale',
        'situation_actuelle' => 'normalizeSituationActuelle',
    ];

    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "Le champ {$field} est obligatoire";
            }
        }

        // Validate each field
        foreach ($data as $field => $value) {
            if (empty($value) || is_array($value)) {
                continue;
            }

            $validator = $this->getValidatorForField($field);
            if ($validator && method_exists($this, $validator)) {
                $error = $this->$validator($value);
                if ($error !== null) {
                    $errors[$field] = $error;
                }
            }
        }

        return $errors;
    }

    public function normalize(array $data): array
    {
        $normalized = $data;

        // Convert empty strings to null for all fields
        foreach ($normalized as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $normalized[$key] = null;
            }
        }

        // Normalize each field
        foreach ($normalized as $field => $value) {
            if ($value === null || is_array($value)) {
                continue;
            }

            $normalizer = $this->getNormalizerForField($field);
            if ($normalizer && method_exists($this, $normalizer)) {
                $normalized[$field] = $this->$normalizer($value);
            }
        }

        // Normalize nested data structures
        if (isset($normalized['conjoint']) && is_array($normalized['conjoint'])) {
            $normalized['conjoint'] = $this->normalizeConjoint($normalized['conjoint']);
        }

        if (isset($normalized['enfants']) && is_array($normalized['enfants'])) {
            $normalized['enfants'] = array_map(
                fn ($enfant) => $this->normalizeEnfant($enfant),
                $normalized['enfants']
            );
            // Remove empty enfants
            $normalized['enfants'] = array_filter($normalized['enfants'], fn ($e) => !empty(array_filter($e)));
            $normalized['enfants'] = array_values($normalized['enfants']);
        }

        // Normalize financial data structures
        foreach (['_revenu', '_actif_financier', '_bien_immobilier', '_passif', '_autre_epargne'] as $key) {
            if (isset($normalized[$key]) && is_array($normalized[$key])) {
                $normalized[$key] = $this->normalizeFinancialData($normalized[$key]);
            }
        }

        return $normalized;
    }

    public function validateAndNormalize(array $data): array
    {
        $normalized = $this->normalize($data);
        $errors = $this->validate($normalized);

        return [
            'data' => $normalized,
            'errors' => $errors,
            'is_valid' => empty($errors),
        ];
    }

    /**
     * Get validator for a field based on patterns
     */
    private function getValidatorForField(string $field): ?string
    {
        // Direct match
        if (isset(self::FIELD_VALIDATORS[$field])) {
            return self::FIELD_VALIDATORS[$field];
        }

        // Pattern matching for conjoint fields
        if (str_starts_with($field, 'conjoint_')) {
            $baseField = str_replace('conjoint_', '', $field);
            if (isset(self::FIELD_VALIDATORS[$baseField])) {
                return self::FIELD_VALIDATORS[$baseField];
            }
        }

        // Pattern matching for enfant fields
        if (preg_match('/^enfant\d+_(.+)$/', $field, $matches)) {
            $baseField = $matches[1];
            if (isset(self::FIELD_VALIDATORS[$baseField])) {
                return self::FIELD_VALIDATORS[$baseField];
            }
        }

        return null;
    }

    /**
     * Get normalizer for a field based on patterns
     */
    private function getNormalizerForField(string $field): ?string
    {
        // Direct match
        if (isset(self::FIELD_NORMALIZERS[$field])) {
            return self::FIELD_NORMALIZERS[$field];
        }

        // Pattern matching for conjoint fields
        if (str_starts_with($field, 'conjoint_')) {
            $baseField = str_replace('conjoint_', '', $field);
            if (isset(self::FIELD_NORMALIZERS[$baseField])) {
                return self::FIELD_NORMALIZERS[$baseField];
            }
        }

        // Pattern matching for enfant fields
        if (preg_match('/^enfant\d+_(.+)$/', $field, $matches)) {
            $baseField = $matches[1];
            if (isset(self::FIELD_NORMALIZERS[$baseField])) {
                return self::FIELD_NORMALIZERS[$baseField];
            }
        }

        return null;
    }

    // ==================== VALIDATORS ====================

    private function validateEmail(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return "Format d'email invalide";
        }

        return null;
    }

    private function validatePhone(string $value): ?string
    {
        $cleaned = preg_replace('/[\s.\-()]/', '', $value);
        $cleaned = preg_replace('/[^0-9+]/', '', $cleaned);

        if (!preg_match('/^(\+33|0)[0-9]{9,}$/', $cleaned)) {
            return 'Format de téléphone invalide (attendu: 0X XX XX XX XX ou +33...)';
        }

        return null;
    }

    private function validateDate(string $value): ?string
    {
        try {
            $this->parseDate($value);

            return null;
        } catch (\Exception $e) {
            return 'Format de date invalide';
        }
    }

    private function validatePostalCode(string $value): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $value);

        if (!preg_match('/^\d{5}$/', $cleaned)) {
            return 'Code postal invalide (5 chiffres attendus)';
        }

        return null;
    }

    private function validateCivilite(string $value): ?string
    {
        $normalized = $this->normalizeStringForComparison($value);
        $valid = ['m', 'mr', 'monsieur', 'mme', 'madame', 'mlle', 'mademoiselle', 'm.'];

        if (!in_array($normalized, $valid)) {
            return 'Civilité invalide (M., Mme, Mlle)';
        }

        return null;
    }

    private function validateSituationMatrimoniale(string $value): ?string
    {
        $normalized = $this->normalizeStringForComparison($value);

        // Accept both input variants and normalized output values
        $valid = [
            // Input variants
            'celibataire', 'single',
            'marie', 'mariee', 'married',
            'pacse', 'pacsee',
            'divorce', 'divorcee', 'divorced',
            'veuf', 'veuve', 'widowed',
            'separe', 'separee', 'separated',
            'concubin', 'concubine', 'union libre', 'concubinage',
            // Normalized output values (for validation after normalization)
            'marie(e)', 'pacse(e)', 'divorce(e)', 'veuf/veuve', 'separe(e)',
        ];

        if (!in_array($normalized, $valid)) {
            return 'Situation matrimoniale non reconnue';
        }

        return null;
    }

    /**
     * Normalize string for comparison (lowercase, no accents)
     */
    private function normalizeStringForComparison(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        return $normalized;
    }

    private function validateNumeric($value): ?string
    {
        if (is_numeric($value)) {
            return null;
        }

        $cleaned = str_replace([' ', ','], ['', '.'], $value);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        if (!is_numeric($cleaned)) {
            return 'Valeur numérique invalide';
        }

        return null;
    }

    // ==================== NORMALIZERS ====================

    private function normalizeEmail(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }

    private function normalizePhone(string $value): ?string
    {
        $cleaned = preg_replace('/[\s.\-()]/', '', $value);
        $cleaned = preg_replace('/[^0-9+]/', '', $cleaned);

        if (preg_match('/^(\+33|0)[0-9]{9,}$/', $cleaned)) {
            return $cleaned;
        }

        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        try {
            $date = $this->parseDate($value);

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizePostalCode(string $value): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $value);

        return preg_match('/^\d{5}$/', $cleaned) ? $cleaned : null;
    }

    private function normalizeName(string $value): string
    {
        $name = trim($value);
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        return $name;
    }

    private function normalizeAddress(string $value): string
    {
        return trim($value);
    }

    private function normalizeCivilite(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if (empty($normalized)) {
            return null;
        }

        return match ($normalized) {
            'm', 'mr', 'monsieur', 'm.' => 'Monsieur',
            'mme', 'madame', 'mlle', 'mademoiselle' => 'Madame',
            default => null,
        };
    }

    private function normalizeSituationMatrimoniale(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if (empty($normalized)) {
            return null;
        }

        return match (true) {
            in_array($normalized, ['célibataire', 'celibataire', 'single']) => 'Célibataire',
            in_array($normalized, ['marié', 'marie', 'mariée', 'mariee', 'married']) => 'Marié(e)',
            in_array($normalized, ['pacsé', 'pacse', 'pacsée', 'pacsee']) => 'Pacsé(e)',
            in_array($normalized, ['divorcé', 'divorce', 'divorcée', 'divorcee', 'divorced']) => 'Divorcé(e)',
            in_array($normalized, ['veuf', 'veuve', 'widowed']) => 'Veuf/Veuve',
            in_array($normalized, ['séparé', 'separe', 'séparée', 'separee', 'separated']) => 'Séparé(e)',
            in_array($normalized, ['concubin', 'concubine', 'union libre', 'concubinage']) => 'Union libre',
            default => $value,
        };
    }

    private function normalizeSituationActuelle(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if (empty($normalized)) {
            return null;
        }

        return match (true) {
            in_array($normalized, ['actif', 'en activité', 'en activite', 'salarié', 'salarie', 'emploi']) => 'Actif',
            in_array($normalized, ['retraité', 'retraite', 'retired', 'à la retraite']) => 'Retraité',
            in_array($normalized, ['chômage', 'chomage', 'chômeur', 'chomeur', 'sans emploi', 'demandeur emploi']) => 'Chômage',
            in_array($normalized, ['étudiant', 'etudiant', 'student']) => 'Étudiant',
            in_array($normalized, ['invalide', 'invalidité', 'invalidite']) => 'Invalide',
            in_array($normalized, ['au foyer', 'parent foyer', 'sans activité']) => 'Au foyer',
            default => $value,
        };
    }

    private function normalizeNumber($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        // Remove spaces and replace comma with dot
        $cleaned = str_replace([' ', ','], ['', '.'], $value);
        // Remove currency symbols and other non-numeric chars (except dot and minus)
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function normalizeInteger($value): ?int
    {
        $number = $this->normalizeNumber($value);

        return $number !== null ? (int) round($number) : null;
    }

    private function normalizeYear($value): ?int
    {
        $number = $this->normalizeInteger($value);

        // If it's a valid year (between 1900 and 2100)
        if ($number !== null && $number >= 1900 && $number <= 2100) {
            return $number;
        }

        // Maybe it's a date, try to extract year
        try {
            $date = $this->parseDate((string) $value);

            return (int) $date->format('Y');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeBoolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $truthy = ['true', '1', 'oui', 'yes', 'vrai', 'ok', 'o', 'x', 'checked'];
            $falsy = ['false', '0', 'non', 'no', 'faux', 'n', '', 'unchecked'];

            if (in_array($normalized, $truthy)) {
                return true;
            }
            if (in_array($normalized, $falsy)) {
                return false;
            }
        }

        return null;
    }

    private function normalizeConjoint(array $conjoint): array
    {
        $normalized = [];

        foreach ($conjoint as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $normalizer = $this->getNormalizerForField($key);
            if ($normalizer && method_exists($this, $normalizer)) {
                $normalized[$key] = $this->$normalizer($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeEnfant(array $enfant): array
    {
        $normalized = [];

        foreach ($enfant as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            // Handle composite nom_prenom field
            if ($key === 'nom_prenom' && !empty($value)) {
                $parts = $this->splitNomPrenom($value);
                if (!empty($parts['nom'])) {
                    $normalized['nom'] = $this->normalizeName($parts['nom']);
                }
                if (!empty($parts['prenom'])) {
                    $normalized['prenom'] = $this->normalizeName($parts['prenom']);
                }
                continue;
            }

            $normalizer = $this->getNormalizerForField($key);
            if ($normalizer && method_exists($this, $normalizer)) {
                $normalized[$key] = $this->$normalizer($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeFinancialData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $normalizer = $this->getNormalizerForField($key);
            if ($normalizer && method_exists($this, $normalizer)) {
                $normalized[$key] = $this->$normalizer($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Split "Nom Prénom" or "Prénom Nom" into separate parts
     */
    private function splitNomPrenom(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value), 2);

        if (count($parts) === 2) {
            // Assume format is "NOM PRENOM" (uppercase NOM first) or "Prénom Nom"
            // If first part is all uppercase, it's probably the NOM
            if (mb_strtoupper($parts[0]) === $parts[0]) {
                return ['nom' => $parts[0], 'prenom' => $parts[1]];
            } else {
                // Otherwise assume "Prénom Nom" format
                return ['prenom' => $parts[0], 'nom' => $parts[1]];
            }
        }

        // Can't split, return as prenom
        return ['prenom' => $value, 'nom' => ''];
    }

    private function parseDate(string $value): Carbon
    {
        $value = trim($value);

        // ISO format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value);
        }

        // French format DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d/m/Y', $value);
        }

        // French format DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d-m-Y', $value);
        }

        // French format DD.MM.YYYY
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d.m.Y', $value);
        }

        // Short year format DD/MM/YY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $value, $matches)) {
            return Carbon::createFromFormat('d/m/y', $value);
        }

        // French text format "15 janvier 1990" or "1er mars 2000"
        $frenchMonths = [
            'janvier' => '01', 'février' => '02', 'fevrier' => '02', 'mars' => '03',
            'avril' => '04', 'mai' => '05', 'juin' => '06', 'juillet' => '07',
            'août' => '08', 'aout' => '08', 'septembre' => '09', 'octobre' => '10',
            'novembre' => '11', 'décembre' => '12', 'decembre' => '12',
        ];

        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/\b1er\b/', '1', $normalized);

        foreach ($frenchMonths as $fr => $num) {
            $normalized = str_replace($fr, $num, $normalized);
        }

        return Carbon::parse($normalized);
    }
}
