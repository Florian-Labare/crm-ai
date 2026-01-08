<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;

class BaeService
{
    /**
     * Synchronise les données BAE (Prévoyance, Retraite, Épargne, Santé) pour un client
     *
     * @param  array  $data  Données extraites par GPT contenant bae_prevoyance, bae_retraite, bae_epargne, sante_souhait
     */
    public function syncBaeData(Client $client, array $data): void
    {
        Log::info("📊 [BAE] Synchronisation des données BAE pour le client #{$client->id}");
        Log::info("🔍 [BAE DEBUG] Clés reçues dans \$data", ['keys' => array_keys($data)]);

        // 0️⃣ Synchroniser Santé Souhait
        if (isset($data['sante_souhait']) && is_array($data['sante_souhait'])) {
            $this->syncSanteSouhait($client, $data['sante_souhait']);
        } else {
            Log::warning("⚠️ [BAE DEBUG] sante_souhait non trouvé ou pas un tableau", [
                'isset' => isset($data['sante_souhait']),
                'is_array' => isset($data['sante_souhait']) ? is_array($data['sante_souhait']) : 'N/A',
            ]);
        }

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

        // 4️⃣ Créer les entrées BAE vides basées sur les besoins du client
        $this->ensureBaeForBesoins($client, $data['besoins'] ?? $client->besoins ?? []);

        Log::info("✅ [BAE] Synchronisation terminée pour le client #{$client->id}");
    }

    /**
     * Crée les entrées BAE vides pour les besoins détectés
     * Permet d'afficher les sections même si aucune donnée n'est renseignée
     *
     * @param  array  $besoins  Liste des besoins du client
     */
    public function ensureBaeForBesoins(Client $client, array $besoins): void
    {
        if (empty($besoins)) {
            return;
        }

        Log::info("📋 [BAE] Vérification des entrées BAE pour les besoins", ['besoins' => $besoins]);

        foreach ($besoins as $besoin) {
            $besoinNormalized = $this->normalizeBesoinName($besoin);

            switch ($besoinNormalized) {
                case 'prevoyance':
                    if (!$client->baePrevoyance()->exists()) {
                        $client->baePrevoyance()->create([]);
                        Log::info("✅ [BAE PRÉVOYANCE] Entrée vide créée pour le besoin détecté");
                    }
                    break;

                case 'retraite':
                    if (!$client->baeRetraite()->exists()) {
                        $client->baeRetraite()->create([]);
                        Log::info("✅ [BAE RETRAITE] Entrée vide créée pour le besoin détecté");
                    }
                    break;

                case 'epargne':
                case 'placement':
                case 'investissement':
                    if (!$client->baeEpargne()->exists()) {
                        $client->baeEpargne()->create([]);
                        Log::info("✅ [BAE ÉPARGNE] Entrée vide créée pour le besoin détecté");
                    }
                    break;

                case 'sante':
                case 'mutuelle':
                case 'complementaire':
                    if (!$client->santeSouhait()->exists()) {
                        $client->santeSouhait()->create([]);
                        Log::info("✅ [SANTÉ SOUHAIT] Entrée vide créée pour le besoin détecté");
                    }
                    break;
            }
        }
    }

