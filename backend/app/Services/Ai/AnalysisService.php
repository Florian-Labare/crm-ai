<?php

namespace App\Services\Ai;

use App\Services\Ai\RouterService;
use App\Services\Ai\AiDataNormalizer;
use App\Services\Ai\ExtractionGuardrailsService;
use App\Services\Ai\Extractors\ClientExtractor;
use App\Services\Ai\Extractors\ConjointExtractor;
use App\Services\Ai\Extractors\PrevoyanceExtractor;
use App\Services\Ai\Extractors\RetraiteExtractor;
use App\Services\Ai\Extractors\EpargneExtractor;
use App\Services\Ai\Extractors\ClientRevenusExtractor;
use App\Services\Ai\Extractors\ClientPassifsExtractor;
use App\Services\Ai\Extractors\ClientActifsFinanciersExtractor;
use App\Services\Ai\Extractors\ClientBiensImmobiliersExtractor;
use App\Services\Ai\Extractors\ClientAutresEpargnesExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Service d'analyse IA refactorisé - Architecture modulaire.
 * 
 * Orchestrateur principal qui :
 * 1. Détecte les sections concernées (RouterService)
 * 2. Appelle les extracteurs spécialisés
 * 3. Fusionne les résultats
 * 4. Normalise les données (AiDataNormalizer)
 * 
 * 🔧 Améliorations :
 * - Température 0.1 (au lieu de 1) pour extraction déterministe
 * - besoins_action = "add" par défaut (au lieu de "replace")
 * - Prompts courts et ciblés (au lieu de 1038 lignes monolithiques)
 * - response_format JSON
 */
class AnalysisService
{
    public function __construct(
        private RouterService $router,
        private AiDataNormalizer $normalizer,
        private ExtractionGuardrailsService $guardrails,
        private ClientExtractor $clientExtractor,
        private ConjointExtractor $conjointExtractor,
        private PrevoyanceExtractor $prevoyanceExtractor,
        private RetraiteExtractor $retraiteExtractor,
        private EpargneExtractor $epargneExtractor,
        private ClientRevenusExtractor $clientRevenusExtractor,
        private ClientPassifsExtractor $clientPassifsExtractor,
        private ClientActifsFinanciersExtractor $clientActifsFinanciersExtractor,
        private ClientBiensImmobiliersExtractor $clientBiensImmobiliersExtractor,
        private ClientAutresEpargnesExtractor $clientAutresEpargnesExtractor
    ) {
    }

