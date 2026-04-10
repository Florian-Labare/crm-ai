<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAutreEpargne;
use Illuminate\Support\Facades\Log;

class ClientAutresEpargnesSyncService
{
    /**
     * Synchronise les autres épargnes d'un client avec les données extraites
     *
     * @param  array  $epargnesData  Tableau d'autres épargnes extraites par GPT
     */
    public function syncAutresEpargnes(Client $client, array $epargnesData): void
    {
        Log::info("💎 [AUTRES ÉPARGNES] Synchronisation des autres épargnes pour le client #{$client->id}", [
            'nombre_epargnes_recues' => count($epargnesData),
        ]);

        // Charger les épargnes existantes
        $existingEpargnes = $client->autresEpargnes;
        Log::info("💎 [AUTRES ÉPARGNES] Épargnes existantes: {$existingEpargnes->count()}");

        // Tableau pour suivre les épargnes traitées
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque épargne du tableau
        foreach ($epargnesData as $index => $epargneData) {
            // Filtrer les valeurs vides
            $epargneData = $this->filterEmptyValues($epargneData);

            if (empty($epargneData)) {
                Log::info("💎 [AUTRES ÉPARGNES] Épargne #{$index} sans données - ignorée");

                continue;
            }

            // Tenter de trouver une épargne existante correspondante
            $epargne = $this->findMatchingEpargne($existingEpargnes, $epargneData);

            if ($epargne) {
                // Mise à jour de l'épargne existante
                Log::info("💎 [AUTRES ÉPARGNES] Mise à jour de l'épargne existante #{$epargne->id}");
                $epargne->update($epargneData);
                $processedIds[] = $epargne->id;
            } else {
                // Création d'une nouvelle épargne
                Log::info("💎 [AUTRES ÉPARGNES] Création d'une nouvelle épargne", $epargneData);
                $epargneData['client_id'] = $client->id;
                $newEpargne = ClientAutreEpargne::create($epargneData);
                $processedIds[] = $newEpargne->id;
            }
        }

        // 2️⃣ IMPORTANT: On ne supprime PAS les épargnes existantes qui ne sont pas mentionnées
        // Les autres épargnes s'accumulent au fil des conversations
        $keptEpargnes = $existingEpargnes->whereNotIn('id', $processedIds)->count();
        if ($keptEpargnes > 0) {
            Log::info("💎 [AUTRES ÉPARGNES] Conservation de {$keptEpargnes} épargne(s) existante(s) non mentionnée(s) dans cette extraction");
        }

        Log::info('✅ [AUTRES ÉPARGNES] Synchronisation terminée - '.count($processedIds).' épargne(s) traitée(s), total: '.$client->autresEpargnes()->count());
    }

    /**
     * Trouve une épargne existante correspondant aux données
     */
    private function findMatchingEpargne($existingEpargnes, array $epargneData): ?ClientAutreEpargne
    {
        // Match par designation
        if (isset($epargneData['designation'])) {
            $match = $existingEpargnes->first(function ($epargne) use ($epargneData) {
                return $this->normalizeString($epargne->designation) === $this->normalizeString($epargneData['designation']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match par designation et valeur
        if (isset($epargneData['designation']) && isset($epargneData['valeur'])) {
            $match = $existingEpargnes->first(function ($epargne) use ($epargneData) {
                return $this->normalizeString($epargne->designation) === $this->normalizeString($epargneData['designation'])
                    && abs($epargne->valeur - $epargneData['valeur']) < 0.01;
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
