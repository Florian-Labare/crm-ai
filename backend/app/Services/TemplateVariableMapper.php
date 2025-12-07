<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;

class TemplateVariableMapper
{
    private array $mapping;

    public function __construct()
    {
        $this->mapping = config('document_mapping');
    }

    /**
     * Mappe toutes les variables du template avec les données du client
     */
    public function mapVariables(Client $client): array
    {
        // Charger toutes les relations nécessaires
        $client->load([
            'conjoint',
            'enfants',
            'santeSouhait',
            'baePrevoyance',
            'baeRetraite',
            'baeEpargne',
            'questionnaireRisque.financier',
            'questionnaireRisque.connaissances',
            'questionnaireRisque.quiz',
        ]);

        $variables = [];

        foreach ($this->mapping as $templateVar => $config) {
            $variables[$templateVar] = $this->resolveVariable($client, $config);
        }

        return $variables;
    }

    /**
     * Résout la valeur d'une variable en fonction de sa configuration
     */
    private function resolveVariable(Client $client, $config): string
    {
        // Si une valeur par défaut est spécifiée et aucune source, retourner la valeur par défaut
        if (isset($config['default']) && ! isset($config['source'])) {
            return $config['default'];
        }

        $value = null;

        // Récupérer la valeur selon la source
        if (isset($config['source'])) {
            switch ($config['source']) {
                case 'client':
                    $value = $client->{$config['field']} ?? null;
                    break;

                case 'conjoint':
                    $value = $client->conjoint->{$config['field']} ?? null;
                    break;

                case 'bae_retraite':
                    $value = $client->baeRetraite->{$config['field']} ?? null;
                    break;

                case 'bae_epargne':
                    $value = $client->baeEpargne->{$config['field']} ?? null;
                    break;

                case 'questionnaire':
                    $value = $client->questionnaireRisque->{$config['field']} ?? null;
                    break;

                case 'questionnaire_financier':
                    $value = $client->questionnaireRisque?->financier->{$config['field']} ?? null;
                    break;

                case 'questionnaire_connaissances':
                    $value = $client->questionnaireRisque?->connaissances->{$config['field']} ?? null;
                    break;

                case 'questionnaire_quiz':
                    $value = $client->questionnaireRisque?->quiz->{$config['field']} ?? null;
                    break;

                case 'computed':
                    if (isset($config['computed']) && is_callable($config['computed'])) {
                        $value = $config['computed']($client);
                    }
                    break;
            }
        }

        // Appliquer le formatage si spécifié
        if (isset($config['format']) && $value !== null) {
            $value = $this->formatValue($value, $config['format'], $config);
        }

        // Si la valeur est toujours null, utiliser la valeur par défaut ou une chaîne vide
        if ($value === null) {
            $value = $config['default'] ?? '';
        }

        return (string) $value;
    }

    /**
     * Formate une valeur selon le type spécifié
     */
    private function formatValue($value, string $format, array $config = []): string
    {
        switch ($format) {
            case 'date':
                if (empty($value)) {
                    return '';
                }
                try {
                    return Carbon::parse($value)->format('d/m/Y');
                } catch (\Exception $e) {
                    return '';
                }

            case 'currency':
                if (empty($value) || $value == 0) {
                    return '';
                }

                return number_format((float) $value, 2, ',', ' ').' €';

            case 'boolean':
                if ($value === null) {
                    return '';
                }

                return $value ? 'Oui' : 'Non';

            case 'enum':
                if ($value === null) {
                    return '';
                }
                $mapping = $config['mapping'] ?? [];
                $key = (string) $value;

                return $mapping[$key] ?? (string) $value;

            case 'quiz':
                if ($value === null || $value === '') {
                    return '';
                }
                $mapping = $config['mapping'] ?? [
                    'vrai' => 'Vrai',
                    'faux' => 'Faux',
                    'aucune_idee' => 'Aucune idée',
                ];
                $key = strtolower((string) $value);

                return $mapping[$key] ?? ucfirst(str_replace('_', ' ', $key));

            case 'text':
            default:
                return (string) $value;
        }
    }

    /**
     * Retourne la liste de toutes les variables disponibles
     */
    public function getAvailableVariables(): array
    {
        return array_keys($this->mapping);
    }

    /**
     * Retourne les statistiques sur les variables mappées
     */
    public function getMappingStats(): array
    {
        $total = count($this->mapping);
        $mapped = count(array_filter($this->mapping, fn ($config) => isset($config['source'])));
        $defaults = count(array_filter($this->mapping, fn ($config) => isset($config['default']) && ! isset($config['source'])));

        return [
            'total' => $total,
            'mapped' => $mapped,
            'defaults' => $defaults,
            'coverage' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
        ];
    }
}
