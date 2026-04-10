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
     * @param  array  $actifsData  Tableau d'actifs financiers extraits par GPT
     */
    public function syncActifsFinanciers(Client $client, array $actifsData): void
    {
        $originalCount = count($actifsData);

        // 🔀 ÉTAPE 1: Nettoyer et dédupliquer les données entrantes
        $actifsData = $this->sanitizeIncomingActifs($actifsData);

        // 🔀 ÉTAPE 2: Dédupliquer par nature (fusionner les entrées du même type)
        $actifsData = $this->deduplicateByNature($actifsData);

        Log::info("📈 [ACTIFS FINANCIERS] Synchronisation des actifs financiers pour le client #{$client->id}", [
            'nombre_actifs_recus_original' => $originalCount,
            'nombre_actifs_apres_dedup' => count($actifsData),
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

        Log::info('✅ [ACTIFS FINANCIERS] Synchronisation terminée - '.count($processedIds).' actif(s) traité(s), total: '.$client->actifsFinanciers()->count());
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
     * Déduplique les actifs par nature en fusionnant les infos complémentaires
     *
     * GPT peut retourner plusieurs objets pour le même actif :
     * - Un avec l'établissement
     * - Un autre avec la valeur
     * Cette méthode les fusionne en un seul objet complet
     */
    private function deduplicateByNature(array $actifs): array
    {
        if (count($actifs) <= 1) {
            return $actifs;
        }

        $merged = [];

        foreach ($actifs as $actif) {
            $actif = $this->filterEmptyValues($actif);
            if (empty($actif) || empty($actif['nature'])) {
                continue;
            }

            $nature = $this->normalizeString($actif['nature']);
            $etablissement = isset($actif['etablissement']) ? $this->normalizeString($actif['etablissement']) : null;

            // Chercher une entrée existante avec la même nature
            $found = false;
            foreach ($merged as $existingKey => &$existing) {
                $existingNature = $this->normalizeString($existing['nature'] ?? '');
                $existingEtab = isset($existing['etablissement']) ? $this->normalizeString($existing['etablissement']) : null;

                // Match si même nature ET (même établissement OU l'un des deux n'a pas d'établissement)
                if ($existingNature === $nature) {
                    if ($etablissement === $existingEtab || ! $etablissement || ! $existingEtab) {
                        // Fusionner : garder les infos non vides de chaque côté
                        foreach ($actif as $field => $value) {
                            if (! empty($value) && (empty($existing[$field]) || $existing[$field] === null)) {
                                $existing[$field] = $value;
                            }
                        }
                        // Si le nouveau a un établissement et l'existant non, utiliser le nouveau
                        if (! empty($actif['etablissement']) && empty($existing['etablissement'])) {
                            $existing['etablissement'] = $actif['etablissement'];
                        }
                        $found = true;
                        Log::info("📈 [ACTIFS FINANCIERS] 🔀 Fusion d'actifs de même nature", [
                            'nature' => $nature,
                            'etablissement' => $existing['etablissement'] ?? 'non spécifié',
                        ]);
                        break;
                    }
                }
            }

            if (! $found) {
                $key = $nature.($etablissement ? '_'.$etablissement : '');
                $merged[$key] = $actif;
            }
        }

        $result = array_values($merged);

        if (count($result) < count($actifs)) {
            Log::info('📈 [ACTIFS FINANCIERS] 🔀 Déduplication par nature: '.count($actifs).' → '.count($result).' actif(s)');
        }

        return $result;
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
                Log::info('📈 [ACTIFS FINANCIERS] Actif crypto ignoré (autres épargnes)', [
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
            $key = ($nature ?? '').'|'.($etablissement ?? '').'|'.$valueKey;

            if (isset($seen[$key])) {
                $index = $seen[$key];
                $result[$index] = array_merge($result[$index], array_filter($actif, fn ($v) => $v !== null && $v !== ''));

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
