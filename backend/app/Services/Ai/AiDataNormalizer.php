<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

/**
 * Service de normalisation des données extraites par l'IA.
 * 
 * Centralise toutes les règles de normalisation :
 * - Dates, téléphones, emails, codes postaux
 * - Booléens (détection négations/affirmations orales)
 * - Enfants (tableau d'objets)
 * - Champs entreprise (hydratation depuis transcription)
 * - Adresse → code_postal + ville
 * - besoins / besoins_action (logique corrigée)
 */
class AiDataNormalizer
{
    /**
     * Normalise les données extraites par l'IA.
     *
     * @param array $data Données brutes extraites
     * @param string $transcription Transcription originale pour corrections contextuelles
     * @return array Données normalisées
     */
    public function normalize(array $data, string $transcription): array
    {
        // 🗺️ Mapping des anciens noms vers les nouveaux
        $data = $this->mapLegacyFieldNames($data);

        // 🔧 Correction email incomplet
        if (isset($data['email']) && !empty($data['email']) && !str_contains($data['email'], '@')) {
            Log::warning('⚠️ Email incomplet détecté (pas de @)', ['email' => $data['email']]);
            $fixedEmail = $this->tryFixIncompleteEmail($transcription, $data['email']);
            if ($fixedEmail) {
                Log::info('✅ Email corrigé automatiquement', ['avant' => $data['email'], 'après' => $fixedEmail]);
                $data['email'] = $fixedEmail;
            }
        }

        // 📅 Normalisation des dates
        $dateFields = ['date_naissance', 'date_situation_matrimoniale', 'date_evenement_professionnel'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->normalizeDateToISO($data[$field]);
            }
        }

        // 📞 Normalisation du téléphone
        if (isset($data['telephone']) && !empty($data['telephone'])) {
            $data['telephone'] = $this->normalizePhone($data['telephone']);
        }

        // 📧 Normalisation de l'email
        if (isset($data['email']) && !empty($data['email'])) {
            $data['email'] = $this->normalizeEmail($data['email']);
        }

        // 📮 Normalisation du code postal
        if (isset($data['code_postal']) && !empty($data['code_postal'])) {
            $data['code_postal'] = $this->normalizePostalCode($data['code_postal']);
        }

        // 🔢 Normalisation des nombres
        if (isset($data['revenus_annuels'])) {
            $data['revenus_annuels'] = is_numeric($data['revenus_annuels'])
                ? (float) $data['revenus_annuels']
                : null;
        }
        if (isset($data['nombre_enfants'])) {
            $data['nombre_enfants'] = is_numeric($data['nombre_enfants'])
                ? (int) $data['nombre_enfants']
                : null;
        }

        // 👶 Normalisation du tableau enfants
        if (isset($data['enfants']) && is_array($data['enfants'])) {
            $data = $this->normalizeEnfants($data);
        }

