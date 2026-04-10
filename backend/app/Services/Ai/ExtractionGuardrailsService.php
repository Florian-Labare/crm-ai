<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

/**
 * Guardrails Layer pour les extractions GPT
 *
 * Ce service encadre les extracteurs GPT pour :
 * 1. Détecter les champs critiques oubliés par GPT (patterns regex)
 * 2. Valider et normaliser les valeurs extraites
 * 3. Logger les écarts pour amélioration continue
 */
class ExtractionGuardrailsService
{
    /**
     * Patterns de détection pour les champs critiques
     * Structure : champ => [positive => [...], negative => [...]]
     */
    private array $criticalFieldPatterns = [
        'consentement_audio' => [
            'context' => [
                'enregistr',
                'accord pour',
                'd\'accord pour',
                'acceptez',
                'ça vous dérange',
            ],
            'positive' => [
                'oui',
                'ouais',
                'd\'accord',
                'pas de problème',
                'pas de souci',
                'ça me va',
                'ok',
                'bien sûr',
                'tout à fait',
                'je suis d\'accord',
                'aucun problème',
                'ça ne me dérange pas',
                'non ça ne me dérange pas', // Double négation = oui
            ],
            'negative' => [
                'non merci',
                'je refuse',
                'je préfère pas',
                'pas d\'accord',
                'je ne suis pas d\'accord',
                'ça me dérange',
            ],
        ],
        'fumeur' => [
            'context' => [
                'fumez',
                'fumeur',
                'fumer',
                'cigarette',
                'tabac',
            ],
            'positive' => [
                'oui je fume',
                'je suis fumeur',
                'je suis fumeuse',
                'je fume',
                'fumeur',
                'fumeuse',
            ],
            'negative' => [
                'non je ne fume pas',
                'je ne fume pas',
                'non fumeur',
                'non fumeuse',
                'pas fumeur',
                'pas fumeuse',
                'jamais fumé',
                'arrêté de fumer',
            ],
        ],
        'activites_sportives' => [
            'context' => [
                'sport',
                'activité physique',
                'exercice',
            ],
            'positive' => [
                'oui je fais du sport',
                'je fais du sport',
                'je pratique',
                'football',
                'tennis',
                'natation',
                'course',
                'musculation',
                'vélo',
                'running',
                'gym',
                'fitness',
                'yoga',
                'basket',
                'rugby',
                'golf',
                'randonnée',
            ],
            'negative' => [
                'non je ne fais pas de sport',
                'pas de sport',
                'sédentaire',
                'je ne fais pas de sport',
                'aucune activité sportive',
            ],
        ],
    ];

    /**
     * Patterns pour extraire des valeurs spécifiques
     */
    private array $valueExtractionPatterns = [
        'telephone' => '/(?:0[1-9])[\s.-]?(?:\d{2}[\s.-]?){4}/',
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        'code_postal' => '/\b([0-9]{5})\b/',
        'date' => '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/',
    ];

    /**
     * Applique les guardrails sur les données extraites
     *
     * @param  array  $extractedData  Données extraites par GPT
     * @param  string  $transcription  Transcription originale
     * @return array Données enrichies et validées
     */
    public function apply(array $extractedData, string $transcription): array
    {
        $originalData = $extractedData;
        $transcriptionLower = mb_strtolower($transcription);

        // 1. Détecter les champs critiques manquants
        $extractedData = $this->detectMissedCriticalFields($extractedData, $transcriptionLower);

        // 2. Valider et normaliser les valeurs
        $extractedData = $this->validateAndNormalize($extractedData);

        // 3. Logger les corrections effectuées
        $this->logCorrections($originalData, $extractedData, $transcription);

        return $extractedData;
    }

