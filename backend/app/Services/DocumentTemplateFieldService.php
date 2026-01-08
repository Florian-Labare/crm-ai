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
        }

        $label = str_replace(['.', '_'], ' ', $label);
        $label = Str::of($label)->replace('  ', ' ')->trim()->title()->toString();

        return $label . $suffix;
    }
}
