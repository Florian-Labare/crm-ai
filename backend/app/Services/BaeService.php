<?php

namespace App\Services;

use App\Models\BaeEpargne;
use App\Models\BaePrevoyance;
use App\Models\BaeRetraite;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class BaeService
{
    /**
     * Synchronise les données BAE (Prévoyance, Retraite, Épargne) pour un client
     *
     * @param Client $client
     * @param array $data Données extraites par GPT contenant bae_prevoyance, bae_retraite, bae_epargne
     * @return void
     */
    public function syncBaeData(Client $client, array $data): void
    {
        Log::info("📊 [BAE] Synchronisation des données BAE pour le client #{$client->id}");

        // 1️⃣ Synchroniser BAE Prévoyance
        if (isset($data['bae_prevoyance']) && is_array($data['bae_prevoyance'])) {
            $this->syncBaePrevoyance($client, $data['bae_prevoyance']);
        }

        // 2️⃣ Synchroniser BAE Retraite
        if (isset($data['bae_retraite']) && is_array($data['bae_retraite'])) {
            $this->syncBaeRetraite($client, $data['bae_retraite']);
        }

        // 3️⃣ Synchroniser BAE Épargne
        if (isset($data['bae_epargne']) && is_array($data['bae_epargne'])) {
            $this->syncBaeEpargne($client, $data['bae_epargne']);
        }

        Log::info("✅ [BAE] Synchronisation terminée pour le client #{$client->id}");
    }

    /**
     * Supprime les entrées BAE correspondant aux besoins retirés
     *
     * @param Client $client
     * @param array $removedBesoins Liste des besoins retirés (ex: ["retraite", "prévoyance"])
     * @return void
     */
    public function removeBaeForBesoins(Client $client, array $removedBesoins): void
    {
        Log::info("🗑️ [BAE] Suppression des BAE pour les besoins retirés", ['besoins' => $removedBesoins]);

        foreach ($removedBesoins as $besoin) {
            $besoinNormalized = $this->normalizeBesoinName($besoin);

            switch ($besoinNormalized) {
                case 'prevoyance':
                    if ($prevoyance = $client->baePrevoyance) {
                        $prevoyance->delete();
                        Log::info("🗑️ [BAE PRÉVOYANCE] Entrée #{$prevoyance->id} supprimée");
                    }
                    break;

                case 'retraite':
                    if ($retraite = $client->baeRetraite) {
                        $retraite->delete();
                        Log::info("🗑️ [BAE RETRAITE] Entrée #{$retraite->id} supprimée");
                    }
                    break;

                case 'epargne':
                    if ($epargne = $client->baeEpargne) {
                        $epargne->delete();
                        Log::info("🗑️ [BAE ÉPARGNE] Entrée #{$epargne->id} supprimée");
                    }
                    break;
            }
        }

        Log::info("✅ [BAE] Suppression terminée");
    }

    /**
     * Normalise le nom d'un besoin pour la comparaison
     */
    private function normalizeBesoinName(string $besoin): string
    {
        $besoin = mb_strtolower($besoin, 'UTF-8');
        $besoin = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $besoin);
        $besoin = preg_replace('/[^a-z0-9]+/', '', $besoin);
        return $besoin;
    }

    /**
     * Synchronise les données de Prévoyance
     */
    private function syncBaePrevoyance(Client $client, array $prevoyanceData): void
    {
        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $prevoyanceData = $this->filterEmptyValues($prevoyanceData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($prevoyanceData)) {
            $prevoyanceData = [];
        }

        Log::info("🛡️ [BAE PRÉVOYANCE] Synchronisation", ['data' => $prevoyanceData]);

        // Récupérer ou créer l'entrée
        $prevoyance = $client->baePrevoyance()->first();

        if ($prevoyance) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (!empty($prevoyanceData)) {
                $prevoyance->update($prevoyanceData);
                Log::info("✅ [BAE PRÉVOYANCE] Mise à jour de l'entrée existante #{$prevoyance->id}");
            } else {
                Log::info("ℹ️ [BAE PRÉVOYANCE] Aucune nouvelle donnée à mettre à jour");
            }
        } else {
            // Création
            $prevoyance = $client->baePrevoyance()->create($prevoyanceData);
            Log::info("✅ [BAE PRÉVOYANCE] Nouvelle entrée créée #{$prevoyance->id}");
        }
    }

    /**
     * Synchronise les données de Retraite
     */
    private function syncBaeRetraite(Client $client, array $retraiteData): void
    {
        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $retraiteData = $this->filterEmptyValues($retraiteData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($retraiteData)) {
            $retraiteData = [];
        }

        Log::info("🏖️ [BAE RETRAITE] Synchronisation", ['data' => $retraiteData]);

        // Récupérer ou créer l'entrée
        $retraite = $client->baeRetraite()->first();

        if ($retraite) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (!empty($retraiteData)) {
                $retraite->update($retraiteData);
                Log::info("✅ [BAE RETRAITE] Mise à jour de l'entrée existante #{$retraite->id}");
            } else {
                Log::info("ℹ️ [BAE RETRAITE] Aucune nouvelle donnée à mettre à jour");
            }
        } else {
            // Création
            $retraite = $client->baeRetraite()->create($retraiteData);
            Log::info("✅ [BAE RETRAITE] Nouvelle entrée créée #{$retraite->id}");
        }
    }

    /**
     * Synchronise les données d'Épargne
     */
    private function syncBaeEpargne(Client $client, array $epargneData): void
    {
        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $epargneData = $this->filterEmptyValues($epargneData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($epargneData)) {
            $epargneData = [];
        }

        Log::info("💰 [BAE ÉPARGNE] Synchronisation", ['data' => $epargneData]);

        // Récupérer ou créer l'entrée
        $epargne = $client->baeEpargne()->first();

        if ($epargne) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (!empty($epargneData)) {
                $epargne->update($epargneData);
                Log::info("✅ [BAE ÉPARGNE] Mise à jour de l'entrée existante #{$epargne->id}");
            } else {
                Log::info("ℹ️ [BAE ÉPARGNE] Aucune nouvelle donnée à mettre à jour");
            }
        } else {
            // Création
            $epargne = $client->baeEpargne()->create($epargneData);
            Log::info("✅ [BAE ÉPARGNE] Nouvelle entrée créée #{$epargne->id}");
        }
    }

    /**
     * Filtre les valeurs vides (null, "", []) pour éviter d'écraser les données existantes
     *
     * @param array $data
     * @return array
     */
    private function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            // Garder les valeurs false et 0 (valeurs valides)
            if ($value === false || $value === 0 || $value === "0") {
                return true;
            }

            // Rejeter null, "", et []
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return false;
            }

            return true;
        });
    }
}