    /**
     * Détecte les champs critiques que GPT a potentiellement oubliés
     */
    private function detectMissedCriticalFields(array $data, string $transcription): array
    {
        foreach ($this->criticalFieldPatterns as $field => $patterns) {
            // Si le champ est déjà extrait, on ne le remplace pas
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                continue;
            }

            // Vérifier si le contexte du champ est présent dans la transcription
            $contextFound = false;
            foreach ($patterns['context'] as $contextPattern) {
                if (str_contains($transcription, $contextPattern)) {
                    $contextFound = true;
                    break;
                }
            }

            if (! $contextFound) {
                continue;
            }

            // Chercher les patterns positifs
            foreach ($patterns['positive'] as $positivePattern) {
                if (str_contains($transcription, mb_strtolower($positivePattern))) {
                    $data[$field] = true;
                    Log::info("🛡️ [GUARDRAILS] Champ '$field' détecté par pattern positif", [
                        'pattern' => $positivePattern,
                        'value' => true,
                    ]);
                    break 2; // Sortir des deux boucles
                }
            }

            // Chercher les patterns négatifs
            foreach ($patterns['negative'] as $negativePattern) {
                if (str_contains($transcription, mb_strtolower($negativePattern))) {
                    $data[$field] = false;
                    Log::info("🛡️ [GUARDRAILS] Champ '$field' détecté par pattern négatif", [
                        'pattern' => $negativePattern,
                        'value' => false,
                    ]);
                    break 2;
                }
            }
        }

        // Cas spécial pour consentement_audio : détecter la réponse après la question
        if (! array_key_exists('consentement_audio', $data) || $data['consentement_audio'] === null) {
            $consentValue = $this->detectConsentementFromContext($transcription);
            if ($consentValue !== null) {
                $data['consentement_audio'] = $consentValue;
                Log::info('🛡️ [GUARDRAILS] consentement_audio détecté par analyse contextuelle', [
                    'value' => $consentValue,
                ]);
            }
        }

