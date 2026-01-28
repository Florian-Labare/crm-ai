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

        // 🔀 ÉTAPE 1: Dédupliquer les données entrantes AVANT traitement
        // Fusionne les entrées de même nature pour éviter les doublons
        $passifsData = $this->deduplicateIncomingPassifs($passifsData);

        Log::info("📉 [PASSIFS] Après déduplication entrante: " . count($passifsData) . " passif(s)");

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
     * Déduplique les passifs entrants en fusionnant ceux de même nature/prêteur
     *
     * GPT peut retourner plusieurs objets pour le même crédit :
     * - Un avec le prêteur et le montant de remboursement
     * - Un autre avec le capital restant dû
     * Cette méthode les fusionne en un seul objet complet
     */
    private function deduplicateIncomingPassifs(array $passifs): array
    {
        if (count($passifs) <= 1) {
            return $passifs;
        }

        $merged = [];

        foreach ($passifs as $passif) {
            $passif = $this->filterEmptyValues($passif);
            if (empty($passif) || empty($passif['nature'])) {
                continue;
            }

            $nature = $this->normalizeString($passif['nature']);
            $preteur = isset($passif['preteur']) ? $this->normalizeString($passif['preteur']) : null;

            // Clé de regroupement : nature + prêteur (si disponible)
            $key = $nature . ($preteur ? '_' . $preteur : '');

            // Chercher une entrée existante avec la même nature
            $found = false;
            foreach ($merged as $existingKey => &$existing) {
                $existingNature = $this->normalizeString($existing['nature'] ?? '');
                $existingPreteur = isset($existing['preteur']) ? $this->normalizeString($existing['preteur']) : null;

                // Match si même nature ET (même prêteur OU l'un des deux n'a pas de prêteur)
                if ($existingNature === $nature) {
                    if ($preteur === $existingPreteur || !$preteur || !$existingPreteur) {
                        // Fusionner : garder les infos non vides de chaque côté
                        foreach ($passif as $field => $value) {
                            if (!empty($value) && (empty($existing[$field]) || $existing[$field] === null)) {
                                $existing[$field] = $value;
                            }
                        }
                        // Si le nouveau a un prêteur et l'existant non, utiliser le nouveau prêteur
                        if (!empty($passif['preteur']) && empty($existing['preteur'])) {
                            $existing['preteur'] = $passif['preteur'];
                        }
                        $found = true;
                        Log::info("📉 [PASSIFS] 🔀 Fusion de passifs de même nature", [
                            'nature' => $nature,
                            'preteur' => $existing['preteur'] ?? 'non spécifié',
                        ]);
                        break;
                    }
                }
            }

            if (!$found) {
                $merged[$key] = $passif;
            }
        }

        $result = array_values($merged);

        if (count($result) < count($passifs)) {
            Log::info("📉 [PASSIFS] 🔀 Déduplication entrante: " . count($passifs) . " → " . count($result) . " passif(s)");
        }

        return $result;
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
