<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPassif;
use Illuminate\Support\Facades\Log;

class ClientPassifsSyncService
{
    /**
     * Synchronise les passifs (prêts/emprunts) d'un client avec les données extraites
     *
     * @param  Client  $client
     * @param  array  $passifsData  Tableau de passifs extraits par GPT
     */
    public function syncPassifs(Client $client, array $passifsData): void
    {
        Log::info("📉 [PASSIFS] Synchronisation des passifs pour le client #{$client->id}", [
            'nombre_passifs_recus' => count($passifsData),
        ]);

        // Charger les passifs existants
        $existingPassifs = $client->passifs;
        Log::info("📉 [PASSIFS] Passifs existants: {$existingPassifs->count()}");

        // Tableau pour suivre les passifs traités
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque passif du tableau
        foreach ($passifsData as $index => $passifData) {
            // Filtrer les valeurs vides
            $passifData = $this->filterEmptyValues($passifData);

            if (empty($passifData)) {
                Log::info("📉 [PASSIFS] Passif #{$index} sans données - ignoré");
                continue;
            }

            // Tenter de trouver un passif existant correspondant
            $passif = $this->findMatchingPassif($existingPassifs, $passifData);

            if ($passif) {
                // Mise à jour du passif existant
                Log::info("📉 [PASSIFS] Mise à jour du passif existant #{$passif->id}");
                $passif->update($passifData);
                $processedIds[] = $passif->id;
            } else {
                // Création d'un nouveau passif
                Log::info("📉 [PASSIFS] Création d'un nouveau passif", $passifData);
                $passifData['client_id'] = $client->id;
                $newPassif = ClientPassif::create($passifData);
                $processedIds[] = $newPassif->id;
            }
        }

        // 2️⃣ IMPORTANT: On ne supprime PAS les passifs existants qui ne sont pas mentionnés
        // Les passifs s'accumulent au fil des conversations (un nouveau passif mentionné s'ajoute aux existants)
        $keptPassifs = $existingPassifs->whereNotIn('id', $processedIds)->count();
        if ($keptPassifs > 0) {
            Log::info("📉 [PASSIFS] Conservation de {$keptPassifs} passif(s) existant(s) non mentionné(s) dans cette extraction");
        }

        Log::info('✅ [PASSIFS] Synchronisation terminée - ' . count($processedIds) . ' passif(s) traité(s), total: ' . $client->passifs()->count());
    }

    /**
     * Trouve un passif existant correspondant aux données
     */
    private function findMatchingPassif($existingPassifs, array $passifData): ?ClientPassif
    {
        // Match par nature et preteur
        if (isset($passifData['nature']) && isset($passifData['preteur'])) {
            $match = $existingPassifs->first(function ($passif) use ($passifData) {
                return $this->normalizeString($passif->nature) === $this->normalizeString($passifData['nature'])
                    && $this->normalizeString($passif->preteur) === $this->normalizeString($passifData['preteur']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match par nature et montant
        if (isset($passifData['nature']) && isset($passifData['capital_restant_du'])) {
            $match = $existingPassifs->first(function ($passif) use ($passifData) {
                return $this->normalizeString($passif->nature) === $this->normalizeString($passifData['nature'])
                    && abs($passif->capital_restant_du - $passifData['capital_restant_du']) < 0.01;
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
