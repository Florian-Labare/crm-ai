<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPendingChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Services de synchronisation des relations

class MergeService
{
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    // Champs critiques qui nécessitent une attention particulière
    private const CRITICAL_FIELDS = [
        'email',
        'telephone',
        'adresse',
        'code_postal',
        'ville',
        'revenus_annuels',
        'date_naissance',
        'situation_matrimoniale',
    ];

    // Labels français pour les champs
    private const FIELD_LABELS = [
        'civilite' => 'Civilité',
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'nom_jeune_fille' => 'Nom de jeune fille',
        'date_naissance' => 'Date de naissance',
        'lieu_naissance' => 'Lieu de naissance',
        'nationalite' => 'Nationalité',
        'situation_matrimoniale' => 'Situation matrimoniale',
        'date_situation_matrimoniale' => 'Date situation matrimoniale',
        'situation_actuelle' => 'Situation actuelle',
        'profession' => 'Profession',
        'date_evenement_professionnel' => 'Date événement professionnel',
        'risques_professionnels' => 'Risques professionnels',
        'details_risques_professionnels' => 'Détails risques professionnels',
        'revenus_annuels' => 'Revenus annuels',
        'adresse' => 'Adresse',
        'code_postal' => 'Code postal',
        'ville' => 'Ville',
        'residence_fiscale' => 'Résidence fiscale',
        'telephone' => 'Téléphone',
        'email' => 'Email',
        'fumeur' => 'Fumeur',
        'activites_sportives' => 'Activités sportives',
        'details_activites_sportives' => 'Détails activités sportives',
        'niveau_activites_sportives' => 'Niveau activités sportives',
        'besoins' => 'Besoins',
        'charge_clientele' => 'Chargé de clientèle',
        'chef_entreprise' => 'Chef d\'entreprise',
        'statut' => 'Statut',
        'travailleur_independant' => 'Travailleur indépendant',
        'mandataire_social' => 'Mandataire social',
    ];

    /**
     * Crée un pending change pour un client avec les données extraites
     */
    public function createPendingChange(
        Client $client,
        array $extractedData,
        int $userId,
        ?int $audioRecordId = null,
        string $source = 'audio',
        array $relationalData = [] // Passifs, actifs, BAE, conjoint, enfants
    ): ClientPendingChange {
        // Calculer le diff pour les champs client
        $diff = $this->calculateDiff($client, $extractedData);

        // Ajouter les données relationnelles au diff si présentes
        if (! empty($relationalData)) {
            $this->addRelationalDataToDiff($client, $relationalData, $diff);
        }

        // Créer le pending change
        $pendingChange = ClientPendingChange::create([
            'client_id' => $client->id,
            'user_id' => $userId,
            'team_id' => $client->team_id,
            'audio_record_id' => $audioRecordId,
            'extracted_data' => $extractedData,
            'relational_data' => $relationalData,
            'changes_diff' => $diff,
            'status' => ClientPendingChange::STATUS_PENDING,
            'source' => $source,
        ]);

        Log::info('📋 [MERGE] Pending change créé', [
            'pending_change_id' => $pendingChange->id,
            'client_id' => $client->id,
            'changes_count' => $pendingChange->changes_count,
            'conflicts_count' => $pendingChange->conflicts_count,
            'relational_fields' => array_keys($relationalData),
        ]);

        return $pendingChange;
    }

    /**
     * Ajoute les données relationnelles au diff pour affichage
     */
    private function addRelationalDataToDiff(Client $client, array $relationalData, array &$diff): void
    {
        // Labels pour les champs relationnels
        $relationalLabels = [
            'client_passifs' => 'Crédits / Passifs',
            'client_actifs_financiers' => 'Actifs financiers',
            'client_biens_immobiliers' => 'Biens immobiliers',
            'client_autres_epargnes' => 'Autres épargnes',
            'client_revenus' => 'Revenus',
            'conjoint' => 'Conjoint',
            'enfants' => 'Enfants',
            'bae_prevoyance' => 'BAE Prévoyance',
            'bae_retraite' => 'BAE Retraite',
            'bae_epargne' => 'BAE Épargne',
        ];

        foreach ($relationalData as $field => $newValue) {
            if (empty($newValue)) {
                continue;
            }

            // Récupérer les données actuelles depuis les relations
            $currentValue = $this->getCurrentRelationalValue($client, $field);

            $diff[$field] = [
                'field' => $field,
                'label' => $relationalLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'current_value' => $currentValue,
                'new_value' => $newValue,
                'has_change' => true,
                'is_conflict' => ! empty($currentValue),
                'is_critical' => false,
                'is_relational' => true, // Marqueur spécial
                'requires_review' => true,
                'relational_fields' => $this->extractRelationalFields($newValue),
                'current_display' => $this->formatRelationalForDisplay($currentValue),
                'new_display' => $this->formatRelationalForDisplay($newValue),
            ];
        }
    }

