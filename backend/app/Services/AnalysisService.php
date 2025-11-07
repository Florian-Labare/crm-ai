<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalysisService
{
    public function extractClientData(string $transcription): array
    {
        $prompt = <<<PROMPT
            Analyse ce texte de conversation et extrais toutes les informations disponibles.

            Ne renvoie **rien d'autre** qu'un JSON valide contenant uniquement les champs mentionnés.

            Voici le texte à analyser :
            ---
            $transcription
            ---
        PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'OpenAI-Organization' => env('OPENAI_ORG_ID'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Modèle GPT-4 optimisé et moins coûteux
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<PROMPT
                        Tu es un assistant spécialisé en analyse de conversations pour un conseiller en assurance et gestion de patrimoine.

                        Ta tâche :
                        - Extraire et structurer les informations concernant un client à partir d'une transcription vocale.
                        - Tu dois produire un JSON contenant uniquement les champs mentionnés ou inférés.
                        - Ne jamais inventer de données qui n'existent pas dans la transcription.

                        🔤🔤🔤 RÈGLE #1 ABSOLUE ET PRIORITAIRE - ÉPELLATION 🔤🔤🔤
                        ⚠️ CETTE RÈGLE SURPASSE TOUTES LES AUTRES ⚠️

                        L'ÉPELLATION LETTRE PAR LETTRE EST LA RÈGLE SUPRÊME ET ANNULE TOUTE AUTRE INTERPRÉTATION.

                        - Quand un utilisateur épelle un champ (nom, prénom, ville, adresse, email, profession, etc.), c'est QU'IL VEUT ABSOLUMENT que tu utilises EXACTEMENT ces lettres.
                        - L'épellation ÉCRASE et REMPLACE toute interprétation phonétique, sémantique ou contextuelle.
                        - IGNORE complètement ce que tu "penses" avoir compris : si c'est épelé, UTILISE L'ÉPELLATION.

                        Formes d'épellation à détecter :
                        1. Lettres espacées : "L A B A R E" → "Labare"
                        2. Avec le mot "espace" : "R U E espace D E espace L A espace P A I X" → "Rue de la Paix"
                        3. Phonétique explicite : "M comme Michel, A comme Anatole, R comme Raoul" → "Mar..."
                        4. Chiffres épelés : "7 5 0 0 1" → "75001" ou "0 6 1 2 3 4 5 6 7 8" → "0612345678"
                        5. Mix lettres/mots : "rue D E espace L A espace R É P U B L I Q U E" → "rue de la République"

                        Exemples CRITIQUES d'épellation à respecter :
                        * "mon nom c'est L A B A R E" → {"nom": "Labare"} et PAS {"nom": "La Barre"} ou autre interprétation
                        * "mon prénom F L O R I A N" → {"prenom": "Florian"}
                        * "j'habite à P A R I S" → {"ville": "Paris"}
                        * "rue V I C T O R espace H U G O" → "rue Victor Hugo"
                        * "mon email c'est f l o r i a n arobase gmail point com" → "florian@gmail.com"
                        * "code postal 7 5 0 2 0" → {"code_postal": "75020"}
                        * "profession D É V E L O P P E U R" → {"profession": "Développeur"}

                        SI TU DÉTECTES UNE ÉPELLATION → UTILISE-LA TEXTUELLEMENT, POINT FINAL.
                        PAS DE REFORMULATION, PAS D'INTERPRÉTATION, PAS DE "CORRECTION".

                        📧📞🏠 CHAMPS ULTRA-SENSIBLES - ÉPELLATION MAXIMALE 📧📞🏠

                        Ces champs sont CRITIQUES et l'épellation y est ENCORE PLUS IMPORTANTE :

                        **EMAIL** :
                        - L'email est LE champ le plus sensible à l'épellation
                        - CHAQUE LETTRE épelée doit être utilisée EXACTEMENT

                        🔴🔴🔴 RÈGLE ULTRA CRITIQUE - EMAIL ET AROBASE @ 🔴🔴🔴

                        ⚠️ PRIORITÉ ABSOLUE #1 : DÉTECTION D'EMAIL ⚠️

                        Quand l'utilisateur dit "email", "mail", "adresse email", "adresse mail" :
                        → Il va TOUJOURS épeler l'adresse caractère par caractère
                        → Tu DOIS extraire cet email dans le champ "email"

                        RÈGLE AROBASE :
                        - "arobase" = @
                        - "at" = @
                        - "a commercial" = @
                        - "arrobase" = @
                        - PAS d'autre façon de dire @ à l'oral !

                        RÈGLE POINT :
                        - "point" = .
                        - "dot" = .

                        RÈGLE TIRET :
                        - "tiret" = -
                        - "tiret du 8" = -
                        - "trait d'union" = -

                        RÈGLE UNDERSCORE :
                        - "underscore" = _
                        - "tiret bas" = _
                        - "souligné" = _

                        EXEMPLES D'EMAILS ÉPELÉS (TRÈS IMPORTANT) :
                        ✅ "mon email f l o r i a n arobase gmail point com" → {"email": "florian@gmail.com"}
                        ✅ "email f l o r i a n at gmail point com" → {"email": "florian@gmail.com"}
                        ✅ "j e a n tiret p i e r r e arobase free point fr" → {"email": "jean-pierre@free.fr"}
                        ✅ "contact arobase entreprise point com" → {"email": "contact@entreprise.com"}
                        ✅ "m a r i e at yahoo point fr" → {"email": "marie@yahoo.fr"}
                        ✅ "info arobase societe point com" → {"email": "info@societe.com"}
                        ✅ "f l o r i a n point l a b a r e arobase gmail point com" → {"email": "florian.labare@gmail.com"}
                        ✅ "j tiret p tiret d u p o n t at hotmail point fr" → {"email": "j-p-dupont@hotmail.fr"}
                        ✅ "m a r i e underscore d u r a n d arobase yahoo point fr" → {"email": "marie_durand@yahoo.fr"}
                        ✅ "s a l e s at entreprise point com" → {"email": "sales@entreprise.com"}
                        ✅ "mon mail c'est a b c arobase test point fr" → {"email": "abc@test.fr"}
                        ✅ "vous pouvez me joindre sur p i e r r e point d u r a n d arobase orange point fr" → {"email": "pierre.durand@orange.fr"}

                        RÈGLE CRITIQUE : SUPPRIME TOUS LES ESPACES dans le résultat final de l'email !

                        ❌ ERREUR À NE JAMAIS FAIRE :
                        - NE JAMAIS écrire "arobase" dans l'email → utilise @
                        - NE JAMAIS écrire "point" dans l'email → utilise .
                        - NE JAMAIS laisser des espaces → supprime-les tous

                        **TÉLÉPHONE** :
                        - Si épelé chiffre par chiffre : "0 6 1 2 3 4 5 6 7 8" → "0612345678"
                        - Si groupé : "06 12 34 56 78" → "0612345678"
                        - SUPPRIME LES ESPACES dans le résultat final

                        **ADRESSE / VILLE / CODE POSTAL** :
                        - Ces champs géographiques sont souvent épelés pour la précision
                        - "ville P A R I S" → {"ville": "Paris"}
                        - "code postal 7 5 0 2 0" → {"code_postal": "75020"}
                        - "132 rue P E L L E P O R T" → {"adresse": "132 rue Pelleport"}

                        SI UN DE CES CHAMPS EST ÉPELÉ → C'EST LA PRIORITÉ ABSOLUE #1

                        ⚠️⚠️⚠️ RÈGLE CRITIQUE #1 - CHAMPS NON MENTIONNÉS ⚠️⚠️⚠️
                        🚫 NE JAMAIS INVENTER DE DONNÉES 🚫

                        RÈGLE ABSOLUE ET PRIORITAIRE :
                        - Si un champ n'est **pas explicitement mentionné** dans la transcription, ne l'inclus **ABSOLUMENT PAS** dans le JSON.
                        - N'inclus JAMAIS un champ avec une valeur vide (""), null, ou par défaut.
                        - NE JAMAIS faire de suppositions ou déductions sur des informations non dites.
                        - NE JAMAIS inventer ou compléter des informations manquantes.

                        Exemples CRITIQUES :
                        ✅ BON : Si seul le nom est dit → {"nom": "Dupont"}
                        ❌ MAUVAIS : {"nom": "Dupont", "prenom": ""}

                        ✅ BON : Si rien n'est dit sur la situation actuelle → {}
                        ❌ MAUVAIS : {"situation_actuelle": "locataire"} (INVENTÉ !)

                        ✅ BON : Si seul "je suis fumeur" est dit → {"fumeur": true}
                        ❌ MAUVAIS : {"fumeur": true, "activites_sportives": false} (le false est INVENTÉ !)

                        ✅ BON : Si "j'ai deux enfants" → {"nombreenfants": 2}
                        ❌ MAUVAIS : {"nombreenfants": 2, "situationmatrimoniale": "marié"} (SUPPOSÉ !)

                        Cette règle est ABSOLUE pour éviter d'écraser les données existantes et d'inventer des informations.
                        TU NE DOIS EXTRAIRE QUE CE QUI EST EXPLICITEMENT DIT, RIEN D'AUTRE.

                        ⚠️⚠️⚠️ RÈGLE CRITIQUE - FORMAT DES DATES ⚠️⚠️⚠️
                        TOUTES LES DATES DOIVENT ÊTRE AU FORMAT ISO : AAAA-MM-JJ (année-mois-jour)

                        ❌ INTERDIT : JJ/MM/AAAA ou DD/MM/YYYY ou tout autre format
                        ✅ OBLIGATOIRE : AAAA-MM-JJ (ex: 1972-01-20, 2025-10-30)

                        Exemples de conversion :
                        - Si l'utilisateur dit "20 janvier 1972" → "1972-01-20"
                        - Si l'utilisateur dit "5 mars 1985" → "1985-03-05"
                        - Si l'utilisateur dit "15 décembre 2000" → "2000-12-15"

                        Cette règle s'applique à TOUS les champs de date :
                        - datedenaissance
                        - date_situation_matrimoniale
                        - date_evenement_professionnel

                        Format attendu (uniquement avec les champs trouvés) :
                        {
                        // Identité de base
                        "civilite": "string (Monsieur ou Madame)",
                        "nom": "string",
                        "nom_jeune_fille": "string (pour les femmes mariées, seulement si Madame et mariée)",
                        "prenom": "string",
                        "datedenaissance": "string (AAAA-MM-JJ UNIQUEMENT, ex: 1972-01-20)",
                        "lieudenaissance": "string",
                        "nationalite": "string",

                        // Situation
                        "situationmatrimoniale": "string (célibataire, marié, pacsé, divorcé, veuf)",
                        "date_situation_matrimoniale": "string (AAAA-MM-JJ UNIQUEMENT, ex: 2015-06-20)",
                        "situation_actuelle": "string (locataire, propriétaire, hébergé, etc.) ⚠️ UNIQUEMENT si EXPLICITEMENT mentionné",

                        // Professionnel
                        "profession": "string",
                        "date_evenement_professionnel": "string (AAAA-MM-JJ UNIQUEMENT, ex: 2020-01-15)",
                        "risques_professionnels": boolean (true/false),
                        "details_risques_professionnels": "string (nature des risques si mentionnés)",
                        "revenusannuels": "number (montant en euros)",

                        // Coordonnées
                        "adresse": "string",
                        "code_postal": "string",
                        "ville": "string",
                        "residence_fiscale": "string",
                        "telephone": "string",
                        "email": "string",

                        // Mode de vie
                        "fumeur": boolean (true/false),
                        "activites_sportives": boolean (true/false),
                        "details_activites_sportives": "string (type de sport si mentionné)",
                        "niveau_activites_sportives": "string (loisir, compétition, professionnel)",

                        // Famille
                        "nombreenfants": "number",

                        // Besoins
                        "besoins": ["array de strings"],
                        "besoins_action": "add|remove|replace",

                        // Autres
                        "consentement_audio": boolean (true si le client consent à l'enregistrement),
                        "charge_clientele": "string (clientèle privée, professionnelle, entreprise)"
                        }

                        📧 EMAILS DICTÉS - RAPPEL DES RÈGLES :

                        ⚠️ RAPPEL : L'ÉPELLATION PRIME SUR TOUT (voir règles ci-dessus)

                        🔴🔴🔴 AROBASE = @ 🔴🔴🔴
                        Quand on ÉPELLE ou DICTE un email à l'oral :
                        - On dit "arobase" pour le symbole @
                        - On dit "at" pour le symbole @

                        Il n'y a PAS d'autre façon de dire @ à l'oral !

                        Tu DOIS convertir systématiquement :
                          * "arobase" → @
                          * "at" → @

                        Autres conversions des termes oraux :
                          * "point" → .
                          * "tiret" ou "tiret du 8" → -
                          * "underscore" ou "tiret bas" → _
                          * "slash" → /

                        Exemples de conversion SANS épellation :
                          * "florian point labare arobase gmail point com" → "florian.labare@gmail.com"
                          * "contact tiret commercial arobase entreprise point fr" → "contact-commercial@entreprise.fr"

                        Exemples AVEC épellation (PRIORITÉ ABSOLUE) :
                          * "f l o r i a n arobase gmail point com" → "florian@gmail.com"
                          * "j e a n tiret p i e r r e arobase free point fr" → "jean-pierre@free.fr"
                          * "m point d u p o n t arobase société point com" → "m.dupont@societe.com"
                          * "info underscore c o n t a c t arobase entreprise point fr" → "info_contact@entreprise.fr"

                        RÈGLES STRICTES pour email :
                        - Supprime TOUS les espaces dans l'email final
                        - Si épelé → utilise CHAQUE lettre exactement comme énoncée
                        - Si épelé partiellement → combine épellation + termes oraux
                        - AUCUNE "correction" ou reformulation permise

                        🏠 RÈGLE SPÉCIALE - ADRESSES DICTÉES :
                        - Les adresses comportent souvent des types de voies ET des noms épelés. Tu dois gérer les deux.
                        - Reconnaître les types de voies (garde-les en toutes lettres) :
                          * rue, avenue, boulevard, allée, impasse, place, cours, chemin, route, voie, square, passage, quai, esplanade, cité, villa, hameau, lotissement, résidence
                        - Si le NOM de la voie est épelé, reconstitue-le correctement en gardant le type de voie.
                        - Exemples de conversion :
                          * "12 rue V I C T O R espace H U G O" → "12 rue Victor Hugo"
                          * "5 avenue D E espace L A espace R É P U B L I Q U E" → "5 avenue de la République"
                          * "33 boulevard J E A N espace J A U R È S" → "33 boulevard Jean Jaurès"
                          * "8 allée D E S espace R O S E S" → "8 allée des Roses"
                          * "rue de la P A I X" → "rue de la Paix"
                          * "avenue M O N T A I G N E" → "avenue Montaigne"
                          * "15 impasse S A I N T tiret M I C H E L" → "15 impasse Saint-Michel"
                        - Garde les numéros de rue (même s'ils sont dictés) : "12", "5 bis", "33 ter"
                        - Reconstitue correctement les articles et prépositions : "de", "la", "le", "les", "du", "des"
                        - Les noms composés avec tiret doivent être préservés : "Saint-Michel", "Jean-Jaurès"
                        - Ajoute les majuscules appropriées aux noms propres de rues.

                        📝 RÈGLES GÉNÉRALES :
                        - Si tu ne trouves pas une valeur, n'inclus pas la clé correspondante.
                        - Si des nombres sont cités en mots ("trente-six mille cinq cents euros"), convertis-les en chiffres ("36500").
                        - Rappel : TOUS les champs peuvent être épelés (voir RÈGLE #1 ci-dessus)

                        🎯 RÈGLES - BESOINS :
                        - Pour "besoins", retourne un TABLEAU de besoins (ex: ["mutuelle", "prévoyance", "assurance habitation"]).
                        - Pour "besoins_action", détecte l'intention :
                          * "add" si le client dit "ajouter", "rajouter", "j'ai aussi besoin de", "en plus", etc.
                          * "remove" si le client dit "retirer", "supprimer", "enlever", "plus besoin de", etc.
                          * "replace" si le client reformule tous ses besoins ou dit "mes besoins sont", "j'ai besoin de", etc. (sans mot-clé d'ajout)

                        ✅ RÈGLES - BOOLÉENS :
                        - Pour les champs booléens (risques_professionnels, fumeur, activites_sportives, consentement_audio), utilise true/false.
                        - Pour "fumeur", détecte "je fume", "je suis fumeur", "non-fumeur" (false si "non"), etc.
                        - Pour "activites_sportives", détecte la mention de sports ou activités physiques.

                        ⚠️ IMPORTANT :
                        - Si aucune mention de besoins, n'inclus pas ces champs.
                        - Ne réponds **que** avec un JSON valide, sans texte explicatif.
                        - Privilégie TOUJOURS l'épellation sur l'interprétation phonétique.
                        PROMPT
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
            ]);

            $json = $response->json();
            Log::info($response->json());
            $raw = $json['choices'][0]['message']['content'] ?? '';

            // 🧾 Log brut pour debug
            Log::info('Réponse brute OpenAI', ['raw' => $raw]);

            // ✅ On isole le JSON proprement
            $raw = trim($raw);
            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $raw = $matches[0];
            }

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            // 🔧 POST-PROCESSING SPÉCIAL - CORRECTION EMAIL INCOMPLET
            // Si GPT a raté l'extraction du @, on essaie de le récupérer depuis la transcription
            if (isset($data['email']) && !empty($data['email']) && !str_contains($data['email'], '@')) {
                Log::warning('⚠️ Email incomplet détecté (pas de @)', ['email' => $data['email']]);
                $fixedEmail = $this->tryFixIncompleteEmail($transcription, $data['email']);
                if ($fixedEmail) {
                    Log::info('✅ Email corrigé automatiquement', ['avant' => $data['email'], 'après' => $fixedEmail]);
                    $data['email'] = $fixedEmail;
                }
            }

            // 🧹 Normalisation - On ne définit pas de valeurs par défaut
            // Les champs non mentionnés ne seront pas envoyés au controller

            // 📅 Normalisation des dates - conversion au format ISO YYYY-MM-DD
            $dateFields = ['datedenaissance', 'date_situation_matrimoniale', 'date_evenement_professionnel'];
            foreach ($dateFields as $field) {
                if (isset($data[$field]) && !empty($data[$field])) {
                    $data[$field] = $this->normalizeDateToISO($data[$field]);
                }
            }

            // 📞 Normalisation du téléphone - suppression des espaces et caractères non numériques
            if (isset($data['telephone']) && !empty($data['telephone'])) {
                $data['telephone'] = $this->normalizePhone($data['telephone']);
            }

            // 📧 Normalisation de l'email - validation et mise en minuscules
            if (isset($data['email']) && !empty($data['email'])) {
                $data['email'] = $this->normalizeEmail($data['email']);
            }

            // 📮 Normalisation du code postal - validation du format français
            if (isset($data['code_postal']) && !empty($data['code_postal'])) {
                $data['code_postal'] = $this->normalizePostalCode($data['code_postal']);
            }

            // 🔢 Normalisation des nombres
            if (isset($data['revenusannuels'])) {
                $data['revenusannuels'] = is_numeric($data['revenusannuels'])
                    ? (float) $data['revenusannuels']
                    : null;
            }
            if (isset($data['nombreenfants'])) {
                $data['nombreenfants'] = is_numeric($data['nombreenfants'])
                    ? (int) $data['nombreenfants']
                    : null;
            }

            // ✅ Normalisation des booléens
            $booleanFields = ['fumeur', 'activites_sportives', 'risques_professionnels', 'consentement_audio'];
            foreach ($booleanFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }

            // 🎯 Normalisation des besoins
            if (isset($data['besoins'])) {
                // S'assurer que besoins est un tableau
                if (is_string($data['besoins'])) {
                    $data['besoins'] = [$data['besoins']];
                } elseif (!is_array($data['besoins'])) {
                    $data['besoins'] = [];
                }
            } else {
                $data['besoins'] = null;
            }

            // Valider besoins_action
            if (isset($data['besoins_action'])) {
                if (!in_array($data['besoins_action'], ['add', 'remove', 'replace'])) {
                    $data['besoins_action'] = 'replace'; // par défaut
                }
            } else {
                $data['besoins_action'] = $data['besoins'] ? 'replace' : null;
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Erreur dans AnalysisService', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalise une date vers le format ISO (YYYY-MM-DD)
     *
     * @param string $date Date à normaliser
     * @return string|null Date au format ISO ou null si invalide
     */
    private function normalizeDateToISO(string $date): ?string
    {
        try {
            // Nettoyer la date (supprimer espaces)
            $date = trim($date);

            // Si déjà au format ISO (YYYY-MM-DD), retourner tel quel
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }

            // Si format français DD/MM/YYYY ou JJ/MM/AAAA
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                return "$year-$month-$day";
            }

            // Si format avec tirets mais inversé DD-MM-YYYY
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                return "$year-$month-$day";
            }

            // Tenter de parser avec Carbon (pour d'autres formats)
            $carbonDate = \Carbon\Carbon::parse($date);
            return $carbonDate->format('Y-m-d');

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser la date', ['date' => $date, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalise un numéro de téléphone (supprime espaces, points, tirets)
     *
     * @param string $phone Numéro de téléphone
     * @return string|null Numéro normalisé ou null si invalide
     */
    private function normalizePhone(string $phone): ?string
    {
        try {
            // Supprimer tous les espaces, points, tirets, parenthèses
            $normalized = preg_replace('/[\s.\-()]/', '', $phone);

            // Garder uniquement les chiffres et le + en début
            $normalized = preg_replace('/[^0-9+]/', '', $normalized);

            // Validation basique : doit commencer par 0 ou + et avoir au moins 10 chiffres
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
     * Normalise une adresse email
     *
     * @param string $email Adresse email
     * @return string|null Email normalisé ou null si invalide
     */
    private function normalizeEmail(string $email): ?string
    {
        try {
            // Supprimer les espaces
            $normalized = trim($email);

            // Convertir en minuscules
            $normalized = strtolower($normalized);

            // Valider le format email
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
     * Normalise un code postal français
     *
     * @param string $postalCode Code postal
     * @return string|null Code postal normalisé (5 chiffres) ou null si invalide
     */
    private function normalizePostalCode(string $postalCode): ?string
    {
        try {
            // Supprimer les espaces
            $normalized = trim($postalCode);

            // Supprimer tous les caractères non numériques
            $normalized = preg_replace('/[^0-9]/', '', $normalized);

            // Validation : doit être exactement 5 chiffres pour la France
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
     * Tente de corriger un email incomplet en analysant la transcription originale
     *
     * @param string $transcription Transcription vocale complète
     * @param string $incompleteEmail Email incomplet extrait par GPT
     * @return string|null Email corrigé ou null si impossible
     */
    private function tryFixIncompleteEmail(string $transcription, string $incompleteEmail): ?string
    {
        try {
            // Normaliser la transcription en minuscules pour la recherche
            $lowerTranscription = mb_strtolower($transcription);

            // 🔍 Chercher les patterns d'email dans la transcription
            // Pattern 1: "email ..." ou "mail ..." ou "adresse email ..."
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

            // 🔧 Extraire et reconstruire l'email depuis le contexte
            // Chercher le pattern : [lettres/mots] + arobase/at + [lettres/mots] + point/dot + [extension]
            $reconstructed = $emailContext;

            // Supprimer les mots-clés initiaux
            $reconstructed = preg_replace('/^.*?(?:email|mail|adresse|mon|c\'?est|voici)\s*/i', '', $reconstructed);

            // Nettoyer les mots inutiles
            $reconstructed = preg_replace('/\b(?:le|la|les|un|une|des|mon|ma|mes|c\'?est|voici|voilà)\b/i', '', $reconstructed);

            // Convertir les termes oraux en symboles
            $reconstructed = preg_replace('/\b(?:arobase|at|arrobase|a\s+commercial)\b/i', '@', $reconstructed);
            $reconstructed = preg_replace('/\b(?:point|dot)\b/i', '.', $reconstructed);
            $reconstructed = preg_replace('/\b(?:tiret|tiret\s+du\s+8|trait\s+d\'?union)\b/i', '-', $reconstructed);
            $reconstructed = preg_replace('/\b(?:underscore|tiret\s+bas|souligné)\b/i', '_', $reconstructed);

            // Supprimer tous les espaces
            $reconstructed = preg_replace('/\s+/', '', $reconstructed);

            // Nettoyer les caractères parasites
            $reconstructed = preg_replace('/[^\w@.\-_]/', '', $reconstructed);

            Log::info('🔧 Email reconstruit', ['reconstructed' => $reconstructed]);

            // Valider que le résultat contient bien un @
            if (str_contains($reconstructed, '@') && filter_var($reconstructed, FILTER_VALIDATE_EMAIL)) {
                return strtolower($reconstructed);
            }

            // Si la validation complète échoue mais qu'on a un @, on essaie quand même de construire un email valide
            if (str_contains($reconstructed, '@')) {
                // Essayer de nettoyer davantage
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
}
