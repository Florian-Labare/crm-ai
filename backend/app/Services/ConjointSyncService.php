<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Conjoint;
use Illuminate\Support\Facades\Log;

/**
 * Service de synchronisation du conjoint.
 *
 * Gère la création et mise à jour du conjoint d'un client depuis les données
 * extraites par l'IA.
 */
class ConjointSyncService
{
    /**
     * Synchronise les données du conjoint pour un client.
     *
     * @param  Client  $client  Client concerné
     * @param  array  $conjointData  Données du conjoint extraites
     */
    public function syncConjoint(Client $client, array $conjointData): void
    {
        Log::info("💑 [CONJOINT] Synchronisation du conjoint pour le client #{$client->id}", [
            'has_conjoint_data' => ! empty($conjointData),
            'keys' => array_keys($conjointData),
        ]);

        // Si aucune donnée de conjoint, on ne fait rien
        if (empty($conjointData)) {
            Log::info('💑 [CONJOINT] Aucune donnée de conjoint à synchroniser');

            return;
        }

        // Filtrer les valeurs vides
        $conjointData = $this->filterEmptyValues($conjointData);

        // Si après filtrage il ne reste rien, on ne fait rien
        if (empty($conjointData)) {
            Log::info('💑 [CONJOINT] Données de conjoint vides après filtrage');

            return;
        }

        Log::info('💑 [CONJOINT] Données à synchroniser', [
            'fields' => array_keys($conjointData),
        ]);

        // Vérifier si le client a déjà un conjoint
        $existingConjoint = $client->conjoint;

        if ($existingConjoint) {
            // Mise à jour du conjoint existant
            Log::info("💑 [CONJOINT] Mise à jour du conjoint existant #{$existingConjoint->id}", [
                'updated_fields' => array_keys($conjointData),
            ]);

            $existingConjoint->update($conjointData);
        } else {
            // Création d'un nouveau conjoint
            Log::info('💑 [CONJOINT] Création d\'un nouveau conjoint', [
                'fields' => array_keys($conjointData),
            ]);

            $conjointData['client_id'] = $client->id;
            Conjoint::create($conjointData);
        }

        Log::info('✅ [CONJOINT] Synchronisation terminée');
    }

    /**
     * Filtre les valeurs null et vides.
     *
     * @param  array  $data  Données à filtrer
     * @return array Données filtrées
     */
    private function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value, $key) {
            // Ne pas filtrer les booléens (même false)
            if (is_bool($value)) {
                return true;
            }

            // Filtrer null et chaînes vides
            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Normalise une chaîne pour la comparaison.
     *
     * @param  string|null  $value  Valeur à normaliser
     * @return string|null Valeur normalisée
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
