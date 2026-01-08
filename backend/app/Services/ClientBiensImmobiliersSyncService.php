<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBienImmobilier;
use Illuminate\Support\Facades\Log;

class ClientBiensImmobiliersSyncService
{
    /**
     * Synchronise les biens immobiliers d'un client avec les données extraites
     *
     * @param  Client  $client
     * @param  array  $biensData  Tableau de biens immobiliers extraits par GPT
     */
    public function syncBiensImmobiliers(Client $client, array $biensData): void
    {
        Log::info("🏠 [BIENS IMMOBILIERS] Synchronisation des biens immobiliers pour le client #{$client->id}", [
            'nombre_biens_recus' => count($biensData),
        ]);

        // Charger les biens existants
        $existingBiens = $client->biensImmobiliers;
        Log::info("🏠 [BIENS IMMOBILIERS] Biens existants: {$existingBiens->count()}");

        // Tableau pour suivre les biens traités
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque bien du tableau
        foreach ($biensData as $index => $bienData) {
            // Filtrer les valeurs vides
            $bienData = $this->filterEmptyValues($bienData);

            if (empty($bienData)) {
                Log::info("🏠 [BIENS IMMOBILIERS] Bien #{$index} sans données - ignoré");
                continue;
            }

            // Tenter de trouver un bien existant correspondant
            $bien = $this->findMatchingBien($existingBiens, $bienData);

            if ($bien) {
                // Mise à jour du bien existant
                Log::info("🏠 [BIENS IMMOBILIERS] Mise à jour du bien existant #{$bien->id}");
                $bien->update($bienData);
                $processedIds[] = $bien->id;
            } else {
                // Création d'un nouveau bien
                Log::info("🏠 [BIENS IMMOBILIERS] Création d'un nouveau bien", $bienData);
                $bienData['client_id'] = $client->id;
                $newBien = ClientBienImmobilier::create($bienData);
                $processedIds[] = $newBien->id;
            }
        }

        // 2️⃣ IMPORTANT: On ne supprime PAS les biens existants qui ne sont pas mentionnés
        // Les biens immobiliers s'accumulent au fil des conversations
        $keptBiens = $existingBiens->whereNotIn('id', $processedIds)->count();
        if ($keptBiens > 0) {
            Log::info("🏠 [BIENS IMMOBILIERS] Conservation de {$keptBiens} bien(s) existant(s) non mentionné(s) dans cette extraction");
        }

        Log::info('✅ [BIENS IMMOBILIERS] Synchronisation terminée - ' . count($processedIds) . ' bien(s) traité(s), total: ' . $client->biensImmobiliers()->count());
    }

    /**
     * Trouve un bien existant correspondant aux données
     */
    private function findMatchingBien($existingBiens, array $bienData): ?ClientBienImmobilier
    {
        // Match par designation
        if (isset($bienData['designation'])) {
            $match = $existingBiens->first(function ($bien) use ($bienData) {
                return $this->normalizeString($bien->designation) === $this->normalizeString($bienData['designation']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match par designation partielle et valeur
        if (isset($bienData['designation']) && isset($bienData['valeur_actuelle_estimee'])) {
            $match = $existingBiens->first(function ($bien) use ($bienData) {
                $designationMatch = str_contains(
                    $this->normalizeString($bien->designation) ?? '',
                    $this->normalizeString($bienData['designation']) ?? ''
                );
                $valeurMatch = abs($bien->valeur_actuelle_estimee - $bienData['valeur_actuelle_estimee']) < 0.01;
                return $designationMatch && $valeurMatch;
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