    /**
     * Récupère la valeur actuelle d'un champ relationnel
     */
    private function getCurrentRelationalValue(Client $client, string $field): mixed
    {
        return match ($field) {
            'client_passifs' => $client->passifs?->map(fn ($p) => [
                'type' => $p->type,
                'montant' => $p->montant,
                'mensualite' => $p->mensualite,
            ])->toArray() ?? [],
            'client_actifs_financiers' => $client->actifsFinanciers?->map(fn ($a) => [
                'type' => $a->type,
                'montant' => $a->montant,
            ])->toArray() ?? [],
            'client_biens_immobiliers' => $client->biensImmobiliers?->map(fn ($b) => [
                'type' => $b->type,
                'valeur' => $b->valeur,
            ])->toArray() ?? [],
            'client_autres_epargnes' => $client->autresEpargnes?->map(fn ($e) => [
                'type' => $e->type,
                'montant' => $e->montant,
            ])->toArray() ?? [],
            'client_revenus' => $client->revenus?->map(fn ($r) => [
                'type' => $r->type,
                'montant' => $r->montant,
            ])->toArray() ?? [],
            'conjoint' => $client->conjoint ? [
                'nom' => $client->conjoint->nom,
                'prenom' => $client->conjoint->prenom,
                'profession' => $client->conjoint->profession,
            ] : null,
            'enfants' => $client->enfants?->map(fn ($e) => [
                'prenom' => $e->prenom,
                'date_naissance' => $e->date_naissance,
            ])->toArray() ?? [],
            'bae_prevoyance' => $client->baePrevoyance?->toArray(),
            'bae_retraite' => $client->baeRetraite?->toArray(),
            'bae_epargne' => $client->baeEpargne?->toArray(),
            default => null,
        };
    }

    /**
     * Formate les données relationnelles pour l'affichage
     */
    private function formatRelationalForDisplay(mixed $value): string
    {
        if (empty($value)) {
            return '(vide)';
        }

        if (is_array($value)) {
            $count = count($value);
            if ($count === 0) {
                return '(vide)';
            }

            // Si c'est un tableau associatif simple (conjoint, BAE)
            if (isset($value['nom']) || isset($value['prenom'])) {
                return ($value['prenom'] ?? '').' '.($value['nom'] ?? '');
            }

            // Si c'est un tableau d'objets
            $items = [];
            foreach ($value as $item) {
                if (isset($item['type'])) {
                    $montant = $item['montant'] ?? $item['valeur'] ?? '';
                    $items[] = $item['type'].($montant ? ': '.number_format((float) $montant, 0, ',', ' ').' €' : '');
                } elseif (isset($item['prenom'])) {
                    $items[] = $item['prenom'];
                }
            }

            return implode(', ', $items) ?: "$count élément(s)";
        }

        return (string) $value;
    }

    /**
     * Extrait les noms de champs d'une donnée relationnelle pour l'affichage.
     */
    private function extractRelationalFields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            $fields = [];
            foreach ($value as $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (array_keys($item) as $key) {
                    if (! in_array($key, $fields, true)) {
                        $fields[] = $key;
                    }
                }
            }

