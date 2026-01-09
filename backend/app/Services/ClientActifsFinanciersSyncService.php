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
        $actifsData = $this->sanitizeIncomingActifs($actifsData);

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

        // 2️⃣ IMPORTANT: On ne supprime PAS les actifs existants qui ne sont pas mentionnés
        // Les actifs financiers s'accumulent au fil des conversations
        $keptActifs = $existingActifs->whereNotIn('id', $processedIds)->count();
        if ($keptActifs > 0) {
            Log::info("📈 [ACTIFS FINANCIERS] Conservation de {$keptActifs} actif(s) existant(s) non mentionné(s) dans cette extraction");
        }

        Log::info('✅ [ACTIFS FINANCIERS] Synchronisation terminée - ' . count($processedIds) . ' actif(s) traité(s), total: ' . $client->actifsFinanciers()->count());
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

    private function sanitizeIncomingActifs(array $actifsData): array
    {
        $filtered = [];
        foreach ($actifsData as $actif) {
            $actif = $this->filterEmptyValues($actif);
            if (empty($actif)) {
                continue;
            }

            $nature = $this->normalizeString($actif['nature'] ?? '');
            if ($nature === null) {
                continue;
            }

            if ($this->isCryptoNature($nature)) {
                Log::info("📈 [ACTIFS FINANCIERS] Actif crypto ignoré (autres épargnes)", [
                    'nature' => $actif['nature'] ?? 'inconnu',
                ]);
                continue;
            }

            $filtered[] = $actif;
        }

        return $this->deduplicateByKey($filtered);
    }

    private function deduplicateByKey(array $actifs): array
    {
        $seen = [];
        $result = [];

        foreach ($actifs as $actif) {
            $nature = $this->normalizeString($actif['nature'] ?? '');
            $etablissement = $this->normalizeString($actif['etablissement'] ?? '');
            $valueKey = isset($actif['valeur_actuelle']) ? number_format((float) $actif['valeur_actuelle'], 2, '.', '') : '';
            $key = ($nature ?? '') . '|' . ($etablissement ?? '') . '|' . $valueKey;

            if (isset($seen[$key])) {
                $index = $seen[$key];
                $result[$index] = array_merge($result[$index], array_filter($actif, fn($v) => $v !== null && $v !== ''));
                continue;
            }

            $seen[$key] = count($result);
            $result[] = $actif;
        }

        return $result;
    }

    private function isCryptoNature(string $value): bool
    {
        return str_contains($value, 'crypto')
            || str_contains($value, 'bitcoin')
            || str_contains($value, 'btc')
            || str_contains($value, 'ethereum')
            || str_contains($value, 'eth')
            || str_contains($value, 'solana')
            || str_contains($value, 'xrp')
            || str_contains($value, 'token');
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
