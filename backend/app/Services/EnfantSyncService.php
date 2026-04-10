<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Enfant;
use Illuminate\Support\Facades\Log;

class EnfantSyncService
{
    /**
     * Synchronise les enfants d'un client avec les données extraites
     *
     * @param  array  $enfantsData  Tableau d'objets enfants extraits par GPT
     */
    public function syncEnfants(Client $client, array $enfantsData): void
    {
        Log::info("👶 [ENFANTS] Synchronisation des enfants pour le client #{$client->id}", [
            'nombre_enfants_recus' => count($enfantsData),
        ]);

        // Charger les enfants existants
        $existingEnfants = $client->enfants;
        Log::info("👶 [ENFANTS] Enfants existants: {$existingEnfants->count()}");

        // Tableau pour suivre les enfants traités
        $processedIds = [];

        // 1️⃣ Créer ou mettre à jour chaque enfant du tableau
        foreach ($enfantsData as $index => $enfantData) {
            // Filtrer les valeurs vides
            $enfantData = $this->filterEmptyValues($enfantData);

            // Si l'enfant n'a aucune donnée, on le crée quand même comme "placeholder"
            // pour garder la cohérence avec le nombre d'enfants
            if (empty($enfantData)) {
                Log::info("👶 [ENFANTS] Enfant #{$index} sans données - création d'un placeholder");
                $enfantData = ['client_id' => $client->id];
            }

            // Tenter de trouver un enfant existant correspondant
            $enfant = $this->findMatchingEnfant($existingEnfants, $enfantData, $index);

            if ($enfant) {
                // Mise à jour de l'enfant existant
                Log::info("👶 [ENFANTS] Mise à jour de l'enfant existant #{$enfant->id}");
                $enfant->update($enfantData);
                $processedIds[] = $enfant->id;
            } else {
                // Création d'un nouvel enfant
                Log::info("👶 [ENFANTS] Création d'un nouvel enfant", $enfantData);
                $enfantData['client_id'] = $client->id;
                $newEnfant = Enfant::create($enfantData);
                $processedIds[] = $newEnfant->id;
            }
        }

        // 2️⃣ Supprimer les enfants qui ne sont plus dans le tableau
        // ⚠️ MODIFICATION : On ne supprime PLUS automatiquement les enfants manquants
        // car l'IA peut ne retourner qu'un seul enfant pour une mise à jour partielle.
        // La suppression devra être gérée manuellement ou via une intention explicite plus tard.
        /*
        if (! empty($enfantsData) && count($enfantsData) < $existingEnfants->count()) {
            $enfantsToDelete = $existingEnfants->whereNotIn('id', $processedIds);
            foreach ($enfantsToDelete as $enfant) {
                Log::info("👶 [ENFANTS] Suppression de l'enfant #{$enfant->id} (plus dans le tableau)");
                $enfant->delete();
            }
        }
        */

        // 3️⃣ Mettre à jour le champ nombre_enfants du client (SUPPRIMÉ car colonne inexistante)
        // $client->update(['nombre_enfants' => count($processedIds)]);

        Log::info('✅ [ENFANTS] Synchronisation terminée - '.count($processedIds).' enfant(s)');
    }

    /**
     * Trouve un enfant existant correspondant aux données
     *
     * On essaie de matcher par :
     * 1. Prénom ET nom (si les deux sont fournis)
     * 2. Prénom seul (si un seul enfant avec ce prénom existe)
     * 3. Index dans le tableau (en dernier recours)
     */
    private function findMatchingEnfant($existingEnfants, array $enfantData, int $index): ?Enfant
    {
        // 1️⃣ Match par prénom + nom
        if (isset($enfantData['prenom']) && isset($enfantData['nom'])) {
            $match = $existingEnfants->first(function ($enfant) use ($enfantData) {
                return $this->normalizeString($enfant->prenom) === $this->normalizeString($enfantData['prenom'])
                    && $this->normalizeString($enfant->nom) === $this->normalizeString($enfantData['nom']);
            });
            if ($match) {
                Log::info("👶 [ENFANTS] Match trouvé par prénom+nom: {$enfantData['prenom']} {$enfantData['nom']}");

                return $match;
            }
        }

        // 2️⃣ Match par prénom seul (si unique)
        if (isset($enfantData['prenom'])) {
            $matches = $existingEnfants->filter(function ($enfant) use ($enfantData) {
                return $this->normalizeString($enfant->prenom) === $this->normalizeString($enfantData['prenom']);
            });

            if ($matches->count() === 1) {
                Log::info("👶 [ENFANTS] Match trouvé par prénom unique: {$enfantData['prenom']}");

                return $matches->first();
            }
        }

        // 3️⃣ Match par index (si l'enfant à cet index existe)
        if ($index < $existingEnfants->count()) {
            Log::info("👶 [ENFANTS] Match trouvé par index: {$index}");

            return $existingEnfants->get($index);
        }

        // Aucun match trouvé
        Log::info('👶 [ENFANTS] Aucun match trouvé - nouvel enfant sera créé');

        return null;
    }

    /**
     * Filtre les valeurs null et vides
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
