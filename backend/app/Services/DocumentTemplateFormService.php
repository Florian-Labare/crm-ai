<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentTemplateFormService
{
    private const COMPUTED_VARIABLES = [
        'bae_epargne.actifs_immo_total',
        'actifs_immo_total',
        'bae_epargne.actifs_financiers_total',
        'actifs_financiers_total',
        'bae_epargne.actifs_autres_total',
        'actifs_autres_total',
        'bae_epargne.passifs_total_emprunts',
        'passifs_total_emprunts',
        'bae_epargne.charges_totales',
        'charges_totales',
    ];

    public function __construct(
        private readonly DirectTemplateMapper $mapper,
        private readonly DocumentTemplateFieldService $fieldService,
        private readonly DocumentTemplateQuestionService $questionService
    ) {
    }

    /**
     * Retourne les champs du formulaire pour un template donné.
     */
    public function getFields(DocumentTemplate $template, Client $client): array
    {
        $templatePath = storage_path('app/' . $template->file_path);
        $variables = $this->mapper->extractTemplateVariables($templatePath);
        $columnMap = $this->fieldService->mapVariablesToColumns($variables);
        $tableName = $this->fieldService->tableNameForPath($template->file_path);
        $questionLabels = $this->questionService->extractQuestions($templatePath);

        if (!Schema::hasTable($tableName)) {
            throw new \Exception("Table de formulaire introuvable : {$tableName}");
        }

        $row = DB::table($tableName)->where('client_id', $client->id)->first();

        // Mapper avec les DEUX formats (ancien et nouveau) pour compatibilité totale
        // L'ordre est important : legacy d'abord, puis le nouveau format pour les variables reconnues
        $legacyDefaults = $this->mapper->mapLegacyVariables($client);
        $newDefaults = $this->mapper->mapVariables($client, $variables);

        // Ne garder que les valeurs non-vides du nouveau mapper pour ne pas écraser les valeurs legacy
        $newDefaultsFiltered = array_filter($newDefaults, fn($v) => $v !== '' && $v !== null);
        $defaults = array_merge($legacyDefaults, $newDefaultsFiltered);

        $fields = [];
        foreach ($variables as $variable) {
            $column = $columnMap[$variable];
            $savedValue = $row?->{$column} ?? null;
            $defaultValue = $defaults[$variable] ?? null;

            if ($this->isComputedVariable($variable)) {
                $label = $this->buildLabel($variable, $questionLabels);
                $fields[] = [
                    'variable' => $variable,
                    'column' => $column,
                    'label' => $label,
                    'value' => $defaultValue,
                ];
                continue;
            }

            $value = ($savedValue !== null && $savedValue !== '')
                ? $savedValue
                : $defaultValue;

            $label = $this->buildLabel($variable, $questionLabels);

            $fields[] = [
                'variable' => $variable,
                'column' => $column,
                'label' => $label,
                'value' => $value,
            ];
        }

        return $fields;
    }

    /**
     * Construit le label pour une variable en ajoutant le suffixe contextuel
     */
    private function buildLabel(string $variable, array $questionLabels): string
    {
        $baseLabel = $questionLabels[$variable] ?? null;
        $generatedLabel = $this->fieldService->labelForVariable($variable);

        // Si pas de label du template, utiliser le label généré
        if (!$baseLabel) {
            return $generatedLabel;
        }

        // Vérifier si ce label du template est partagé par plusieurs variables
        // Dans ce cas, utiliser le label généré qui est plus spécifique
        if ($this->isSharedTemplateLabel($baseLabel, $variable)) {
            return $generatedLabel;
        }

        // Extraire le suffixe contextuel du label généré
        $suffix = $this->extractSuffix($generatedLabel);

        // Si le label du template contient déjà le contexte, ne pas ajouter de suffixe
        if (!$suffix || $this->labelAlreadyHasContext($baseLabel, $variable)) {
            return $baseLabel;
        }

        return trim($baseLabel) . $suffix;
    }

    /**
     * Vérifie si un label du template est partagé par plusieurs types de champs
     */
    private function isSharedTemplateLabel(string $label, string $variable): bool
    {
        $lower = strtolower($label);
        $varLower = strtolower($variable);

        // Labels connus comme étant partagés
        $sharedLabels = [
            'date et lieu de naissance',
            'adresse complète',
            'nom - prénom - date de naissance',
            'situation actuelle',
            'régime de protection juridique',
            'total',
            'premier objectif',
            // Questionnaire risque - produits financiers
            'obligations ou opcvm',
            'actions ou opcvm',
            'des fip',
            'des scpi',
            'des produits structurés',
            'des produits monétaires',
            'des parts sociales',
            'des titres participatifs',
            'des fps',
            'défiscalisation girardin',
        ];

        foreach ($sharedLabels as $shared) {
            if (str_contains($lower, $shared)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrait le suffixe contextuel d'un label (ex: " (conjoint)", " (enfant 1)")
     */
    private function extractSuffix(string $label): string
    {
        if (preg_match('/(\s*\([^)]+\))$/', $label, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Vérifie si le label du template contient déjà le contexte
     */
    private function labelAlreadyHasContext(string $label, string $variable): bool
    {
        $lower = strtolower($label);
        $varLower = strtolower($variable);

        // Si le label mentionne déjà conjoint/enfant/etc.
        if (str_contains($lower, 'conjoint') && str_contains($varLower, 'conjoint')) {
            return true;
        }
        if (preg_match('/enfant\s*\d/', $lower) && preg_match('/enfant\d/', $varLower)) {
            return true;
        }

        return false;
    }

    /**
     * Retourne uniquement les valeurs sauvegardées (pour override lors de la génération).
     */
    public function getSavedValues(DocumentTemplate $template, Client $client): array
    {
        $templatePath = storage_path('app/' . $template->file_path);
        $variables = $this->mapper->extractTemplateVariables($templatePath);
        $columnMap = $this->fieldService->mapVariablesToColumns($variables);
        $tableName = $this->fieldService->tableNameForPath($template->file_path);

        if (!Schema::hasTable($tableName)) {
            return [];
        }

        $row = DB::table($tableName)->where('client_id', $client->id)->first();
        if (!$row) {
            return [];
        }

        $overrides = [];
        foreach ($variables as $variable) {
            if ($this->isComputedVariable($variable)) {
                continue;
            }
            $column = $columnMap[$variable];
            $value = $row->{$column} ?? null;
            if ($value !== null && $value !== '') {
                $overrides[$variable] = $value;
            }
        }

        return $overrides;
    }

    /**
     * Sauvegarde les valeurs du formulaire pour un client et un template.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveValues(DocumentTemplate $template, Client $client, array $values): void
    {
        $templatePath = storage_path('app/' . $template->file_path);
        $variables = $this->mapper->extractTemplateVariables($templatePath);
        $columnMap = $this->fieldService->mapVariablesToColumns($variables);
        $tableName = $this->fieldService->tableNameForPath($template->file_path);

        if (!Schema::hasTable($tableName)) {
            throw new \Exception("Table de formulaire introuvable : {$tableName}");
        }

        $data = [
            'client_id' => $client->id,
            'updated_at' => now(),
        ];

        foreach ($values as $variable => $value) {
            if ($this->isComputedVariable($variable)) {
                continue;
            }
            $column = $columnMap[$variable] ?? null;
            if (!$column || !Schema::hasColumn($tableName, $column)) {
                continue;
            }

            $data[$column] = is_array($value) ? json_encode($value) : $value;
        }

        $existing = DB::table($tableName)->where('client_id', $client->id)->first();
        if ($existing) {
            DB::table($tableName)->where('client_id', $client->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table($tableName)->insert($data);
        }
    }

    private function isComputedVariable(string $variable): bool
    {
        return in_array($variable, self::COMPUTED_VARIABLES, true);
    }

    private function variableIsSituationActuelle(string $variable): bool
    {
        return $variable === 'clients.situation_actuelle'
            || $variable === 'conjoints.situation_actuelle_statut';
    }

    private function variableIsFullName(string $variable): bool
    {
        return str_ends_with($variable, '.full_name')
            || str_starts_with($variable, 'nomprenom')
            || str_starts_with($variable, 'nomprenom');
    }
}
