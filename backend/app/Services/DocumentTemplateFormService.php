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
                $fields[] = [
                    'variable' => $variable,
                    'column' => $column,
                    'label' => $questionLabels[$variable] ?? $this->fieldService->labelForVariable($variable),
                    'value' => $defaultValue,
                ];
                continue;
            }

            $value = ($savedValue !== null && $savedValue !== '')
                ? $savedValue
                : $defaultValue;

            $fields[] = [
                'variable' => $variable,
                'column' => $column,
                'label' => $questionLabels[$variable] ?? $this->fieldService->labelForVariable($variable),
                'value' => $value,
            ];
        }

        return $fields;
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
}
