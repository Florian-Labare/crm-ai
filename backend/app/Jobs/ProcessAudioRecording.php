<?php

namespace App\Jobs;

use App\Models\AudioRecord;
use App\Models\Client;
use App\Services\Ai\AnalysisService;
use App\Services\AssetCategorizationService; // Nouveau namespace
use App\Services\BaeService;
use App\Services\BesoinService;
use App\Services\ClientActifsFinanciersSyncService;
use App\Services\ClientAutresEpargnesSyncService;
use App\Services\ClientBiensImmobiliersSyncService;
use App\Services\ClientPassifsSyncService;
use App\Services\ClientRevenusSyncService;
use App\Services\ClientSyncService;
use App\Services\ConjointSyncService;
use App\Services\EnfantSyncService;
use App\Services\MeetingSummaryService;
use App\Services\MergeService;
use App\Services\TranscriptionService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAudioRecording implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives avant échec définitif
     */
    public $tries = 3;

    /**
     * Temps max d'exécution (5 minutes)
     */
    public $timeout = 600;

    /**
     * Délai avant nouvelle tentative (backoff exponentiel)
     */
    public $backoff = [30, 60, 120]; // 30s, 1min, 2min

    /**
     * L'enregistrement audio à traiter
     */
    protected AudioRecord $audioRecord;

    /**
     * ID du client existant (optionnel)
     */
    protected ?int $existingClientId;

    /**
     * Mode review : crée des PendingChanges au lieu d'appliquer directement
     * RECOMMANDÉ pour les clients existants avec données sensibles
     */
    protected bool $reviewMode;

    /**
     * Créer une nouvelle instance du job
     *
     * @param  bool  $reviewMode  Si true, crée des PendingChanges pour validation manuelle
     */
    public function __construct(AudioRecord $audioRecord, ?int $existingClientId = null, bool $reviewMode = true) {
        $this->audioRecord = $audioRecord;
        $this->existingClientId = $existingClientId;
        $this->reviewMode = $reviewMode;
        $this->onQueue('audio');
    }

    /**
     * Exécuter le job
     */
    public function handle(
        TranscriptionService $transcriptionService,
        AnalysisService $analysisService,
        ClientSyncService $clientSyncService,
        MergeService $mergeService,
        AssetCategorizationService $assetCategorizationService
    ): void {
        $summaryStored = false;
        $storeMeetingSummary = function (Client $client, string $transcription) use (&$summaryStored): void {
            if ($summaryStored) {
                return;
            }

            try {
                $summaryService = new MeetingSummaryService();
                $summaryPayload = $summaryService->generateSummary($transcription);
                if (! empty($summaryPayload['summary_text']) || ! empty($summaryPayload['summary_json'])) {
                    $summaryService->storeSummary(
                        $client->id,
                        $this->audioRecord->user_id,
                        $this->audioRecord->id,
                        $summaryPayload
                    );
                    $summaryStored = true;
                    Log::info("📝 Résumé de rendez-vous généré pour audio #{$this->audioRecord->id}");
                } else {
                    Log::warning("⚠️ Résumé non généré (payload vide) pour audio #{$this->audioRecord->id}");
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ Échec génération résumé audio #{$this->audioRecord->id}: {$e->getMessage()}");
            }
        };

        try {
            Log::info("🎵 Début du traitement audio #{$this->audioRecord->id}");

            // 1️⃣ Mettre le statut à "processing"
            $this->audioRecord->update(['status' => 'processing']);

            // 2️⃣ Transcription via Whisper API (ou réutilisation si déjà présente)
            if (! empty($this->audioRecord->transcription)) {
                // Transcription déjà présente (ex: depuis LongRecorder)
                Log::info("📝 Transcription déjà disponible pour audio #{$this->audioRecord->id}");
                $transcription = $this->audioRecord->transcription;
            } else {
                // Transcription via Whisper
                Log::info("🧠 Transcription audio #{$this->audioRecord->id}...");

                // Télécharger depuis S3 vers temp pour traitement
                $tempDir = storage_path('app/temp/audio');
                if (! is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $tempAudioPath = $tempDir.'/'.$this->audioRecord->id.'_'.basename($this->audioRecord->path);

                // Vérifier si le fichier existe sur S3
                if (! \Illuminate\Support\Facades\Storage::exists($this->audioRecord->path)) {
                    $message = "Fichier audio introuvable sur S3 : {$this->audioRecord->path}";
                    Log::error("❌ {$message}");
                    $this->audioRecord->update([
                        'status' => 'failed',
                        'transcription' => $message,
                    ]);
                    $this->fail(new Exception($message));

                    return;
                }

                // Télécharger depuis S3 vers temp
                $audioContent = \Illuminate\Support\Facades\Storage::get($this->audioRecord->path);
                file_put_contents($tempAudioPath, $audioContent);

                $audioPath = $tempAudioPath;

                if (! file_exists($audioPath) || ! is_file($audioPath)) {
                    $message = "Échec du téléchargement de l'audio vers temp : {$audioPath}";
                    Log::error("❌ {$message}");
                    $this->audioRecord->update([
                        'status' => 'failed',
                        'transcription' => $message,
                    ]);
                    $this->fail(new Exception($message));

                    return;
                }

                try {
                    $transcription = $transcriptionService->transcribe($audioPath);
                } finally {
                    // Nettoyer le fichier temp même en cas d'échec
                    @unlink($tempAudioPath);
                }

                if (empty($transcription)) {
                    throw new Exception('Transcription vide ou échec de Whisper API');
                }

                Log::info('✅ Transcription réussie : '.strlen($transcription).' caractères');
            }

            // 3️⃣ Analyse GPT pour extraction des données
            Log::info('💬 Analyse GPT-4 des données client...');
            $data = $analysisService->extractClientData($transcription);

            // 🛡️ GARDE-FOU : Validation et correction de la catégorisation des actifs
            // Assure que crypto → autres_actifs, immobilier → biens_immobiliers, etc.
            $data = $assetCategorizationService->validateAndCorrect($data);

            // 🔍 LOG DEBUG - Voir ce que GPT retourne pour les besoins
            if (isset($data['besoins']) || isset($data['besoins_action'])) {
                Log::info('🔍 [DEBUG BESOINS] Réponse GPT', [
                    'besoins' => $data['besoins'] ?? 'NON DÉFINI',
                    'besoins_action' => $data['besoins_action'] ?? 'NON DÉFINI',
                ]);
            }

            // 🏢 POST-TRAITEMENT : Corriger les champs entreprise mal placés par GPT
            $this->fixEnterpriseFields($transcription, $data);

            // 4️⃣ Synchronisation client
            // Sauvegarder les données du questionnaire de risque avant traitement
            $questionnaireData = $data['questionnaire_risque'] ?? null;

            if ($this->existingClientId) {
                // Mise à jour d'un client existant (vérifier qu'il appartient au bon utilisateur)
                // Utiliser withoutGlobalScope pour éviter le filtre par team qui peut échouer
                // si le job s'exécute dans un contexte différent (queue worker)
                $client = Client::withoutGlobalScope(\App\Scopes\TeamScope::class)
                    ->where('id', $this->existingClientId)
                    ->where('user_id', $this->audioRecord->user_id)
                    ->firstOrFail();

                // Gestion intelligente des besoins
                $removedBesoins = []; // Pour supprimer les BAE correspondants
                if (isset($data['besoins'])) {
                    $currentBesoins = is_array($client->besoins) ? $client->besoins : [];
                    $newBesoins = is_array($data['besoins']) ? $data['besoins'] : [];

                    Log::info("🔍 [DEBUG BESOINS] Besoins actuels du client #{$client->id}", [
                        'besoins_actuels' => $currentBesoins,
                        'nouveaux_besoins' => $newBesoins,
                    ]);

                    // 🛡️ GARDE-FOU : Déterminer l'action (FORCER "add" par défaut)
                    $action = $data['besoins_action'] ?? 'add';

                    // 🚨 GARDE-FOU CRITIQUE : Si l'action est "replace" sans raison valide, forcer "add"
                    if ($action === 'replace') {
                        Log::warning("⚠️ [GARDE-FOU] GPT a retourné 'replace' - FORCÉ à 'add' pour protéger les besoins existants");
                        $action = 'add';
                    }

                    switch ($action) {
                        case 'remove':
                            $removedBesoins = $newBesoins; // Sauvegarder les besoins à supprimer
                            $data['besoins'] = array_values(array_diff($currentBesoins, $newBesoins));
                            Log::info('🗑️ [BESOINS] Action: REMOVE', [
                                'besoins_retirés' => $removedBesoins,
                                'besoins_finaux' => $data['besoins'],
                            ]);
                            break;
                        case 'add':
                        default:
                            // Par défaut: TOUJOURS ajouter aux besoins existants
                            $data['besoins'] = array_values(array_unique(array_merge($currentBesoins, $newBesoins)));
                            Log::info('➕ [BESOINS] Action: ADD', [
                                'besoins_finaux' => $data['besoins'],
                            ]);
                            break;
                    }
                    // Normaliser les besoins vers des slugs propres avant stockage
                    if (isset($data['besoins']) && is_array($data['besoins'])) {
                        $data['besoins'] = app(BesoinService::class)->normalizeSlugs($data['besoins']);
                        Log::info('🔧 [BESOINS] Besoins normalisés en slugs', [
                            'besoins_slugs' => $data['besoins'],
                        ]);
                    }
                    unset($data['besoins_action']);
                }

                // Séparer les données : champs client vs relations
                $filteredData = [];
                $relationalData = [];
                $fillable = $client->getFillable();

                // Champs relationnels à stocker séparément
                $relationalFields = [
                    'bae_prevoyance', 'bae_retraite', 'bae_epargne',
                    'enfants', 'conjoint', 'client_revenus',
                    'client_passifs', 'client_actifs_financiers',
                    'client_biens_immobiliers', 'client_autres_epargnes',
                ];

                foreach ($data as $key => $value) {
                    // Exclusion questionnaire (traité autrement)
                    if (in_array($key, ['questionnaire_risque', 'sante_souhait', 'besoins_action'])) {
                        continue;
                    }

                    // Stocker les données relationnelles séparément
                    if (in_array($key, $relationalFields)) {
                        if (! empty($value) && (! is_array($value) || ! empty(array_filter($value, fn ($v) => ! empty($v))))) {
                            $relationalData[$key] = $value;
                            Log::info("📦 [MODE REVIEW] Données relationnelles détectées: $key", [
                                'count' => is_array($value) ? count($value) : 1,
                            ]);
                        }

                        continue;
                    }

                    // Vérifier si le champ est autorisé dans le modèle
                    if (! in_array($key, $fillable)) {
                        Log::warning("⚠️ Champ '$key' ignoré car non présent dans fillable du modèle Client");

                        continue;
                    }

                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (is_array($value) && empty($value)) {
                        continue;
                    }
                    $filteredData[$key] = $value;
                }

                // Normaliser la civilité pour correspondre à l'enum (Monsieur/Madame)
                if (isset($filteredData['civilite'])) {
                    $filteredData['civilite'] = $this->normalizeCivilite($filteredData['civilite']);
                }

                // 🎙️ Appliquer immédiatement le consentement audio (même en mode review)
                if (array_key_exists('consentement_audio', $filteredData)) {
                    $consentValue = $filteredData['consentement_audio'];
                    unset($filteredData['consentement_audio']);

                    if ($consentValue !== null) {
                        $client->consentement_audio = (bool) $consentValue;
                        if ($client->isDirty('consentement_audio')) {
                            $client->save();
                            Log::info("✅ Consentement audio mis à jour pour le client #{$client->id}", [
                                'consentement_audio' => $client->consentement_audio,
                            ]);
                        }
                    }
                }

                // 🔒 MODE REVIEW : Créer un PendingChange au lieu d'appliquer directement
                if ($this->reviewMode) {
                    Log::info("🔍 [MODE REVIEW] Création d'un PendingChange pour validation", [
                        'client_fields' => array_keys($filteredData),
                        'relational_fields' => array_keys($relationalData),
                    ]);

                    $pendingChange = $mergeService->createPendingChange(
                        $client,
                        $filteredData,
                        $this->audioRecord->user_id,
                        $this->audioRecord->id,
                        'audio',
                        $relationalData // Passer les données relationnelles
                    );

                    // Mettre à jour l'audio record avec le statut spécial
                    $this->audioRecord->update([
                        'status' => 'pending_review',
                        'transcription' => $transcription,
                        'client_id' => $client->id,
                        'processed_at' => now(),
                    ]);

                    Log::info("📋 [MODE REVIEW] PendingChange #{$pendingChange->id} créé", [
                        'changes_count' => $pendingChange->changes_count,
                        'conflicts_count' => $pendingChange->conflicts_count,
                    ]);

                    $storeMeetingSummary($client, $transcription);

                    // Sortir du job - les relations seront synchronisées après validation
                    return;
                }

                // 🚀 MODE DIRECT : Appliquer directement (ancien comportement)
                $client->fill($filteredData);
                if ($client->isDirty()) {
                    $client->save();
                    Log::info("✅ Client #{$client->id} mis à jour (mode direct)");
                }
            } else {
                // Création ou recherche automatique
                // Sauvegarder les enfants pour les traiter après
                $enfantsData = $data['enfants'] ?? null;

                unset($data['besoins_action']);
                unset($data['questionnaire_risque']); // Exclu car géré séparément
                unset($data['bae_prevoyance']); // Exclu car géré séparément
                unset($data['bae_retraite']); // Exclu car géré séparément
                unset($data['bae_epargne']); // Exclu car géré séparément
                unset($data['enfants']); // Exclu car géré séparément
                unset($data['conjoint']); // Exclu car géré séparément
                unset($data['client_revenus']); // Exclu car géré séparément
                unset($data['client_passifs']); // Exclu car géré séparément
                unset($data['client_actifs_financiers']); // Exclu car géré séparément
                unset($data['client_biens_immobiliers']); // Exclu car géré séparément
                unset($data['client_autres_epargnes']); // Exclu car géré séparément

                // 🔒 En mode review, ne pas mettre à jour les clients existants directement
                $result = $clientSyncService->findOrCreateFromAnalysis(
                    $data,
                    $this->audioRecord->user_id,
                    ! $this->reviewMode // updateExisting = false si reviewMode = true
                );

                $client = $result['client'];
                $wasExisting = $result['was_existing'];
                $cleanData = $result['clean_data'];

                // 🎙️ Appliquer immédiatement le consentement audio (même en mode review)
                if (array_key_exists('consentement_audio', $cleanData)) {
                    $consentValue = $cleanData['consentement_audio'];
                    unset($cleanData['consentement_audio']);

                    if ($consentValue !== null) {
                        $client->consentement_audio = (bool) $consentValue;
                        if ($client->isDirty('consentement_audio')) {
                            $client->save();
                            Log::info("✅ Consentement audio mis à jour pour le client #{$client->id}", [
                                'consentement_audio' => $client->consentement_audio,
                            ]);
                        }
                    }
                }

                // 🔒 MODE REVIEW : Si un client EXISTANT a été trouvé, créer un PendingChange
                if ($wasExisting && $this->reviewMode) {
                    Log::info('🔍 [MODE REVIEW] Client existant trouvé par recherche automatique - Création PendingChange');

                    $pendingChange = $mergeService->createPendingChange(
                        $client,
                        $cleanData,
                        $this->audioRecord->user_id,
                        $this->audioRecord->id,
                        'audio'
                    );

                    $this->audioRecord->update([
                        'status' => 'pending_review',
                        'transcription' => $transcription,
                        'client_id' => $client->id,
                        'processed_at' => now(),
                    ]);

                    Log::info("📋 [MODE REVIEW] PendingChange #{$pendingChange->id} créé (recherche auto)", [
                        'client_id' => $client->id,
                        'changes_count' => $pendingChange->changes_count,
                        'conflicts_count' => $pendingChange->conflicts_count,
                    ]);

                    $storeMeetingSummary($client, $transcription);

                    return; // Sortir - validation manuelle requise
                }

                Log::info("✅ Client #{$client->id} synchronisé (".($wasExisting ? 'trouvé' : 'créé').')');

                // Restaurer les enfants pour la synchronisation ultérieure
                if ($enfantsData) {
                    $data['enfants'] = $enfantsData;
                }
            }

            // 4️⃣ bis - Sauvegarde du questionnaire de risque si présent dans les données
            if ($questionnaireData) {
                Log::info('📊 Détection de données de questionnaire de risque, sauvegarde...');
                $analysisService->saveQuestionnaireRisque($client->id, ['questionnaire_risque' => $questionnaireData]);
            }

            // 4️⃣ ter - Synchronisation des données BAE (Prévoyance, Retraite, Épargne)
            $baeService = new BaeService();

            // Supprimer les BAE des besoins retirés
            if (! empty($removedBesoins)) {
                $baeService->removeBaeForBesoins($client, $removedBesoins);
            }

            // Synchroniser les BAE des besoins actuels
            $baeService->syncBaeData($client, $data);

            // 4️⃣ quater - Synchronisation des enfants
            if (isset($data['enfants']) && is_array($data['enfants']) && ! empty($data['enfants'])) {
                Log::info('👶 Détection de données enfants, synchronisation...');
                $enfantService = new EnfantSyncService();
                $enfantService->syncEnfants($client, $data['enfants']);
            }

            // 4️⃣ quinquies - Synchronisation du conjoint
            if (isset($data['conjoint']) && is_array($data['conjoint']) && ! empty($data['conjoint'])) {
                Log::info('💑 Détection de données conjoint, synchronisation...');
                $conjointService = new ConjointSyncService();
                $conjointService->syncConjoint($client, $data['conjoint']);
            }

            // 4️⃣ sextus - Synchronisation des revenus
            if (isset($data['client_revenus']) && is_array($data['client_revenus']) && ! empty($data['client_revenus'])) {
                Log::info('💰 Détection de données revenus, synchronisation...');
                $revenusService = new ClientRevenusSyncService();
                $revenusService->syncRevenus($client, $data['client_revenus']);
            }

            // 4️⃣ septimus - Synchronisation des passifs
            if (isset($data['client_passifs']) && is_array($data['client_passifs']) && ! empty($data['client_passifs'])) {
                Log::info('📉 Détection de données passifs, synchronisation...');
                $passifsService = new ClientPassifsSyncService();
                $passifsService->syncPassifs($client, $data['client_passifs']);
            }

            // 4️⃣ octavus - Synchronisation des actifs financiers
            if (isset($data['client_actifs_financiers']) && is_array($data['client_actifs_financiers']) && ! empty($data['client_actifs_financiers'])) {
                Log::info('📈 Détection de données actifs financiers, synchronisation...');
                $actifsService = new ClientActifsFinanciersSyncService();
                $actifsService->syncActifsFinanciers($client, $data['client_actifs_financiers']);
            }

            // 4️⃣ nonus - Synchronisation des biens immobiliers
            if (isset($data['client_biens_immobiliers']) && is_array($data['client_biens_immobiliers']) && ! empty($data['client_biens_immobiliers'])) {
                Log::info('🏠 Détection de données biens immobiliers, synchronisation...');
                $biensService = new ClientBiensImmobiliersSyncService();
                $biensService->syncBiensImmobiliers($client, $data['client_biens_immobiliers']);
            }

            // 4️⃣ decimus - Synchronisation des autres épargnes
            if (isset($data['client_autres_epargnes']) && is_array($data['client_autres_epargnes']) && ! empty($data['client_autres_epargnes'])) {
                Log::info('💎 Détection de données autres épargnes, synchronisation...');
                $epargnesService = new ClientAutresEpargnesSyncService();
                $epargnesService->syncAutresEpargnes($client, $data['client_autres_epargnes']);
            }

            // 5️⃣ Finalisation : marquer comme traité
            $this->audioRecord->update([
                'status' => 'done',
                'transcription' => $transcription,
                'client_id' => $client->id,
                'processed_at' => now(),
            ]);

            $storeMeetingSummary($client, $transcription);

            Log::info("🎉 Traitement audio #{$this->audioRecord->id} terminé avec succès !");

        } catch (Exception $e) {
            Log::error("❌ Échec du traitement audio #{$this->audioRecord->id}: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            // Marquer comme échec seulement si c'est la dernière tentative
            if ($this->attempts() >= $this->tries) {
                $this->audioRecord->update([
                    'status' => 'failed',
                    'transcription' => 'Erreur : '.$e->getMessage(),
                ]);
                Log::error("💀 Échec définitif après {$this->tries} tentatives");
            }

            // Re-throw pour que Laravel gère le retry
            throw $e;
        }
    }

    /**
     * Corrige les champs entreprise mal placés par GPT
     */
    private function fixEnterpriseFields(string $transcription, array &$data): void {
        Log::info('🏢 [FIX ENTREPRISE] Correction des champs entreprise');

        $text = mb_strtolower($transcription, 'UTF-8');

        // 0️⃣ DÉTECTION DE LA NÉGATION - PRIORITÉ ABSOLUE
        // Si le client dit "je ne suis PAS/PLUS chef d'entreprise", forcer à false
        $negationPatterns = [
            'chef_entreprise' => [
                "/\b(ne|n'|pas|plus|jamais).{0,30}chef\s+d['']?entreprise/u",
                "/\bchef\s+d['']?entreprise.{0,30}(ne|n'|pas|plus|non|jamais)/u",
            ],
            'travailleur_independant' => [
                "/\b(ne|n'|pas|plus|jamais).{0,30}(travailleur\s+ind[ée]pendant|ind[ée]pendant|freelance)/u",
                "/\b(travailleur\s+ind[ée]pendant|ind[ée]pendant|freelance).{0,30}(ne|n'|pas|plus|non|jamais)/u",
            ],
            'mandataire_social' => [
                "/\b(ne|n'|pas|plus|jamais).{0,30}mandataire\s+social/u",
                "/\bmandataire\s+social.{0,30}(ne|n'|pas|plus|non|jamais)/u",
            ],
        ];

        foreach ($negationPatterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    Log::info("🏢 [FIX] NÉGATION détectée pour '$field' → false");
                    $data[$field] = false;
                    break; // Passer au champ suivant
                }
            }
        }

        // 1️⃣ Corriger si GPT a mis ces infos dans les mauvais champs
        if (isset($data['profession']) && is_string($data['profession'])) {
            $profession = mb_strtolower($data['profession'], 'UTF-8');

            // Si profession contient "chef d'entreprise" → corriger
            if (str_contains($profession, "chef d'entreprise") || str_contains($profession, 'chef entreprise')) {
                Log::info("🏢 [FIX] 'chef d'entreprise' trouvé dans profession → chef_entreprise: true");
                $data['chef_entreprise'] = true;
                unset($data['profession']); // Supprimer le champ incorrect
            }

            // Si profession contient "travailleur indépendant" → corriger
            if (str_contains($profession, 'travailleur') && str_contains($profession, 'indépendant')) {
                Log::info("🏢 [FIX] 'travailleur indépendant' trouvé dans profession → travailleur_independant: true");
                $data['travailleur_independant'] = true;
                unset($data['profession']);
            }

            // Si profession contient "mandataire social" → corriger
            if (str_contains($profession, 'mandataire') && str_contains($profession, 'social')) {
                Log::info("🏢 [FIX] 'mandataire social' trouvé dans profession → mandataire_social: true");
                $data['mandataire_social'] = true;
                unset($data['profession']);
            }
        }

        // 2️⃣ Corriger situation_actuelle
        if (isset($data['situation_actuelle']) && is_string($data['situation_actuelle'])) {
            $situation = mb_strtolower($data['situation_actuelle'], 'UTF-8');

            if (str_contains($situation, 'travailleur') && str_contains($situation, 'indépendant')) {
                Log::info("🏢 [FIX] 'travailleur indépendant' trouvé dans situation_actuelle → travailleur_independant: true");
                $data['travailleur_independant'] = true;
                unset($data['situation_actuelle']);
            }

            if (str_contains($situation, 'chef') && str_contains($situation, 'entreprise')) {
                Log::info("🏢 [FIX] 'chef d'entreprise' trouvé dans situation_actuelle → chef_entreprise: true");
                $data['chef_entreprise'] = true;
                unset($data['situation_actuelle']);
            }
        }

        // 3️⃣ Analyser la transcription brute pour être sûr (SAUF si négation détectée)
        $patterns = [
            'chef_entreprise' => "/\bchef\s+d['\']?entreprise/u",
            'travailleur_independant' => "/\b(travailleur\s+ind[ée]pendant|ind[ée]pendant|freelance|auto[-\s]?entrepreneur)/u",
            'mandataire_social' => "/\bmandataire\s+social/u",
        ];

        foreach ($patterns as $field => $pattern) {
            // Ne pas remplacer si déjà false (négation détectée)
            if (isset($data[$field]) && $data[$field] === false) {
                continue;
            }

            if (! isset($data[$field]) || $data[$field] !== true) {
                if (preg_match($pattern, $text)) {
                    Log::info("🏢 [FIX] Pattern '$field' trouvé dans transcription → $field: true");
                    $data[$field] = true;
                }
            }
        }

        // 4️⃣ Détecter le statut juridique (SARL, SAS, etc.)
        if (empty($data['statut'])) {
            $statutPatterns = [
                'sarl' => 'SARL',
                'sas' => 'SAS',
                'sasu' => 'SASU',
                'eurl' => 'EURL',
                'sci' => 'SCI',
                'auto-entrepreneur' => 'Auto-entrepreneur',
                'auto entrepreneur' => 'Auto-entrepreneur',
                'micro-entreprise' => 'Micro-entreprise',
                'micro entreprise' => 'Micro-entreprise',
            ];

            foreach ($statutPatterns as $needle => $label) {
                if (str_contains($text, $needle)) {
                    Log::info("🏢 [FIX] Statut '$label' détecté dans transcription");
                    $data['statut'] = $label;
                    break;
                }
            }
        }

        Log::info('🏢 [FIX ENTREPRISE] Résultat final', [
            'chef_entreprise' => $data['chef_entreprise'] ?? 'non défini',
            'travailleur_independant' => $data['travailleur_independant'] ?? 'non défini',
            'mandataire_social' => $data['mandataire_social'] ?? 'non défini',
            'statut' => $data['statut'] ?? 'non défini',
        ]);
    }

    /**
     * Normalise la civilité pour correspondre à l'enum MySQL (Monsieur/Madame)
     */
    private function normalizeCivilite(?string $civilite): ?string {
        if (empty($civilite)) {
            return null;
        }

        $civilite = trim(mb_strtolower($civilite, 'UTF-8'));

        // Variantes pour Monsieur
        $monsieurVariants = ['m.', 'm', 'mr', 'mr.', 'monsieur', 'homme', 'masculin', 'h'];
        if (in_array($civilite, $monsieurVariants)) {
            return 'Monsieur';
        }

        // Variantes pour Madame
        $madameVariants = ['mme', 'mme.', 'madame', 'mademoiselle', 'mlle', 'mlle.', 'femme', 'féminin', 'f'];
        if (in_array($civilite, $madameVariants)) {
            return 'Madame';
        }

        // Si déjà au bon format
        if (mb_strtolower($civilite) === 'monsieur') {
            return 'Monsieur';
        }
        if (mb_strtolower($civilite) === 'madame') {
            return 'Madame';
        }

        Log::warning("⚠️ Civilité non reconnue: '$civilite', ignorée");

        return null;
    }

    /**
     * Gestion de l'échec définitif du job
     */
    public function failed(\Throwable $exception): void {
        Log::error("💀 Job ProcessAudioRecording #{$this->audioRecord->id} échoué définitivement");

        $this->audioRecord->update([
            'status' => 'failed',
            'transcription' => 'Échec définitif : '.$exception->getMessage(),
        ]);
    }
}