        // ✅ Normalisation des booléens
        $booleanFields = [
            'fumeur',
            'activites_sportives',
            'risques_professionnels',
            'consentement_audio',
            'chef_entreprise',
            'travailleur_independant',
            'mandataire_social',
        ];
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $normalized = $this->normalizeBoolean($data[$field]);
                if ($normalized === null) {
                    unset($data[$field]);
                } else {
                    $data[$field] = $normalized;
                }
            }
        }

        // 🛑 Applique les négations/affirmations orales depuis la transcription
        $this->applyBooleanNegationsFromTranscript($transcription, $data);

        // 🏃 Détecte et extrait les activités sportives spécifiques
        $this->detectSportsFromTranscript($transcription, $data);

        // 🛡️ GARDE-FOU : Cohérence activités sportives
        // Si details_activites_sportives est rempli → activites_sportives DOIT être true
        if (!empty($data['details_activites_sportives']) || !empty($data['niveau_activites_sportives'])) {
            if (empty($data['activites_sportives']) || $data['activites_sportives'] === false) {
                Log::info('🏃 [SPORTS GARDE-FOU] Correction incohérence: details remplis mais boolean false → forcé à true');
                $data['activites_sportives'] = true;
            }
        }

        // 🔁 Hydrate les champs entreprise depuis la transcription
        $this->hydrateEnterpriseFieldsFromTranscript($transcription, $data);

        // 🏠 Déduit code postal / ville depuis l'adresse
        $this->hydrateAddressComponents($data);

        // 🎯 Normalisation des besoins (logique corrigée)
        $data = $this->normalizeBesoins($data);

        return $data;
    }

    /**
     * Mapping des anciens noms de champs vers les nouveaux.
     */
    private function mapLegacyFieldNames(array $data): array
    {
        $fieldMapping = [
            'datedenaissance' => 'date_naissance',
            'lieudenaissance' => 'lieu_naissance',
            'situationmatrimoniale' => 'situation_matrimoniale',
            'revenusannuels' => 'revenus_annuels',
            'nombreenfants' => 'nombre_enfants',
        ];

        foreach ($fieldMapping as $oldName => $newName) {
            if (isset($data[$oldName]) && !isset($data[$newName])) {
                $data[$newName] = $data[$oldName];
                unset($data[$oldName]);
            }
        }

        // Mapping spécial pour "enfants"
        if (isset($data['enfants'])) {
            if (is_numeric($data['enfants'])) {
                // Ancien système: enfants est un nombre → convertir en nombre_enfants
                if (!isset($data['nombre_enfants'])) {
                    $data['nombre_enfants'] = (int) $data['enfants'];
                }
                unset($data['enfants']);
            }
        }

        // Mapping "marie" → "situation_matrimoniale"
        if (isset($data['marie'])) {
            if ($data['marie'] === true) {
                $data['situation_matrimoniale'] = 'Marié(e)';
            } elseif ($data['marie'] === false) {
                $data['situation_matrimoniale'] = 'Célibataire';
            }
            unset($data['marie']);
        }

        // Mapping "celibataire" → "situation_matrimoniale"
        if (isset($data['celibataire']) && $data['celibataire'] === true) {
            $data['situation_matrimoniale'] = 'Célibataire';
            unset($data['celibataire']);
        }

        // Mapping "divorce" → "situation_matrimoniale"
        if (isset($data['divorce']) && $data['divorce'] === true) {
            $data['situation_matrimoniale'] = 'Divorcé(e)';
            unset($data['divorce']);
        }

        // Mapping "veuf" → "situation_matrimoniale"
        if (isset($data['veuf']) && $data['veuf'] === true) {
            $data['situation_matrimoniale'] = 'Veuf(ve)';
            unset($data['veuf']);
        }

        // Mapping "proprietaire" → "situation_actuelle"
        if (isset($data['proprietaire'])) {
            if ($data['proprietaire'] === true) {
                $data['situation_actuelle'] = 'Propriétaire';
            }
            unset($data['proprietaire']);
        }

        // Mapping "locataire" → "situation_actuelle"
        if (isset($data['locataire'])) {
            if ($data['locataire'] === true) {
                $data['situation_actuelle'] = 'Locataire';
            }
            unset($data['locataire']);
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

            // Si déjà au format ISO
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }

            // Format français DD/MM/YYYY
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            // Format DD-MM-YYYY
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            // Tentative avec Carbon (formats et mois FR)
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

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        $monthMap = [
            'janvier' => 'january',
            'fevrier' => 'february',
            'mars' => 'march',
            'avril' => 'april',
            'mai' => 'may',
            'juin' => 'june',
            'juillet' => 'july',
            'aout' => 'august',
            'septembre' => 'september',
            'octobre' => 'october',
            'novembre' => 'november',
            'decembre' => 'december',
        ];

        foreach ($monthMap as $fr => $en) {
            $normalized = preg_replace('/\b' . $fr . '\b/', $en, $normalized);
        }

        return $normalized;
    }

    /**
     * Normalise un numéro de téléphone.
     */
    private function normalizePhone(string $phone): ?string
    {
        try {
            // Supprimer espaces, points, tirets, parenthèses
            $normalized = preg_replace('/[\s.\-()]/', '', $phone);

            // Garder uniquement chiffres et +
            $normalized = preg_replace('/[^0-9+]/', '', $normalized);

            // Validation : doit commencer par 0 ou +33
            if (preg_match('/^(\+33|0)[0-9]{9,}$/', $normalized)) {
                return $normalized;
            }

            Log::warning('Format de téléphone invalide', ['phone' => $phone]);
            return null;

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser le téléphone', ['phone' => $phone, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise une adresse email.
     */
    private function normalizeEmail(string $email): ?string
    {
        try {
            $normalized = trim($email);
            $normalized = strtolower($normalized);

            if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                return $normalized;
            }

            Log::warning('Format email invalide', ['email' => $email]);
            return null;

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser l\'email', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise un code postal français.
     */
    private function normalizePostalCode(string $postalCode): ?string
    {
        try {
            $normalized = trim($postalCode);
            $normalized = preg_replace('/[^0-9]/', '', $normalized);

            if (preg_match('/^\d{5}$/', $normalized)) {
                return $normalized;
            }

            Log::warning('Format code postal invalide', ['code_postal' => $postalCode]);
            return null;

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser le code postal', ['code_postal' => $postalCode, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise les entrées booléennes (true/false, oui/non).
     */
    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            $normalized = trim(mb_strtolower($value, 'UTF-8'));
            $normalized = trim($normalized, " \t\n\r\0\x0B.,;:!?");

            $truthy = ['true', '1', 'oui', 'yes', 'vrai', 'ok'];
            $falsy = ['false', '0', 'non', 'no', 'faux'];

            if (in_array($normalized, $truthy, true)) {
                return true;
            }

            if (in_array($normalized, $falsy, true)) {
                return false;
            }

            if (preg_match('/\boui\b/u', $normalized)) {
                return true;
            }

            if (preg_match('/\bnon\b/u', $normalized)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Normalise le tableau enfants.
     */
    private function normalizeEnfants(array $data): array
    {
        Log::info('👶 [ENFANTS] Normalisation du tableau enfants', ['count' => count($data['enfants'])]);
        $normalizedEnfants = [];

        foreach ($data['enfants'] as $index => $enfant) {
            if (!is_array($enfant)) {
                Log::warning("👶 [ENFANTS] Enfant #{$index} ignoré (pas un tableau)");
                continue;
            }

            Log::info("👶 [ENFANTS] Normalisation enfant #{$index}", ['data' => $enfant]);
            $normalizedEnfant = [];

            if (isset($enfant['nom']) && !empty($enfant['nom'])) {
                $normalizedEnfant['nom'] = trim($enfant['nom']);
            }

            if (isset($enfant['prenom']) && !empty($enfant['prenom'])) {
                $normalizedEnfant['prenom'] = trim($enfant['prenom']);
            }

            if (isset($enfant['date_naissance']) && !empty($enfant['date_naissance'])) {
                $normalizedDate = $this->normalizeDateToISO($enfant['date_naissance']);
                if ($normalizedDate) {
                    $normalizedEnfant['date_naissance'] = $normalizedDate;
                }
            }

            if (isset($enfant['fiscalement_a_charge'])) {
                $normalized = $this->normalizeBoolean($enfant['fiscalement_a_charge']);
                if ($normalized !== null) {
                    $normalizedEnfant['fiscalement_a_charge'] = $normalized;
                }
            }

            if (isset($enfant['garde_alternee'])) {
                $normalized = $this->normalizeBoolean($enfant['garde_alternee']);
                if ($normalized !== null) {
                    $normalizedEnfant['garde_alternee'] = $normalized;
                }
            }

            $normalizedEnfants[] = $normalizedEnfant;
            Log::info("👶 [ENFANTS] Enfant #{$index} normalisé", ['normalized' => $normalizedEnfant]);
        }

        if (!empty($normalizedEnfants)) {
            $data['enfants'] = $normalizedEnfants;
            // Déduire nombre_enfants si pas déjà défini
            if (!isset($data['nombre_enfants'])) {
                $data['nombre_enfants'] = count($normalizedEnfants);
            }
            Log::info('✅ [ENFANTS] Normalisation terminée', ['count' => count($normalizedEnfants)]);
        } else {
            Log::warning('⚠️ [ENFANTS] Aucun enfant normalisé - suppression du champ');
            unset($data['enfants']);
        }

        return $data;
    }

    /**
     * Applique les négations/affirmations orales détectées dans la transcription.
     */
    private function applyBooleanNegationsFromTranscript(string $transcription, array &$data): void
    {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", $transcription), 'UTF-8');

        $fieldPatterns = [
            'fumeur' => [
                'negative' => [
                    "/je\s+ne\s+suis\s+pas\s+fumeur/u",
                    "/je\s+ne\s+suis\s+plus\s+fumeur/u",
                    "/je\s+ne\s+fume\s+pas/u",
                    "/je\s+ne\s+fume\s+plus/u",
                    "/je\s+ne\s+fume\s+jamais/u",
                    "/je\s+suis\s+non[-\s]?fumeur/u",
                ],
                'positive' => [
                    "/je\s+suis\s+fumeur/u",
                    "/je\s+fume\b/u",
                ],
            ],
            'activites_sportives' => [
                'negative' => [
                    "/je\s+ne\s+fais\s+pas\s+de?\s+sport/u",
                    "/je\s+ne\s+fais\s+plus\s+de?\s+sport/u",
                    "/je\s+ne\s+pratique\s+pas\s+de?\s+sport/u",
                    "/aucune?\s+activit[ée]\s+sportive/u",
                    "/pas\s+d['e]?\s*activit[ée]\s+sportive/u",
                    "/pas\s+de?\s+sport/u",
                ],
                'positive' => [
                    "/je\s+fais\s+du\s+sport/u",
                    "/je\s+pratique\s+(?:un|le|la|du|de\s+la)\s+\w+/u",
                    "/je\s+fais\s+(?:du|de\s+la|de\s+l['e]?)\s+\w+/u",
                    "/activit[ée]s?\s+sportives?/u",
                    // Sports spécifiques
                    "/\b(?:football|foot|tennis|natation|course|running|jogging|musculation|fitness|gym|yoga|pilates|boxe|judo|karate|vélo|cyclisme|randonnée|ski|snowboard|surf|plongée|escalade|basketball|basket|volleyball|volley|handball|rugby|golf|équitation|danse|badminton|squash|paddle|crossfit|triathlon|marathon|athlétisme)\b/ui",
                ],
            ],
            'risques_professionnels' => [
                'negative' => [
                    "/je\s+n['e]\s+ai\s+pas\s+de?\s+risques?\s+professionnels/u",
                    "/aucun\s+risque\s+professionnel/u",
                    "/pas\s+de?\s+risques?\s+professionnels/u",
                ],
                'positive' => [
                    "/j['e]\s+ai\s+des?\s+risques?\s+professionnels/u",
                    "/je\s+suis\s+exposé\s+à\s+des?\s+risques?\s+professionnels/u",
                ],
            ],
            'chef_entreprise' => [
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:un\s+|une\s+)?chef\s+d[''\s]?entreprise/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:un\s+|une\s+)?chef\s+d[''\s]?entreprise/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:chef\s+d[''\s]?entreprise)/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:chef\s+d[''\s]?entreprise)/u",
                    "/pas\s+chef\s+d[''\s]?entreprise/u",
                    "/plus\s+chef\s+d[''\s]?entreprise/u",
                    "/ni\s+chef\s+d[''\s]?entreprise/u",
                ],
                'positive' => [
                    "/\bchef\s+d[''\s]?entreprise/u",
                    "/je\s+dirige\s+(?:ma|mon|une)\s+(?:entreprise|société)/u",
                    "/je\s+gère\s+(?:ma|mon|une)\s+(?:propre\s+)?entreprise/u",
                ],
            ],
            'travailleur_independant' => [
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/pas\s+ind[ée]pendant/u",
                    "/plus\s+travailleur\s+ind[ée]pendant/u",
                    "/ni\s+travailleur\s+ind[ée]pendant/u",
                ],
                'positive' => [
                    "/\btravailleur\s+ind[ée]pendant/u",
                    "/\bind[ée]pendant\b/u",
                    "/je\s+travaille\s+(?:à|a)\s+mon\s+compte/u",
                    "/\bfreelance\b/u",
                    "/\bauto[-\s]?entrepreneur/u",
                    "/\bmicro[-\s]?entrepreneur/u",
                    "/profession\s+(?:libérale|liberale)/u",
                ],
            ],
            'mandataire_social' => [
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+mandataire\s+social/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+mandataire\s+social/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+mandataire\s+social/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+mandataire\s+social/u",
                    "/pas\s+mandataire\s+social/u",
                    "/plus\s+mandataire\s+social/u",
                    "/ni\s+mandataire\s+social/u",
                ],
                'positive' => [
                    "/\bmandataire\s+social/u",
                ],
            ],
        ];

        foreach ($fieldPatterns as $field => $patterns) {
            foreach ($patterns['negative'] as $regex) {
                if (preg_match($regex, $text)) {
                    $data[$field] = false;
                    continue 2;
                }
            }

            if (!empty($patterns['positive'])) {
                foreach ($patterns['positive'] as $regex) {
                    if (preg_match($regex, $text)) {
                        if (!array_key_exists($field, $data) || $data[$field] === null) {
                            $data[$field] = true;
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * Détecte et extrait les activités sportives depuis la transcription.
     * Remplit activites_sportives (boolean) et details_activites_sportives (string).
     */
    private function detectSportsFromTranscript(string $transcription, array &$data): void
    {
        $text = mb_strtolower(str_replace(["\u{2019}", "\u{2018}"], "'", $transcription), 'UTF-8');

        // Liste des sports à détecter
        $sportsMap = [
            'football' => 'Football',
            'foot' => 'Football',
            'tennis' => 'Tennis',
            'natation' => 'Natation',
            'course' => 'Course à pied',
            'running' => 'Running',
            'jogging' => 'Jogging',
            'musculation' => 'Musculation',
            'fitness' => 'Fitness',
            'gym' => 'Gym',
            'yoga' => 'Yoga',
            'pilates' => 'Pilates',
            'boxe' => 'Boxe',
            'judo' => 'Judo',
            'karaté' => 'Karaté',
            'karate' => 'Karaté',
            'vélo' => 'Vélo',
            'velo' => 'Vélo',
            'cyclisme' => 'Cyclisme',
            'randonnée' => 'Randonnée',
            'randonnee' => 'Randonnée',
            'ski' => 'Ski',
            'snowboard' => 'Snowboard',
            'surf' => 'Surf',
            'plongée' => 'Plongée',
            'plongee' => 'Plongée',
            'escalade' => 'Escalade',
            'basketball' => 'Basketball',
            'basket' => 'Basketball',
            'volleyball' => 'Volleyball',
            'volley' => 'Volleyball',
            'handball' => 'Handball',
            'rugby' => 'Rugby',
            'golf' => 'Golf',
            'équitation' => 'Équitation',
            'equitation' => 'Équitation',
            'danse' => 'Danse',
            'badminton' => 'Badminton',
            'squash' => 'Squash',
            'paddle' => 'Paddle',
            'crossfit' => 'CrossFit',
            'triathlon' => 'Triathlon',
            'marathon' => 'Marathon',
            'athlétisme' => 'Athlétisme',
            'athletisme' => 'Athlétisme',
            'moto' => 'Moto',
            'motocross' => 'Motocross',
            'parachutisme' => 'Parachutisme',
            'parapente' => 'Parapente',
            'alpinisme' => 'Alpinisme',
            'voile' => 'Voile',
            'aviron' => 'Aviron',
            'canoë' => 'Canoë',
            'canoe' => 'Canoë',
            'kayak' => 'Kayak',
            'shooting' => 'Tir sportif',
            'tir' => 'Tir sportif',
            'tir sportif' => 'Tir sportif',
            'chasse' => 'Chasse',
            'pêche' => 'Pêche',
            'peche' => 'Pêche',
        ];

        $detectedSports = [];

        // Patterns pour détecter les sports avec contexte
        $patterns = [
            "/je\s+(?:fais|pratique)\s+(?:du|de\s+la|de\s+l['e]?)\s+(\w+)/ui",
            "/je\s+joue\s+(?:au|à\s+la|à\s+l['e]?)\s+(\w+)/ui",
            "/(?:mon|ma)\s+sport\s+(?:c'?est|principal)\s+(?:le|la|l['e]?)\s+(\w+)/ui",
        ];

        // Chercher via patterns contextuels
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $sportMention) {
                    $sportKey = mb_strtolower(trim($sportMention), 'UTF-8');
                    if (isset($sportsMap[$sportKey])) {
                        $detectedSports[] = $sportsMap[$sportKey];
                    }
                }
            }
        }

        // Chercher les sports mentionnés directement
        foreach ($sportsMap as $keyword => $sportName) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/ui';
            if (preg_match($pattern, $text) && !in_array($sportName, $detectedSports)) {
                // Vérifier que ce n'est pas dans un contexte négatif
                $negativePattern = "/(?:pas|plus|jamais|aucun)\s+(?:de\s+)?" . preg_quote($keyword, '/') . "/ui";
                if (!preg_match($negativePattern, $text)) {
                    $detectedSports[] = $sportName;
                }
            }
        }

        // Si des sports ont été détectés
        if (!empty($detectedSports)) {
            $uniqueSports = array_unique($detectedSports);

            // Mettre activites_sportives à true
            $data['activites_sportives'] = true;

            // Remplir details_activites_sportives si pas déjà défini
            if (empty($data['details_activites_sportives'])) {
                $data['details_activites_sportives'] = implode(', ', $uniqueSports);
            }

            Log::info('🏃 [SPORTS] Activités sportives détectées', [
                'sports' => $uniqueSports,
                'activites_sportives' => true,
                'details' => $data['details_activites_sportives'],
            ]);
        }
    }

    /**
     * Hydrate les champs entreprise depuis la transcription.
     */
    private function hydrateEnterpriseFieldsFromTranscript(string $transcription, array &$data): void
    {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", $transcription), 'UTF-8');

        $patterns = [
            'chef_entreprise' => [
                'positive' => [
                    "/\bchef\s+d[''\s]?entreprise/u",
                    "/je\s+dirige\s+(?:ma|mon|une)\s+(?:entreprise|société)/u",
                    "/je\s+gère\s+(?:ma|mon|une)\s+(?:propre\s+)?entreprise/u",
                    "/(?:ma|mon)\s+(?:propre\s+)?entreprise/u",
                ],
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:un\s+|une\s+)?chef\s+d[''\s]?entreprise/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:un\s+|une\s+)?chef\s+d[''\s]?entreprise/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:chef\s+d[''\s]?entreprise)/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:chef\s+d[''\s]?entreprise)/u",
                    "/pas\s+chef\s+d[''\s]?entreprise/u",
                    "/plus\s+chef\s+d[''\s]?entreprise/u",
                    "/ni\s+chef\s+d[''\s]?entreprise/u",
                ],
            ],
            'travailleur_independant' => [
                'positive' => [
                    "/\btravailleur\s+ind[ée]pendant/u",
                    "/\bind[ée]pendant\b/u",
                    "/je\s+travaille\s+(?:à|a)\s+mon\s+compte/u",
                    "/\bfreelance\b/u",
                    "/\bauto[-\s]?entrepreneur/u",
                    "/\bmicro[-\s]?entrepreneur/u",
                    "/profession\s+(?:libérale|liberale)/u",
                ],
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:travailleur\s+)?ind[ée]pendant/u",
                    "/pas\s+ind[ée]pendant/u",
                    "/plus\s+travailleur\s+ind[ée]pendant/u",
                    "/ni\s+travailleur\s+ind[ée]pendant/u",
                ],
            ],
            'mandataire_social' => [
                'positive' => [
                    "/\bmandataire\s+social/u",
                ],
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+mandataire\s+social/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+mandataire\s+social/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+mandataire\s+social/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+mandataire\s+social/u",
                    "/pas\s+mandataire\s+social/u",
                    "/plus\s+mandataire\s+social/u",
                    "/ni\s+mandataire\s+social/u",
                ],
            ],
        ];

        foreach ($patterns as $field => $regexes) {
            // Priorité aux négations
            foreach ($regexes['negative'] as $negativeRegex) {
                if (preg_match($negativeRegex, $text)) {
                    Log::info("🔍 [ENTREPRISE] Pattern négatif trouvé pour $field", ['pattern' => $negativeRegex]);
                    $data[$field] = false;
                    continue 2;
                }
            }

            // Chercher patterns positifs
            $matched = false;
            foreach ($regexes['positive'] as $positiveRegex) {
                if (preg_match($positiveRegex, $text)) {
                    Log::info("✅ [ENTREPRISE] Pattern positif trouvé pour $field", ['pattern' => $positiveRegex]);
                    $data[$field] = true;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                Log::info("❌ [ENTREPRISE] Aucun pattern trouvé pour $field");
            }
        }

        Log::info('🔍 [ENTREPRISE] Résultat après analyse', [
            'chef_entreprise' => $data['chef_entreprise'] ?? 'non défini',
            'travailleur_independant' => $data['travailleur_independant'] ?? 'non défini',
            'mandataire_social' => $data['mandataire_social'] ?? 'non défini',
            'statut' => $data['statut'] ?? 'non défini',
        ]);

        // Détection du statut juridique
        if (empty($data['statut'])) {
            $statutKeywords = [
                'sarl' => 'SARL',
                'sas' => 'SAS',
                'sasu' => 'SASU',
                'eurl' => 'EURL',
                'sci' => 'SCI',
                'ei' => 'EI',
                'eirl' => 'EIRL',
                'auto-entrepreneur' => 'Auto-entrepreneur',
                'auto entrepreneur' => 'Auto-entrepreneur',
                'micro-entreprise' => 'Micro-entreprise',
                'micro entreprise' => 'Micro-entreprise',
                'profession libérale' => 'Profession libérale',
            ];

            foreach ($statutKeywords as $needle => $label) {
                $pattern = '/\b' . preg_quote($needle, '/') . '\b/u';
                if (preg_match($pattern, $text)) {
                    $data['statut'] = $label;
                    break;
                }
            }
        }
    }

    /**
     * Hydrate code_postal et ville depuis l'adresse complète.
     */
    private function hydrateAddressComponents(array &$data): void
    {
        if (empty($data['adresse'])) {
            return;
        }

        $address = trim($data['adresse']);
        if ($address === '') {
            return;
        }

        // Chercher code postal (5 chiffres) + ville
        $postalMatches = [];
        if (preg_match_all('/\b(\d{5})\b(?:\s+([A-Za-zÀ-ÖØ-öø-ÿ\'\-\s]+))?/u', $address, $postalMatches, PREG_SET_ORDER)) {
            $match = end($postalMatches);

            if (!empty($match[1]) && (empty($data['code_postal']) || strlen((string) $data['code_postal']) < 5)) {
                $normalizedPostal = $this->normalizePostalCode($match[1]);
                if ($normalizedPostal) {
                    $data['code_postal'] = $normalizedPostal;
                }
            }

            if (empty($data['ville']) && !empty($match[2])) {
                $cityCandidate = trim(preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\'\-\s]/u', '', $match[2]));
                if ($cityCandidate !== '') {
                    $data['ville'] = $cityCandidate;
                }
            }
        }

        // Sinon, chercher ville en dernier segment
        if (empty($data['ville'])) {
            $segments = preg_split('/[,;\n]/u', $address);
            $lastSegment = trim(end($segments));
            $lastSegment = preg_replace('/^\d{5}\s*/', '', $lastSegment);

            if ($lastSegment !== '' && !preg_match('/\d{3,}/', $lastSegment)) {
                $data['ville'] = $lastSegment;
            }
        }
    }

    /**
     * Tente de corriger un email incomplet en analysant la transcription.
     */
    private function tryFixIncompleteEmail(string $transcription, string $incompleteEmail): ?string
    {
        try {
            $lowerTranscription = mb_strtolower($transcription);

            $patterns = [
                '/(?:email|mail|adresse\s+email|adresse\s+mail)[^\n\.]{0,200}/',
                '/(?:mon|mon\s+email|mon\s+mail)[^\n\.]{0,200}/',
                '/(?:c\'?est|c\'?est\s+quoi|voici)[^\n\.]{0,200}(?:arobase|at|arrobase)[^\n\.]{0,200}/',
            ];

            $emailContext = '';
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $lowerTranscription, $matches)) {
                    $emailContext = $matches[0];
                    Log::info('🔍 Contexte email trouvé dans transcription', ['context' => $emailContext]);
                    break;
                }
            }

            if (empty($emailContext)) {
                Log::warning('❌ Aucun contexte email trouvé dans la transcription');
                return null;
            }

            $reconstructed = $emailContext;
            $reconstructed = preg_replace('/^.*?(?:email|mail|adresse|mon|c\'?est|voici)\s*/i', '', $reconstructed);
            $reconstructed = preg_replace('/\b(?:le|la|les|un|une|des|mon|ma|mes|c\'?est|voici|voilà)\b/i', '', $reconstructed);
            $reconstructed = preg_replace('/\b(?:arobase|at|arrobase|a\s+commercial)\b/i', '@', $reconstructed);
            $reconstructed = preg_replace('/\b(?:point|dot)\b/i', '.', $reconstructed);
            $reconstructed = preg_replace('/\b(?:tiret|tiret\s+du\s+8|trait\s+d\'?union)\b/i', '-', $reconstructed);
            $reconstructed = preg_replace('/\b(?:underscore|tiret\s+bas|souligné)\b/i', '_', $reconstructed);
            $reconstructed = preg_replace('/\s+/', '', $reconstructed);
            $reconstructed = preg_replace('/[^\w@.\-_]/', '', $reconstructed);

            Log::info('🔧 Email reconstruit', ['reconstructed' => $reconstructed]);

            if (str_contains($reconstructed, '@') && filter_var($reconstructed, FILTER_VALIDATE_EMAIL)) {
                return strtolower($reconstructed);
            }

            if (str_contains($reconstructed, '@')) {
                $parts = explode('@', $reconstructed);
                if (count($parts) === 2) {
                    $local = preg_replace('/[^\w.\-_]/', '', $parts[0]);
                    $domain = preg_replace('/[^\w.\-]/', '', $parts[1]);

                    if (!empty($local) && !empty($domain) && str_contains($domain, '.')) {
                        $finalEmail = strtolower($local . '@' . $domain);
                        if (filter_var($finalEmail, FILTER_VALIDATE_EMAIL)) {
                            Log::info('✅ Email nettoyé et validé', ['final' => $finalEmail]);
                            return $finalEmail;
                        }
                    }
                }
            }

            Log::warning('❌ Impossible de reconstruire un email valide', ['reconstructed' => $reconstructed]);
            return null;

        } catch (\Throwable $e) {
            Log::error('Erreur lors de la correction d\'email', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise les besoins et besoins_action (LOGIQUE CORRIGÉE).
     * 
     * Règles :
     * - Si besoins non vide ET besoins_action absent/invalide → "add"
     * - Si besoins vide/null → besoins_action = null
     * - Jamais "replace" par défaut
     */
    private function normalizeBesoins(array $data): array
    {
        // S'assurer que besoins est un tableau
        if (isset($data['besoins'])) {
            if (is_string($data['besoins'])) {
                $decoded = json_decode($data['besoins'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data['besoins'] = $decoded;
                } else {
                    $data['besoins'] = [$data['besoins']];
                }
            } elseif (!is_array($data['besoins'])) {
                $data['besoins'] = [];
            }

            // Nettoyer chaque besoin
            $data['besoins'] = array_map(function ($besoin) {
                if (is_string($besoin)) {
                    $decoded = json_decode($besoin, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                    return trim($besoin);
                }
                return $besoin;
            }, $data['besoins']);

            // Aplatir le tableau si nécessaire
            $data['besoins'] = array_reduce($data['besoins'], function ($carry, $item) {
                if (is_array($item)) {
                    return array_merge($carry, $item);
                }
                $carry[] = $item;
                return $carry;
            }, []);
        } else {
            $data['besoins'] = null;
        }

        // 🎯 LOGIQUE CORRIGÉE - besoins_action
        if (isset($data['besoins']) && !empty($data['besoins'])) {
            // Si besoins non vide
            if (!isset($data['besoins_action']) || !in_array($data['besoins_action'], ['add', 'remove'])) {
                // Si action absente ou invalide → forcer "add"
                Log::info('🔧 [BESOINS] Correction besoins_action → "add"', [
                    'besoins' => $data['besoins'],
                    'old_action' => $data['besoins_action'] ?? 'absent',
                ]);
                $data['besoins_action'] = 'add';
            }
        } else {
            // Si besoins vide → action = null
            $data['besoins_action'] = null;
        }

        return $data;
    }
}