    /**
     * Supprime les entrées BAE correspondant aux besoins retirés
     *
     * @param  array  $removedBesoins  Liste des besoins retirés (ex: ["retraite", "prévoyance"])
     */
    public function removeBaeForBesoins(Client $client, array $removedBesoins): void
    {
        Log::info('🗑️ [BAE] Suppression des BAE pour les besoins retirés', ['besoins' => $removedBesoins]);

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

        Log::info('✅ [BAE] Suppression terminée');
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
        $prevoyanceData = $this->normalizeDateFields($prevoyanceData, ['date_effet']);

        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $prevoyanceData = $this->filterEmptyValues($prevoyanceData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($prevoyanceData)) {
            $prevoyanceData = [];
        }

        Log::info('🛡️ [BAE PRÉVOYANCE] Synchronisation', ['data' => $prevoyanceData]);

        // Récupérer ou créer l'entrée
        $prevoyance = $client->baePrevoyance()->first();

        if ($prevoyance) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (! empty($prevoyanceData)) {
                $prevoyance->update($prevoyanceData);
                Log::info("✅ [BAE PRÉVOYANCE] Mise à jour de l'entrée existante #{$prevoyance->id}");
            } else {
                Log::info('ℹ️ [BAE PRÉVOYANCE] Aucune nouvelle donnée à mettre à jour');
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

        Log::info('🏖️ [BAE RETRAITE] Synchronisation', ['data' => $retraiteData]);

        // Récupérer ou créer l'entrée
        $retraite = $client->baeRetraite()->first();

        if ($retraite) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (! empty($retraiteData)) {
                $retraite->update($retraiteData);
                Log::info("✅ [BAE RETRAITE] Mise à jour de l'entrée existante #{$retraite->id}");
            } else {
                Log::info('ℹ️ [BAE RETRAITE] Aucune nouvelle donnée à mettre à jour');
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
        $epargneData = $this->normalizeDateFields($epargneData, ['donation_date']);

        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $epargneData = $this->filterEmptyValues($epargneData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($epargneData)) {
            $epargneData = [];
        }

        Log::info('💰 [BAE ÉPARGNE] Synchronisation', ['data' => $epargneData]);

        // Récupérer ou créer l'entrée
        $epargne = $client->baeEpargne()->first();

        if ($epargne) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (! empty($epargneData)) {
                $epargne->update($epargneData);
                Log::info("✅ [BAE ÉPARGNE] Mise à jour de l'entrée existante #{$epargne->id}");
            } else {
                Log::info('ℹ️ [BAE ÉPARGNE] Aucune nouvelle donnée à mettre à jour');
            }
        } else {
            // Création
            $epargne = $client->baeEpargne()->create($epargneData);
            Log::info("✅ [BAE ÉPARGNE] Nouvelle entrée créée #{$epargne->id}");
        }
    }

    /**
     * Synchronise les données de Santé Souhait
     */
    private function syncSanteSouhait(Client $client, array $santeData): void
    {
        // Filtrer les valeurs null/vides pour ne pas écraser les données existantes
        $santeData = $this->filterEmptyValues($santeData);

        // Si après filtrage il ne reste rien, créer quand même une entrée vide
        if (empty($santeData)) {
            $santeData = [];
        }

        Log::info('❤️ [SANTÉ SOUHAIT] Synchronisation', ['data' => $santeData]);

        // Récupérer ou créer l'entrée
        $sante = $client->santeSouhait()->first();

        if ($sante) {
            // Mise à jour : on merge les nouvelles données avec les anciennes
            if (! empty($santeData)) {
                $sante->update($santeData);
                Log::info("✅ [SANTÉ SOUHAIT] Mise à jour de l'entrée existante #{$sante->id}");
            } else {
                Log::info('ℹ️ [SANTÉ SOUHAIT] Aucune nouvelle donnée à mettre à jour');
            }
        } else {
            // Création
            $sante = $client->santeSouhait()->create($santeData);
            Log::info("✅ [SANTÉ SOUHAIT] Nouvelle entrée créée #{$sante->id}");
        }
    }

    /**
     * Filtre les valeurs vides (null, "", []) pour éviter d'écraser les données existantes
     */
    private function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            // Garder les valeurs false et 0 (valeurs valides)
            if ($value === false || $value === 0 || $value === '0') {
                return true;
            }

            // Rejeter null, "", et []
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return false;
            }

            return true;
        });
    }

    /**
     * Normalise les champs date attendus vers le format ISO.
     */
    private function normalizeDateFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = $this->normalizeDateToISO((string) $data[$field]);
            }
        }

        return $data;
    }

    /**
     * Normalise une date vers le format ISO (YYYY-MM-DD).
     */
    private function normalizeDateToISO(string $date): ?string
    {
        try {
            $date = trim($date);
            if ($date === '') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            $normalizedDate = $this->normalizeFrenchDateString($date);
            $carbonDate = \Carbon\Carbon::parse($normalizedDate);

            return $carbonDate->format('Y-m-d');

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser la date', ['date' => $date, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise une date avec mois français vers une chaîne parsable par Carbon.
     */
    private function normalizeFrenchDateString(string $date): string
    {
        $normalized = mb_strtolower($date, 'UTF-8');
        $normalized = preg_replace('/\b1er\b/u', '1', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        // Map des mois français (avec et sans accents) vers anglais
        $monthMap = [
            // Avec accents
            'janvier' => 'january',
            'février' => 'february',
            'fevrier' => 'february',
            'mars' => 'march',
            'avril' => 'april',
            'mai' => 'may',
            'juin' => 'june',
            'juillet' => 'july',
            'août' => 'august',
            'aout' => 'august',
            'septembre' => 'september',
            'octobre' => 'october',
            'novembre' => 'november',
            'décembre' => 'december',
            'decembre' => 'december',
        ];

        // Remplacer les mois français par les mois anglais
        foreach ($monthMap as $fr => $en) {
            // Utiliser une regex Unicode pour matcher les mois avec accents
            $pattern = '/\b' . preg_quote($fr, '/') . '\b/ui';
            $normalized = preg_replace($pattern, $en, $normalized);
        }

        // Si c'est un format "DD mois YYYY", le convertir en "DD month YYYY"
        if (preg_match('/^(\d{1,2})\s+(\w+)\s+(\d{4})$/', $normalized, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = $matches[2];
            $year = $matches[3];

            // Vérifier si c'est un mois anglais valide, sinon essayer de parser directement
            $englishMonths = ['january', 'february', 'march', 'april', 'may', 'june',
                             'july', 'august', 'september', 'october', 'november', 'december'];

            if (in_array(strtolower($month), $englishMonths)) {
                return "{$day} {$month} {$year}";
            }
        }

        return $normalized;
    }
}
