<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientActifFinancier;
use Illuminate\Support\Facades\Log;

class ClientActifsFinanciersSyncService
{
    /**
     * Synchronise les actifs financiers d'un client avec les données extraites
     *
     * @param  Client  $client
     * @param  array  $actifsData  Tableau d'actifs financiers extraits par GPT
     */
    public function syncActifsFinanciers(Client $client, array $actifsData): void
    {
        Log::info("📈 [ACTIFS FINANCIERS] Synchronisation des actifs financiers pour le client #{$client->id}", [
            'nombre_actifs_recus' => count($actifsData),
        ]);

        // Charger les actifs existants
        $existingActifs = $client->actifsFinanciers;
        Log::info("📈 [ACTIFS FINANCIERS] Actifs existants: {$existingActifs->count()}");

        // Tableau pour suivre les actifs traités
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque actif du tableau
        foreach ($actifsData as $index => $actifData) {
            // Filtrer les valeurs vides
            $actifData = $this->filterEmptyValues($actifData);

            if (empty($actifData)) {
                Log::info("📈 [ACTIFS FINANCIERS] Actif #{$index} sans données - ignoré");
                continue;
            }

            // Tenter de trouver un actif existant correspondant
            $actif = $this->findMatchingActif($existingActifs, $actifData);

            if ($actif) {
                // Mise à jour de l'actif existant
                Log::info("📈 [ACTIFS FINANCIERS] Mise à jour de l'actif existant #{$actif->id}");
                $actif->update($actifData);
                $processedIds[] = $actif->id;
            } else {
                // Création d'un nouvel actif
                Log::info("📈 [ACTIFS FINANCIERS] Création d'un nouvel actif", $actifData);
                $actifData['client_id'] = $client->id;
                $newActif = ClientActifFinancier::create($actifData);
                $processedIds[] = $newActif->id;
            }
        }

        // 2️⃣ Supprimer les actifs qui ne sont plus dans le tableau
        if (!empty($actifsData)) {
            $actifsToDelete = $existingActifs->whereNotIn('id', $processedIds);
            foreach ($actifsToDelete as $actif) {
                Log::info("📈 [ACTIFS FINANCIERS] Suppression de l'actif #{$actif->id} (plus dans le tableau)");
                $actif->delete();
            }
        }

        Log::info('✅ [ACTIFS FINANCIERS] Synchronisation terminée - ' . count($processedIds) . ' actif(s)');
    }

    /**
     * Trouve un actif existant correspondant aux données
     */
    private function findMatchingActif($existingActifs, array $actifData): ?ClientActifFinancier
    {
        // Match par nature et etablissement
        if (isset($actifData['nature']) && isset($actifData['etablissement'])) {
            $match = $existingActifs->first(function ($actif) use ($actifData) {
                return $this->normalizeString($actif->nature) === $this->normalizeString($actifData['nature'])
                    && $this->normalizeString($actif->etablissement) === $this->normalizeString($actifData['etablissement']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match par nature et valeur
        if (isset($actifData['nature']) && isset($actifData['valeur_actuelle'])) {
            $match = $existingActifs->first(function ($actif) use ($actifData) {
                return $this->normalizeString($actif->nature) === $this->normalizeString($actifData['nature'])
                    && abs($actif->valeur_actuelle - $actifData['valeur_actuelle']) < 0.01;
            });
            if ($match) {
                return $match;
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