    /**
     * Extrait les données client depuis une transcription vocale.
     * 
     * Signature identique à l'ancien service pour compatibilité avec ProcessAudioRecording.
     *
     * @param string $transcription Transcription vocale
     * @return array Données extraites et normalisées
     */
    public function extractClientData(string $transcription): array
    {
        try {
            Log::info('🚀 [AnalysisService] Début extraction modulaire', [
                'transcription_length' => strlen($transcription),
            ]);

            // 1️⃣ ROUTING - Détecter les sections concernées
            $sections = $this->router->detectSections($transcription);
            Log::info('🧭 [AnalysisService] Sections détectées', ['sections' => $sections]);

            // 2️⃣ EXTRACTION - Appeler les extracteurs spécialisés
            $mergedData = [];

            foreach ($sections as $section) {
                $extractorData = $this->extractSection($section, $transcription);

                if (!empty($extractorData)) {
                    Log::info("📦 [AnalysisService] Données extraites pour section '$section'", [
                        'keys' => array_keys($extractorData),
                    ]);

                    // Fusion intelligente des données
                    $mergedData = $this->mergeData($mergedData, $extractorData);
                }
            }

            Log::info('🔀 [AnalysisService] Fusion des données terminée', [
                'merged_keys' => array_keys($mergedData),
            ]);

            // 🔒 GARDE-FOU : Nettoyer les données client si elles correspondent au conjoint
            $mergedData = $this->cleanClientDataIfConjointDetected($mergedData, $sections);

            // 🛡️ GUARDRAILS LAYER - Détecter les champs manqués et valider
            $mergedData = $this->guardrails->apply($mergedData, $transcription);
            $mergedData = $this->guardrails->checkCoherence($mergedData);

            Log::info('🛡️ [AnalysisService] Guardrails appliqués', [
                'keys_after_guardrails' => array_keys($mergedData),
            ]);

            // 3️⃣ NORMALISATION - Appliquer toutes les règles de normalisation
            $normalizedData = $this->normalizer->normalize($mergedData, $transcription);

            Log::info('✅ [AnalysisService] Extraction et normalisation terminées', [
                'final_keys' => array_keys($normalizedData),
            ]);

            return $normalizedData;

        } catch (\Throwable $e) {
            Log::error('❌ [AnalysisService] Erreur lors de l\'extraction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Extrait les données pour une section donnée.
     */
    private function extractSection(string $section, string $transcription): array
    {
        return match ($section) {
            'client' => $this->clientExtractor->extract($transcription),
            'conjoint' => $this->conjointExtractor->extract($transcription),
            'prevoyance' => $this->prevoyanceExtractor->extract($transcription),
            'retraite' => $this->retraiteExtractor->extract($transcription),
            'epargne' => $this->epargneExtractor->extract($transcription),
            'revenus' => $this->clientRevenusExtractor->extract($transcription),
            'passifs' => $this->clientPassifsExtractor->extract($transcription),
            'actifs_financiers' => $this->clientActifsFinanciersExtractor->extract($transcription),
            'biens_immobiliers' => $this->clientBiensImmobiliersExtractor->extract($transcription),
            'autres_epargnes' => $this->clientAutresEpargnesExtractor->extract($transcription),
            default => []
        };
    }

    /**
     * Garde-fou : Nettoie les données client si elles correspondent au conjoint.
     *
     * Si la section "conjoint" a été détectée et que des données conjoint ont été extraites,
     * on vérifie si les données du client correspondent aux données du conjoint (erreur du GPT).
     * Si c'est le cas, on supprime ces données du client pour éviter l'écrasement.
     */
    private function cleanClientDataIfConjointDetected(array $data, array $sections): array
    {
        // Si la section conjoint n'a pas été détectée, pas besoin de nettoyer
        if (!in_array('conjoint', $sections)) {
            return $data;
        }

        // Si pas de données conjoint extraites, pas besoin de nettoyer
        if (!isset($data['conjoint']) || empty($data['conjoint'])) {
            return $data;
        }

        $conjointData = $data['conjoint'];

        Log::info('🔒 [AnalysisService] Garde-fou : Vérification des données client vs conjoint', [
            'conjoint_keys' => array_keys($conjointData),
        ]);

        // Liste des champs à vérifier pour détecter si le client a les données du conjoint
        $fieldsToCheck = ['nom', 'prenom', 'date_naissance', 'profession'];

        $matchingFields = 0;
        $checkedFields = 0;

        foreach ($fieldsToCheck as $field) {
            // Si les deux ont le champ et qu'il est rempli
            if (isset($data[$field]) && !empty($data[$field]) &&
                isset($conjointData[$field]) && !empty($conjointData[$field])) {

                $checkedFields++;

                // Comparaison insensible à la casse
                if (mb_strtolower(trim($data[$field])) === mb_strtolower(trim($conjointData[$field]))) {
                    $matchingFields++;
                }
            }
        }

        // Si au moins 2 champs correspondent, c'est probablement une erreur du GPT
        if ($checkedFields >= 2 && $matchingFields >= 2) {
            Log::warning('🔒 [AnalysisService] GARDE-FOU ACTIVÉ : Les données client correspondent au conjoint ! Nettoyage...', [
                'matching_fields' => $matchingFields,
                'checked_fields' => $checkedFields,
            ]);

            // Supprimer les champs du client qui correspondent au conjoint
            foreach ($fieldsToCheck as $field) {
                if (isset($data[$field]) && isset($conjointData[$field])) {
                    if (mb_strtolower(trim($data[$field])) === mb_strtolower(trim($conjointData[$field]))) {
                        Log::info("🔒 Suppression du champ '$field' du client (correspond au conjoint)", [
                            'value_removed' => $data[$field],
                        ]);
                        unset($data[$field]);
                    }
                }
            }

            // Supprimer aussi les champs connexes qui pourraient être du conjoint
            $relatedFields = ['civilite', 'lieu_naissance', 'nationalite', 'situation_actuelle_statut',
                             'telephone', 'email', 'adresse'];

            foreach ($relatedFields as $field) {
                if (isset($data[$field]) && isset($conjointData[$field])) {
                    Log::info("🔒 Suppression du champ connexe '$field' du client", [
                        'value_removed' => $data[$field],
                    ]);
                    unset($data[$field]);
                }
            }
        }

        return $data;
    }

    /**
     * Fusionne deux tableaux de données de manière intelligente.
     *
     * Règles de fusion :
     * - Les tableaux sont fusionnés (besoins, enfants, etc.)
     * - Les objets sont fusionnés récursivement (bae_prevoyance, bae_retraite, etc.)
     * - Les valeurs scalaires : la nouvelle valeur écrase l'ancienne (si non vide)
     */
    private function mergeData(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (!isset($existing[$key])) {
                // Clé n'existe pas → ajouter
                $existing[$key] = $value;
            } elseif (is_array($existing[$key]) && is_array($value)) {
                // Les deux sont des tableaux

                // Cas spéciaux : besoins (concaténer et dédoublonner)
                if ($key === 'besoins') {
                    $existing[$key] = array_values(array_unique(array_merge($existing[$key], $value)));
                }
                // Cas spéciaux : enfants, actifs_*, passifs_*, charges_* (concaténer)
                elseif (in_array($key, ['enfants', 'actifs_financiers_details', 'actifs_immo_details', 'passifs_details', 'charges_details'])) {
                    $existing[$key] = array_merge($existing[$key], $value);
                }
                // Cas spéciaux : objets BAE (fusion récursive)
                elseif (in_array($key, ['bae_prevoyance', 'bae_retraite', 'bae_epargne', 'sante_souhait', 'conjoint'])) {
                    $existing[$key] = $this->mergeData($existing[$key], $value);
                }
                // Autres tableaux : remplacer
                else {
                    $existing[$key] = $value;
                }
            } else {
                // Valeur scalaire : la nouvelle valeur écrase l'ancienne (si non vide)
                if ($value !== null && $value !== '') {
                    $existing[$key] = $value;
                }
            }
        }

        return $existing;
    }

    /**
     * Sauvegarde les données du questionnaire de risque (conservé pour compatibilité).
     * 
     * NOTE : Cette méthode est gardée pour compatibilité avec ProcessAudioRecording.
     * Elle pourrait être déplacée dans un service dédié QuestionnaireRisqueService.
     *
     * @param int $clientId ID du client
     * @param array $data Données extraites contenant potentiellement questionnaire_risque
     */
    public function saveQuestionnaireRisque(int $clientId, array $data): void
    {
        try {
            if (!isset($data['questionnaire_risque']) || empty($data['questionnaire_risque'])) {
                Log::info('Aucune donnée de questionnaire de risque à sauvegarder', ['client_id' => $clientId]);
                return;
            }

            $questionnaireData = $data['questionnaire_risque'];

            if (empty($questionnaireData['financier']) && empty($questionnaireData['connaissances'])) {
                Log::info('Données de questionnaire vides, abandon', ['client_id' => $clientId]);
                return;
            }

            Log::info('💾 Sauvegarde du questionnaire de risque', [
                'client_id' => $clientId,
                'has_financier' => !empty($questionnaireData['financier']),
                'has_connaissances' => !empty($questionnaireData['connaissances']),
            ]);

            // Créer ou récupérer le questionnaire principal
            $questionnaire = \App\Models\QuestionnaireRisque::firstOrCreate(
                ['client_id' => $clientId],
                [
                    'score_global' => 0,
                    'profil_calcule' => 'Prudent',
                    'recommandation' => '',
                ]
            );

            // Sauvegarder les données financières si présentes
            if (!empty($questionnaireData['financier']) && is_array($questionnaireData['financier'])) {
                $financierData = array_filter($questionnaireData['financier'], function ($value) {
                    return !is_null($value) && $value !== '';
                });

                if (!empty($financierData)) {
                    $questionnaire->financier()->updateOrCreate(
                        ['questionnaire_risque_id' => $questionnaire->id],
                        $financierData
                    );
                    Log::info('✅ Données financières sauvegardées', ['data' => $financierData]);
                }
            }

            // Sauvegarder les connaissances si présentes
            if (!empty($questionnaireData['connaissances']) && is_array($questionnaireData['connaissances'])) {
                $connaissancesData = array_filter($questionnaireData['connaissances'], function ($value) {
                    return !is_null($value) && $value !== '';
                });

                if (!empty($connaissancesData)) {
                    $questionnaire->connaissances()->updateOrCreate(
                        ['questionnaire_risque_id' => $questionnaire->id],
                        $connaissancesData
                    );
                    Log::info('✅ Connaissances sauvegardées', ['data' => $connaissancesData]);
                }
            }

            // Recalculer le score
            $scoringService = app(\App\Services\ScoringService::class);
            $updatedQuestionnaire = $scoringService->scorerEtSauvegarder($questionnaire, [
                'financier' => $questionnaireData['financier'] ?? [],
                'connaissances' => $questionnaireData['connaissances'] ?? [],
                'quiz' => [],
            ]);

            Log::info('✅ Questionnaire de risque mis à jour', [
                'client_id' => $clientId,
                'score' => $updatedQuestionnaire->score_global,
                'profil' => $updatedQuestionnaire->profil_calcule,
            ]);

        } catch (\Throwable $e) {
            Log::error('❌ Erreur lors de la sauvegarde du questionnaire de risque', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
