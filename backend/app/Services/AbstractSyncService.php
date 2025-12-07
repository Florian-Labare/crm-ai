<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Classe de base pour synchroniser des entités à partir de données d'analyse IA.
 * Exemple : ClientSyncService, ProspectSyncService, InteractionSyncService...
 */
abstract class AbstractSyncService
{
    /**
     * Retourne le nom de la classe du modèle Eloquent.
     * Exemple : return Client::class;
     */
    abstract protected function getModelClass(): string;

    /**
     * Retourne la liste des champs utilisés pour identifier un doublon.
     * Exemple : return ['nom', 'prenom', 'datedenaissance'];
     */
    abstract protected function getMatchFields(): array;

    /**
     * Synchronise (création ou mise à jour) une entité à partir d'un tableau de données.
     */
    public function findOrCreate(array $data): Model
    {
        $modelClass = $this->getModelClass();

        // 1️⃣ Normalisation
        $normalizedData = $this->normalizeData($data);

        // 2️⃣ Recherche d'une entité existante
        $existing = $this->findExisting($normalizedData);

        // 3️⃣ Mise à jour ou création
        if ($existing) {
            $existing->update($normalizedData);

            return $existing;
        }

        return $modelClass::create($normalizedData);
    }

    /**
     * Recherche une entité existante en fonction des champs de correspondance.
     */
    protected function findExisting(array $data): ?Model
    {
        $modelClass = $this->getModelClass();
        $matchFields = $this->getMatchFields();

        $query = $modelClass::query();

        foreach ($matchFields as $field) {
            if (! empty($data[$field])) {
                $query->whereRaw('LOWER('.$field.') = ?', [strtolower($data[$field])]);
            }
        }

        return $query->first();
    }

    /**
     * Normalise les données pour éviter les incohérences
     * (espaces inutiles, casse, formatage...).
     */
    protected function normalizeData(array $data): array
    {
        return collect($data)
            ->map(function ($value) {
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            })
            ->toArray();
    }
}
