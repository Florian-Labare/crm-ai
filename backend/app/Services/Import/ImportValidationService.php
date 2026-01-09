<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportValidationService
{
    private const REQUIRED_FIELDS = ['nom', 'prenom'];

    private const FIELD_VALIDATORS = [
        'email' => 'validateEmail',
        'telephone' => 'validatePhone',
        'date_naissance' => 'validateDate',
        'code_postal' => 'validatePostalCode',
        'civilite' => 'validateCivilite',
        'situation_matrimoniale' => 'validateSituationMatrimoniale',
    ];

    private const FIELD_NORMALIZERS = [
        'email' => 'normalizeEmail',
        'telephone' => 'normalizePhone',
        'date_naissance' => 'normalizeDate',
        'code_postal' => 'normalizePostalCode',
        'nom' => 'normalizeName',
        'prenom' => 'normalizeName',
        'ville' => 'normalizeName',
        'civilite' => 'normalizeCivilite',
        'revenus_annuels' => 'normalizeNumber',
        'fumeur' => 'normalizeBoolean',
        'chef_entreprise' => 'normalizeBoolean',
    ];

    public function validate(array $data): array
    {
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "Le champ {$field} est obligatoire";
            }
        }

        foreach (self::FIELD_VALIDATORS as $field => $validator) {
            if (!empty($data[$field])) {
                $error = $this->$validator($data[$field]);
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

        foreach (self::FIELD_NORMALIZERS as $field => $normalizer) {
            if (isset($normalized[$field]) && $normalized[$field] !== null && $normalized[$field] !== '') {
                $normalized[$field] = $this->$normalizer($normalized[$field]);
            }
        }

        if (isset($normalized['conjoint']) && is_array($normalized['conjoint'])) {
            $normalized['conjoint'] = $this->normalizeConjoint($normalized['conjoint']);
        }

        if (isset($normalized['enfants']) && is_array($normalized['enfants'])) {
            $normalized['enfants'] = array_map(
                fn($enfant) => $this->normalizeEnfant($enfant),
                $normalized['enfants']
            );
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
        $normalized = strtolower(trim($value));
        $valid = ['m', 'mr', 'monsieur', 'mme', 'madame', 'mlle', 'mademoiselle'];

        if (!in_array($normalized, $valid)) {
            return 'Civilité invalide (M., Mme, Mlle)';
        }

        return null;
    }

    private function validateSituationMatrimoniale(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $valid = [
            'célibataire', 'celibataire', 'single',
            'marié', 'marie', 'mariée', 'mariee', 'married',
            'pacsé', 'pacse', 'pacsée', 'pacsee',
            'divorcé', 'divorce', 'divorcée', 'divorcee', 'divorced',
            'veuf', 'veuve', 'widowed',
            'séparé', 'separe', 'séparée', 'separee', 'separated',
            'concubin', 'concubine', 'union libre',
        ];

        if (!in_array($normalized, $valid)) {
            return 'Situation matrimoniale non reconnue';
        }

        return null;
    }

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

    private function normalizeCivilite(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'm', 'mr', 'monsieur' => 'M.',
            'mme', 'madame' => 'Mme',
            'mlle', 'mademoiselle' => 'Mlle',
            default => $value,
        };
    }

    private function normalizeNumber($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = str_replace([' ', ','], ['', '.'], $value);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
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
            $truthy = ['true', '1', 'oui', 'yes', 'vrai', 'ok', 'o'];
            $falsy = ['false', '0', 'non', 'no', 'faux', 'n'];

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
        $normalized = $conjoint;

        if (isset($normalized['nom'])) {
            $normalized['nom'] = $this->normalizeName($normalized['nom']);
        }
        if (isset($normalized['prenom'])) {
            $normalized['prenom'] = $this->normalizeName($normalized['prenom']);
        }
        if (isset($normalized['date_naissance'])) {
            $normalized['date_naissance'] = $this->normalizeDate($normalized['date_naissance']);
        }
        if (isset($normalized['email'])) {
            $normalized['email'] = $this->normalizeEmail($normalized['email']);
        }
        if (isset($normalized['telephone'])) {
            $normalized['telephone'] = $this->normalizePhone($normalized['telephone']);
        }

        return $normalized;
    }

    private function normalizeEnfant(array $enfant): array
    {
        $normalized = $enfant;

        if (isset($normalized['nom'])) {
            $normalized['nom'] = $this->normalizeName($normalized['nom']);
        }
        if (isset($normalized['prenom'])) {
            $normalized['prenom'] = $this->normalizeName($normalized['prenom']);
        }
        if (isset($normalized['date_naissance'])) {
            $normalized['date_naissance'] = $this->normalizeDate($normalized['date_naissance']);
        }
        if (isset($normalized['fiscalement_a_charge'])) {
            $normalized['fiscalement_a_charge'] = $this->normalizeBoolean($normalized['fiscalement_a_charge']);
        }

        return $normalized;
    }

    private function parseDate(string $value): Carbon
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value);
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d/m/Y', $value);
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d-m-Y', $value);
        }

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $matches)) {
            return Carbon::createFromFormat('d.m.Y', $value);
        }

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
