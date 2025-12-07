<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientRevenu;
use Illuminate\Support\Facades\Log;

class ClientRevenusSyncService
{
    /**
     * Synchronise les revenus d'un client avec les données extraites
     *
     * @param  Client  $client
     * @param  array  $revenusData  Tableau de revenus extraits par GPT
     */
    public function syncRevenus(Client $client, array $revenusData): void
    {
        Log::info("💰 [REVENUS] Synchronisation des revenus pour le client #{$client->id}", [
            'nombre_revenus_recus' => count($revenusData),
        ]);

        // Charger les revenus existants
        $existingRevenus = $client->revenus;
        Log::info("💰 [REVENUS] Revenus existants: {$existingRevenus->count()}");

        // Tableau pour suivre les revenus traités
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque revenu du tableau
        foreach ($revenusData as $index => $revenuData) {
            // Filtrer les valeurs vides
            $revenuData = $this->filterEmptyValues($revenuData);

            if (empty($revenuData)) {
                Log::info("💰 [REVENUS] Revenu #{$index} sans données - ignoré");
                continue;
            }

            // Tenter de trouver un revenu existant correspondant
            $revenu = $this->findMatchingRevenu($existingRevenus, $revenuData);

            if ($revenu) {
                // Mise à jour du revenu existant
                Log::info("💰 [REVENUS] Mise à jour du revenu existant #{$revenu->id}");
                $revenu->update($revenuData);
                $processedIds[] = $revenu->id;
            } else {
                // Création d'un nouveau revenu
                Log::info("💰 [REVENUS] Création d'un nouveau revenu", $revenuData);
                $revenuData['client_id'] = $client->id;
                $newRevenu = ClientRevenu::create($revenuData);
                $processedIds[] = $newRevenu->id;
            }
        }

        // 2️⃣ Supprimer les revenus qui ne sont plus dans le tableau
        if (!empty($revenusData)) {
            $revenusToDelete = $existingRevenus->whereNotIn('id', $processedIds);
            foreach ($revenusToDelete as $revenu) {
                Log::info("💰 [REVENUS] Suppression du revenu #{$revenu->id} (plus dans le tableau)");
                $revenu->delete();
            }
        }

        Log::info('✅ [REVENUS] Synchronisation terminée - ' . count($processedIds) . ' revenu(s)');
    }

    /**
     * Trouve un revenu existant correspondant aux données
     */
    private function findMatchingRevenu($existingRevenus, array $revenuData): ?ClientRevenu
    {
        // Match par nature et montant
        if (isset($revenuData['nature']) && isset($revenuData['montant'])) {
            $match = $existingRevenus->first(function ($revenu) use ($revenuData) {
                return $this->normalizeString($revenu->nature) === $this->normalizeString($revenuData['nature'])
                    && abs($revenu->montant - $revenuData['montant']) < 0.01;
            });
            if ($match) {
                return $match;
            }
        }

        // Match par nature seule (si unique)
        if (isset($revenuData['nature'])) {
            $matches = $existingRevenus->filter(function ($revenu) use ($revenuData) {
                return $this->normalizeString($revenu->nature) === $this->normalizeString($revenuData['nature']);
            });

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    /**
     * Filtre les valeurs null et vides
     */
    private function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value, $key) {
            if (is_bool($value)) {
                return true;
            }
            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Normalise une chaîne pour la comparaison
     */
    private function normalizeString(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        return $normalized === '' ? null : $normalized;
    }
}