        return $data;
    }

    /**
     * Analyse contextuelle avancée pour le consentement audio
     * Cherche la question puis la réponse qui suit
     */
    private function detectConsentementFromContext(string $transcription): ?bool
    {
        // Patterns de questions sur l'enregistrement
        $questionPatterns = [
            'est-ce que vous êtes d\'accord',
            'êtes-vous d\'accord',
            'acceptez-vous',
            'ça vous dérange si',
            'd\'accord pour',
            'ok pour',
            'enregistr',
        ];

        $hasQuestion = false;
        $questionPos = -1;

        foreach ($questionPatterns as $pattern) {
            $pos = mb_strpos($transcription, $pattern);
            if ($pos !== false) {
                $hasQuestion = true;
                $questionPos = $pos;
                break;
            }
        }

        if (! $hasQuestion) {
            return null;
        }

        // Chercher la réponse après la question (dans les 100 caractères suivants)
        $responseZone = mb_substr($transcription, $questionPos, 150);

        // Patterns de réponse positive
        $positiveResponses = [
            'oui',
            'ouais',
            'd\'accord',
            'pas de problème',
            'pas de souci',
            'bien sûr',
            'tout à fait',
            'ok',
            'ça me va',
            'aucun problème',
            'il n\'y a pas de problème',
            'y a pas de problème',
        ];

        // Patterns de réponse négative
        $negativeResponses = [
            'non merci',
            'je refuse',
            'je préfère pas',
            'non je',
            'pas d\'accord',
        ];

        // Cas spécial : "ça vous dérange" + "non" = consentement (double négation)
        if (str_contains($responseZone, 'dérange') && str_contains($responseZone, 'non')) {
            // "non ça ne me dérange pas" = consentement
            if (str_contains($responseZone, 'ne me dérange pas') ||
                str_contains($responseZone, 'ça ne me dérange pas')) {
                return true;
            }
        }

        // Chercher réponse positive
        foreach ($positiveResponses as $positive) {
            if (str_contains($responseZone, $positive)) {
                return true;
            }
        }

        // Chercher réponse négative
        foreach ($negativeResponses as $negative) {
            if (str_contains($responseZone, $negative)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Valide et normalise les valeurs extraites
     */
    private function validateAndNormalize(array $data): array
    {
        // Normaliser le téléphone (supprimer espaces, tirets)
        if (isset($data['telephone'])) {
            $data['telephone'] = preg_replace('/[\s.-]/', '', $data['telephone']);
        }

        // Normaliser l'email (lowercase)
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        // Valider le code postal (5 chiffres)
        if (isset($data['code_postal'])) {
            if (! preg_match('/^\d{5}$/', $data['code_postal'])) {
                Log::warning('🛡️ [GUARDRAILS] Code postal invalide ignoré', [
                    'value' => $data['code_postal'],
                ]);
                unset($data['code_postal']);
            }
        }

        // Normaliser la civilité
        if (isset($data['civilite'])) {
            $civilite = mb_strtolower(trim($data['civilite']));
            if (in_array($civilite, ['m', 'm.', 'mr', 'monsieur'])) {
                $data['civilite'] = 'Monsieur';
            } elseif (in_array($civilite, ['mme', 'mme.', 'madame'])) {
                $data['civilite'] = 'Madame';
            } elseif (in_array($civilite, ['mlle', 'mlle.', 'mademoiselle'])) {
                $data['civilite'] = 'Madame'; // Normalisé en Madame
            }
        }

        // S'assurer que les booléens sont bien des booléens
        $booleanFields = [
            'consentement_audio',
            'fumeur',
            'activites_sportives',
            'chef_entreprise',
            'travailleur_independant',
            'mandataire_social',
            'risques_professionnels',
        ];

        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (bool) $data[$field];
            }
        }

        return $data;
    }

    /**
     * Log les corrections effectuées par les guardrails
     */
    private function logCorrections(array $original, array $corrected, string $transcription): void
    {
        $corrections = [];

        foreach ($corrected as $field => $value) {
            if (! array_key_exists($field, $original)) {
                $corrections[$field] = [
                    'type' => 'added',
                    'value' => $value,
                ];
            } elseif ($original[$field] !== $value) {
                $corrections[$field] = [
                    'type' => 'modified',
                    'from' => $original[$field],
                    'to' => $value,
                ];
            }
        }

        if (! empty($corrections)) {
            Log::info('🛡️ [GUARDRAILS] Corrections appliquées', [
                'corrections' => $corrections,
                'transcription_excerpt' => mb_substr($transcription, 0, 200),
            ]);
        }
    }

    /**
     * Extrait des valeurs manquantes par patterns regex
     * Utilisé en dernier recours si GPT n'a pas extrait certaines valeurs évidentes
     */
    public function extractMissingValues(array $data, string $transcription): array
    {
        foreach ($this->valueExtractionPatterns as $field => $pattern) {
            if (! isset($data[$field]) && preg_match($pattern, $transcription, $matches)) {
                $data[$field] = $matches[0];
                Log::info('🛡️ [GUARDRAILS] Valeur extraite par regex', [
                    'field' => $field,
                    'value' => $matches[0],
                ]);
            }
        }

        return $data;
    }

    /**
     * Vérifie la cohérence des données extraites
     */
    public function checkCoherence(array $data): array
    {
        $warnings = [];

        // Si chef_entreprise mais pas de profession
        if (($data['chef_entreprise'] ?? false) && empty($data['profession'])) {
            $warnings[] = "Chef d'entreprise sans profession spécifiée";
        }

        // Si enfants mentionnés mais nombre incohérent
        if (isset($data['enfants']) && is_array($data['enfants'])) {
            $nombreEnfants = count($data['enfants']);
            if (isset($data['nombre_enfants']) && $data['nombre_enfants'] !== $nombreEnfants) {
                $warnings[] = "Incohérence entre nombre_enfants ({$data['nombre_enfants']}) et enfants listés ($nombreEnfants)";
                // Corriger en se basant sur le tableau
                $data['nombre_enfants'] = $nombreEnfants;
            }
        }

        if (! empty($warnings)) {
            Log::warning('🛡️ [GUARDRAILS] Alertes de cohérence', [
                'warnings' => $warnings,
            ]);
        }

        return $data;
    }
}