            return $fields;
        }

        return array_keys($value);
    }

    /**
     * Calcule le diff entre les données du client et les données extraites
     */
    public function calculateDiff(Client $client, array $extractedData): array
    {
        $diff = [];

        // Ne traiter que les champs scalaires du client (pas les relations)
        $clientFields = $client->getFillable();
        $excludedFields = ['team_id', 'user_id', 'transcription_path', 'consentement_audio'];

        foreach ($extractedData as $field => $newValue) {
            // Ignorer les champs de relations (seront traités séparément)
            if (in_array($field, ['revenus', 'passifs', 'actifs_financiers', 'biens_immobiliers', 'autres_epargnes', 'conjoint', 'enfants'])) {
                continue;
            }

            // Ignorer les champs non-fillable ou exclus
            if (! in_array($field, $clientFields) || in_array($field, $excludedFields)) {
                continue;
            }

            $currentValue = $client->$field;
            $hasChange = $this->valuesAreDifferent($currentValue, $newValue);

            // Détermine s'il y a un conflit (valeur existante non vide sera écrasée)
            $isConflict = $hasChange && ! $this->isEmpty($currentValue);

            $diff[$field] = [
                'field' => $field,
                'label' => self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'current_value' => $currentValue,
                'new_value' => $newValue,
                'has_change' => $hasChange,
                'is_conflict' => $isConflict,
                'is_critical' => in_array($field, self::CRITICAL_FIELDS),
                'requires_review' => $isConflict || in_array($field, self::CRITICAL_FIELDS),
                'current_display' => $this->formatForDisplay($currentValue),
                'new_display' => $this->formatForDisplay($newValue),
            ];
        }

        return $diff;
    }

    /**
     * Applique les changements selon les décisions de l'utilisateur
     */
    public function applyChanges(
        ClientPendingChange $pendingChange,
        array $decisions,
        int $reviewerId,
        array $overrides = []
    ): array {
        $client = $pendingChange->client;
        $applied = [];
        $rejected = [];

        // Récupérer les données relationnelles
        $relationalData = $pendingChange->relational_data ?? [];

        DB::beginTransaction();

        try {
            foreach ($decisions as $field => $decision) {
                $changeInfo = $pendingChange->changes_diff[$field] ?? null;

                if (! $changeInfo || ! $changeInfo['has_change']) {
                    continue;
                }

                if ($decision === 'accept') {
                    $overrideProvided = array_key_exists($field, $overrides);
                    $overrideValue = $overrideProvided ? $this->normalizeOverrideValue($overrides[$field], $changeInfo['new_value'] ?? null) : null;

                    // Vérifier si c'est un champ relationnel
                    if ($changeInfo['is_relational'] ?? false) {
                        // Appliquer via les services de synchronisation
                        $relationalPayload = $overrideProvided ? (is_array($overrideValue) ? $overrideValue : []) : ($relationalData[$field] ?? []);
                        $this->applyRelationalChange($client, $field, $relationalPayload);
                        $applied[$field] = [
                            'old' => $changeInfo['current_display'],
                            'new' => $overrideProvided ? $overrideValue : $changeInfo['new_display'],
                            'type' => 'relational',
                        ];
                    } else {
                        // Appliquer le changement standard
                        $oldValue = $client->$field;
                        $client->$field = $overrideProvided ? $overrideValue : $changeInfo['new_value'];
                        $applied[$field] = [
                            'old' => $oldValue,
                            'new' => $overrideProvided ? $overrideValue : $changeInfo['new_value'],
                        ];
                    }
                } elseif ($decision === 'reject') {
                    $rejected[$field] = [
                        'value' => $changeInfo['new_value'],
                        'reason' => 'Rejected by user',
                    ];
                }
            }

            // Sauvegarder le client
            $client->save();

            // Mettre à jour le pending change
            $finalStatus = count($rejected) > 0
                ? ClientPendingChange::STATUS_PARTIALLY_APPLIED
                : ClientPendingChange::STATUS_APPLIED;

            $pendingChange->update([
                'status' => $finalStatus,
                'user_decisions' => $decisions,
                'reviewed_at' => now(),
                'applied_at' => now(),
                'reviewed_by' => $reviewerId,
            ]);

            // Audit log
            $this->auditService->log(
                'pending_change_applied',
                'Modifications appliquées: '.count($applied).' acceptées, '.count($rejected).' rejetées',
                $pendingChange,
                'merge',
                'info',
                ['applied_fields' => array_keys($applied)],
                ['rejected_fields' => array_keys($rejected)]
            );

            DB::commit();

            Log::info('✅ [MERGE] Changements appliqués', [
                'pending_change_id' => $pendingChange->id,
                'applied' => array_keys($applied),
                'rejected' => array_keys($rejected),
            ]);

            return [
                'applied' => $applied,
                'rejected' => $rejected,
                'client' => $client->fresh()->load(['passifs', 'actifsFinanciers', 'autresEpargnes', 'biensImmobiliers']),
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("❌ [MERGE] Erreur lors de l'application", [
                'pending_change_id' => $pendingChange->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Applique un changement relationnel via le service approprié
     */
    private function applyRelationalChange(Client $client, string $field, array $data): void
    {
        Log::info("🔄 [MERGE] Application du champ relationnel: $field", [
            'client_id' => $client->id,
            'data_count' => count($data),
        ]);

        switch ($field) {
            case 'client_passifs':
                $service = new ClientPassifsSyncService;
                $service->syncPassifs($client, $data);
                break;

            case 'client_actifs_financiers':
                $service = new ClientActifsFinanciersSyncService;
                $service->syncActifsFinanciers($client, $data);
                break;

            case 'client_biens_immobiliers':
                $service = new ClientBiensImmobiliersSyncService;
                $service->syncBiensImmobiliers($client, $data);
                break;

            case 'client_autres_epargnes':
                $service = new ClientAutresEpargnesSyncService;
                $service->syncAutresEpargnes($client, $data);
                break;

            case 'client_revenus':
                $service = new ClientRevenusSyncService;
                $service->syncRevenus($client, $data);
                break;

            case 'conjoint':
                $service = new ConjointSyncService;
                $service->syncConjoint($client, $data);
                break;

            case 'enfants':
                $service = new EnfantSyncService;
                $service->syncEnfants($client, $data);
                break;

            case 'bae_prevoyance':
            case 'bae_retraite':
            case 'bae_epargne':
                $baeService = new BaeService;
                $baeService->syncBaeData($client, [$field => $data]);
                break;

            default:
                Log::warning("⚠️ [MERGE] Champ relationnel inconnu: $field");
        }
    }

    /**
     * Normalise une valeur modifiée par l'utilisateur selon le type attendu.
     */
    private function normalizeOverrideValue(mixed $override, mixed $baseline): mixed
    {
        if (is_string($override)) {
            $trimmed = trim($override);
            if ((is_array($baseline) || is_object($baseline)) && $trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            if (is_numeric($baseline)) {
                return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
            }

            if (is_bool($baseline)) {
                return filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        return $override;
    }

    /**
     * Rejette tous les changements
     */
    public function rejectAll(
        ClientPendingChange $pendingChange,
        int $reviewerId,
        ?string $reason = null
    ): void {
        $pendingChange->update([
            'status' => ClientPendingChange::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId,
            'notes' => $reason,
        ]);

        // Audit log
        $this->auditService->log(
            'pending_change_rejected',
            'Toutes les modifications rejetées'.($reason ? ": $reason" : ''),
            $pendingChange,
            'merge',
            'info',
            null,
            ['reason' => $reason]
        );

        Log::info('❌ [MERGE] Tous les changements rejetés', [
            'pending_change_id' => $pendingChange->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Applique automatiquement les changements sans conflit
     */
    public function autoApplySafeChanges(
        ClientPendingChange $pendingChange,
        int $reviewerId
    ): array {
        $decisions = [];

        foreach ($pendingChange->changes_diff as $field => $change) {
            if (! $change['has_change']) {
                continue;
            }

            // Accepter automatiquement si pas de conflit et pas critique
            if (! $change['is_conflict'] && ! $change['is_critical']) {
                $decisions[$field] = 'accept';
            } else {
                // Laisser en skip pour révision manuelle
                $decisions[$field] = 'skip';
            }
        }

        return $this->applyChanges($pendingChange, $decisions, $reviewerId, []);
    }

    /**
     * Vérifie si deux valeurs sont différentes
     */
    private function valuesAreDifferent($current, $new): bool
    {
        // Si la nouvelle valeur est vide, pas de changement à faire
        if ($this->isEmpty($new)) {
            return false;
        }

        // Si la valeur actuelle est vide et la nouvelle ne l'est pas
        if ($this->isEmpty($current) && ! $this->isEmpty($new)) {
            return true;
        }

        // Comparer les valeurs normalisées
        return $this->normalizeValue($current) !== $this->normalizeValue($new);
    }

    /**
     * Vérifie si une valeur est vide
     */
    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Normalise une valeur pour la comparaison
     */
    private function normalizeValue($value)
    {
        if (is_string($value)) {
            return strtolower(trim($value));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            sort($value);

            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Formate une valeur pour l'affichage
     */
    private function formatForDisplay($value): string
    {
        if ($this->isEmpty($value)) {
            return '(vide)';
        }
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        if (is_numeric($value) && $value > 1000) {
            return number_format($value, 0, ',', ' ');
        }

        return (string) $value;
    }
}
