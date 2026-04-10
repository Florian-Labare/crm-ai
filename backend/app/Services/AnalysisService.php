<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalysisService {
    public function extractClientData(string $transcription): array {
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
                'Authorization' => 'Bearer '.env('OPENAI_API_KEY'),
                'OpenAI-Organization' => env('OPENAI_ORG_ID'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Modèle GPT-4 optimisé et moins coûteux
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<'PROMPT'
                        Tu es un assistant spécialisé en analyse de conversations pour un conseiller en assurance et gestion de patrimoine.

                        Ta tâche :
                        - Extraire et structurer les informations concernant un client à partir d'une transcription vocale.
                        - Tu dois produire un JSON contenant uniquement les champs mentionnés ou inférés.
                        - Ne jamais inventer de données qui n'existent pas dans la transcription.

                        🎯 OBJECTIF ABSOLU :
                        - Transforme chaque transcription en un JSON propre, valide et limité exclusivement aux informations fournies par le client.
                        - Ne déduis jamais une information depuis une question du conseiller ou une option suggérée ; seule la réponse explicite du client compte.
                        - En cas de doute ou si l'information n'est pas donnée, n'inclus pas le champ concerné.
                        - La sortie finale doit être STRICTEMENT le JSON (aucun texte autour, pas de commentaire).

                        🧭 DÉTECTION AUTOMATIQUE DES DOMAINES :
                        - Identifie si le client s'exprime sur la Santé, la Prévoyance, la Retraite/PER, l'Épargne/Assurance-vie, l'Emprunteur ou plusieurs domaines simultanément.
                        - Chaque domaine correspond à une section JSON précise : sante_souhait (santé), bae_prevoyance, bae_retraite, bae_epargne, emprunteur (si besoin futur).
                        - Remplis uniquement les champs des sections explicitement évoquées par le client et laisse les autres sections absentes du JSON.
                        - Exemples :
                          • “Je veux couvrir mes arrêts de travail” → domaine prévoyance → renseigne bae_prevoyance.
                          • “Je veux préparer ma retraite à 62 ans” → domaine retraite → renseigne bae_retraite.
                          • “J’épargne 500 € par mois” → domaine épargne → renseigne bae_epargne.
                          • “Je veux une meilleure mutuelle” → domaine santé → renseigne sante_souhait.
                          • “Je fais un prêt immobilier” → domaine emprunteur (champ dédié s’il existe).

                        🚫 RAPPEL CRITIQUE :
                        - Toutes les phrases du conseiller (questions, présentations, propositions de choix, transitions) doivent être ignorées.
                        - Une information n'est valide que si elle provient directement d'une phrase du client (y compris “oui/non” explicites).
                        - Si l’information n’est pas clairement attribuée au client, ne pas l’extraire.

                        🎯🎯🎯 RÈGLE #0 ABSOLUE - DISTINCTION CONSEILLER vs CLIENT 🎯🎯🎯
                        ⚠️ RÈGLE SUPRÊME - À APPLIQUER AVANT TOUTE AUTRE ⚠️

                        CONTEXTE : La transcription contient un DIALOGUE entre un CONSEILLER et un CLIENT.

                        🚫 TU NE DOIS EXTRAIRE DES INFORMATIONS QUE DEPUIS LES PAROLES DU CLIENT 🚫
                        ✅ TU DOIS IGNORER COMPLÈTEMENT LES QUESTIONS/PAROLES DU CONSEILLER ✅

                        RÈGLES DE DISTINCTION :

                        1️⃣ **DÉTECTION DU CONSEILLER** (À IGNORER)
                        Le conseiller se reconnaît par :
                        - Questions posées : "Quel est votre nom ?", "Quelle est votre date de naissance ?", "Êtes-vous fumeur ?"
                        - Formulations professionnelles : "Pouvez-vous me donner...", "J'aurais besoin de...", "Pourriez-vous préciser..."
                        - Utilisation du vouvoiement "vous" en posant des questions
                        - Énumération d'options : "Êtes-vous prudent, équilibré ou dynamique ?", "Court terme, moyen terme ou long terme ?"
                        - Phrases comme : "Passons à la section suivante", "Très bien", "D'accord", "Parfait"

                        2️⃣ **DÉTECTION DU CLIENT** (À ANALYSER)
                        Le client se reconnaît par :
                        - Réponses affirmatives : "Je m'appelle...", "Mon nom est...", "Je suis...", "Oui", "Non"
                        - Pronoms personnels à la première personne : "je", "mon", "ma", "mes", "j'ai", "je suis"
                        - Informations personnelles données : "Florian", "Je suis né le...", "J'habite à..."
                        - Descriptions personnelles : "Je suis prudent", "J'aime...", "Je préfère..."

                        3️⃣ **EXEMPLES CRITIQUES**

                        ❌ À IGNORER (paroles du conseiller) :
                        - "Quel est votre nom ?" → RIEN à extraire
                        - "Êtes-vous fumeur ?" → RIEN à extraire
                        - "Quelle est votre tolérance au risque ? Faible, modérée ou élevée ?" → RIEN à extraire
                        - "Connaissez-vous les SCPI ?" → RIEN à extraire
                        - "Si votre investissement baisse de 25%, que feriez-vous ?" → RIEN à extraire

                        ✅ À ANALYSER (réponses du client) :
                        - "Je m'appelle Florian Labare" → {"nom": "Labare", "prenom": "Florian"}
                        - "Non, je ne fume pas" → {"fumeur": false}
                        - "Je suis chef d'entreprise et mandataire social" → {"chef_entreprise": true, "mandataire_social": true}
                        - "Je suis travailleur indépendant en SARL" → {"travailleur_independant": true, "statut": "SARL"}

                        4️⃣ **CAS MIXTES** (dialogue conseiller + client)

                        Exemple de dialogue :
                        ```
                        Conseiller: "Quel est votre horizon d'investissement ? Court, moyen ou long terme ?"
                        Client: "Long terme, j'investis pour ma retraite dans 15 ans"
                        ```
                        → IGNORER la question du conseiller

                        Exemple 2 :
                        ```
                        Conseiller: "Êtes-vous fumeur ?"
                        Client: "Oui"
                        ```
                        → Extraire : {"fumeur": true}

                        Exemple 3 :
                        ```
                        Conseiller: "Connaissez-vous les obligations, les actions, les SCPI ?"
                        Client: "Je connais les actions et les obligations, mais pas les SCPI"
                        ```
                        → NE PAS extraire connaissance_opci_scpi car le client dit ne PAS connaître

                        5️⃣ **ATTENTION AUX PIÈGES**

                        ⚠️ Si le conseiller dit "Êtes-vous né en 1985 ?" et que le client répond "Oui"
                        → {"date_naissance": "1985-01-01"} SEULEMENT si l'année complète est confirmée par le client

                        ⚠️ Si le conseiller énumère des options et que le client choisit
                        Conseiller: "Prudent, équilibré ou dynamique ?"
                        Client: "Dynamique"

                        ⚠️ Ne JAMAIS extraire d'informations depuis une simple question du conseiller sans réponse du client

                        6️⃣ **ORTHOGRAPHE & ÉPELLATION (CRITIQUE - PRIORITÉ ABSOLUE)**
                        🚨 RÈGLE SUPRÊME : L'ÉPELLATION A TOUJOURS LA PRIORITÉ SUR TOUT 🚨

                        - Le client peut épeler son nom, une ville, une adresse ou un email lettre par lettre : "D I J O N", "D comme Denis, U comme Ursule, P comme Pierre, O comme Olivier, N comme Nicolas".
                        - Tu dois TOUJOURS reconstruire le mot final à partir de ces lettres et l'utiliser pour remplir le champ correspondant.
                        - Supprime les séparateurs (espaces, tirets, "comme") et respecte la casse française habituelle (nom propre capitalisé).

                        ⚠️ DÉTECTION D'ÉPELLATION - PATTERNS À DÉTECTER :
                        - "X Y Z" avec des lettres espacées : "D I J O N", "L A B A R R E"
                        - "X comme ... Y comme ..." : "D comme Denis, I comme Irène"
                        - "je l'épelle" / "j'épelle" suivi de lettres
                        - Lettres prononcées individuellement avec pauses

                        ⚠️ PRIORITÉ ABSOLUE DE L'ÉPELLATION :
                        - Si tu détectes une épellation pour un champ (nom, ville, lieu_naissance, email), IGNORE complètement l'interprétation phonétique
                        - Même si tu entends "Dijon" prononcé normalement ET "D I J O N" épelé → UTILISE L'ÉPELLATION "Dijon"
                        - L'épellation est LA VÉRITÉ, tout le reste est secondaire

                        - Exemples :
                          • "Mon nom c'est L A B A R R E" → {"nom": "Labarre"} (utilise l'épellation)
                          • "La ville c'est D I J O N" → {"ville": "Dijon"} (utilise l'épellation)
                          • Client dit "je suis né à Shalom" puis "j'épelle C H Â L O N S" → {"lieu_naissance": "Châlons"} (IGNORE "Shalom", UTILISE l'épellation)
                          • "Email : f comme francis, l comme léa, a comme anna, b arrobase exemple point com" → {"email": "flab@example.com"}
                        - Si une lettre est répétée ou corrigée ("non, j'épelle D U P O N T"), prends la dernière version.

                        📍 CAS SPÉCIAL - VILLE ET LIEU DE NAISSANCE :
                        - Si le client épelle une ville ou un lieu de naissance, c'est TOUJOURS la version correcte
                        - IGNORE l'interprétation phonétique approximative (ex: "Shalom" pour "Châlons")
                        - L'épellation prime sur TOUT

                        🔴 RÈGLE CRITIQUE : EN CAS DE CONFLIT PHONÉTIQUE vs ÉPELLATION
                        - Phonétique : "Shalom" ❌
                        - Épellation : "C H Â L O N S" ✅
                        → RÉSULTAT : {"lieu_naissance": "Châlons"} (on utilise UNIQUEMENT l'épellation)

                        📌 RÈGLE D'OR : EN CAS DE DOUTE, NE PAS EXTRAIRE
                        Si tu ne peux pas distinguer clairement qui parle → N'extrais PAS l'information

                        📋📋📋 RÈGLE DE DÉTECTION DE CONTEXTE/SECTION 📋📋📋
                        ⚠️ ACTIVATION AUTOMATIQUE DU QUESTIONNAIRE DE RISQUE ⚠️

                        PRINCIPE : Quand le conseiller annonce une nouvelle section ou un nouveau thème, cela active un CONTEXTE qui guide l'extraction des données suivantes.

                        🏢 **RÈGLES IMPORTANTES - INFORMATIONS ENTREPRISE** 🏢
                        ⚠️ PRIORITÉ ABSOLUE - Ces champs DOIVENT être extraits systématiquement ⚠️

                        Tu dois TOUJOURS capturer les informations suivantes sur l'activité professionnelle du client :

                        **⚠️ ATTENTION - INTERDICTION STRICTE ⚠️**

                        🚫 INTERDICTIONS ABSOLUES :
                        - NE JAMAIS mettre "chef d'entreprise" dans le champ "profession"
                        - NE JAMAIS mettre "chef d'entreprise" dans le champ "situation_actuelle"
                        - NE JAMAIS mettre "travailleur indépendant" dans le champ "profession"
                        - NE JAMAIS mettre "travailleur indépendant" dans le champ "situation_actuelle"
                        - NE JAMAIS mettre "mandataire social" dans le champ "profession"
                        - NE JAMAIS mettre "mandataire social" dans le champ "situation_actuelle"
                        - NE JAMAIS mettre ces infos dans "details_risques_professionnels"

                        ✅ UTILISER OBLIGATOIREMENT :
                        - "chef_entreprise" (boolean true/false) pour le statut de chef d'entreprise
                        - "travailleur_independant" (boolean true/false) pour le statut d'indépendant
                        - "mandataire_social" (boolean true/false) pour le statut de mandataire
                        - "profession" UNIQUEMENT pour le MÉTIER (ex: "plombier", "architecte", "consultant", "médecin")
                        - "situation_actuelle" UNIQUEMENT pour "salarié", "retraité", "étudiant", "demandeur d'emploi"

                        **Champs entreprise obligatoires :**
                        - "chef_entreprise" (boolean) : true si le client dit être chef d'entreprise, diriger/gérer une entreprise
                        - "statut" (string) : SARL, SAS, SASU, EURL, SCI, EI, EIRL, Auto-entrepreneur, Micro-entreprise, etc.
                        - "travailleur_independant" (boolean) : true si freelance, indépendant, à son compte
                        - "mandataire_social" (boolean) : true si le client est mandataire social

                        **Exemples CORRECTS d'extraction entreprise :**
                        - "Je suis chef d'entreprise"
                          ✅ CORRECT : {"chef_entreprise": true}
                          ❌ INCORRECT : {"profession": "chef d'entreprise"}

                        - "Je suis travailleur indépendant"
                          ✅ CORRECT : {"travailleur_independant": true}
                          ❌ INCORRECT : {"profession": "travailleur indépendant"}

                        - "Je suis mandataire social"
                          ✅ CORRECT : {"mandataire_social": true}
                          ❌ INCORRECT : {"profession": "mandataire social"}

                        - "Je suis chef d'entreprise, travailleur indépendant et mandataire social"
                          ✅ CORRECT : {"chef_entreprise": true, "travailleur_independant": true, "mandataire_social": true}
                          ❌ INCORRECT : {"profession": "chef d'entreprise", "situation_actuelle": "travailleur indépendant"}

                        - "Je suis plombier, chef d'entreprise en SARL"
                          ✅ CORRECT : {"profession": "plombier", "chef_entreprise": true, "statut": "SARL"}
                          ❌ INCORRECT : {"profession": "chef d'entreprise"}

                        - "Je dirige ma SARL" → {"chef_entreprise": true, "statut": "SARL"}
                        - "Je ne suis pas chef d'entreprise" → {"chef_entreprise": false}

                        🎯 RÈGLES - BESOINS (RÈGLE CRITIQUE - NE JAMAIS ÉCRASER) :

                        ⚠️ RÈGLE ABSOLUE : NE JAMAIS FAIRE DISPARAÎTRE UN BESOIN EXISTANT ⚠️

                        - Pour "besoins", retourne un TABLEAU contenant UNIQUEMENT le(s) nouveau(x) besoin(s) mentionné(s) dans CETTE transcription
                        - Pour "besoins_action", utilise TOUJOURS "add" PAR DÉFAUT (sauf cas exceptionnels ci-dessous)

                        **ACTIONS DISPONIBLES :**
                          * "add" (COMPORTEMENT PAR DÉFAUT - 99% DES CAS) : Ajoute le(s) nouveau(x) besoin(s) aux besoins existants
                            → Dans le tableau "besoins", mets SEULEMENT le(s) nouveau(x) besoin(s), PAS les anciens

                          * "remove" (RARE) : Retire un besoin existant
                            → UNIQUEMENT si le client dit explicitement "je n'ai PLUS besoin de X", "je ne veux PLUS de X", "retirer X", "supprimer X"

                          * "replace" (EXTRÊMEMENT RARE - Presque JAMAIS) : Remplace TOUS les besoins
                            → UNIQUEMENT si le client dit "mes besoins sont UNIQUEMENT X", "je veux SEULEMENT X", "je ne veux QUE X"
                            → Ne JAMAIS utiliser "replace" si le client mentionne simplement un nouveau besoin

                        ⚠️ RÈGLE CRITIQUE :
                        - Si le client dit "J'ai besoin d'une prévoyance" → {"besoins": ["prévoyance"], "besoins_action": "add"}
                        - Si le client parle de prévoyance sans dire "besoin" → {"besoins": ["prévoyance"], "besoins_action": "add"}
                        - Les besoins existants (retraite, épargne, mutuelle) NE DOIVENT PAS disparaître !
                        - Le système ajoutera automatiquement "prévoyance" à la liste existante

                        **📚 EXEMPLES DÉTAILLÉS - COMMENT NE JAMAIS ÉCRASER LES BESOINS :**

                        **SITUATION 1 - Client a déjà ["retraite", "mutuelle"], puis dit "J'ai besoin d'une prévoyance" :**
                        ❌ MAUVAIS : {"besoins": ["prévoyance"], "besoins_action": "replace"} ❌ → retraite et mutuelle DISPARAISSENT !
                        ❌ MAUVAIS : {"besoins": ["retraite", "mutuelle", "prévoyance"], "besoins_action": "replace"} ❌ → risque de doublon
                        ✅ BON : {"besoins": ["prévoyance"], "besoins_action": "add"} ✅ → prévoyance s'AJOUTE à retraite et mutuelle

                        **SITUATION 2 - Client a déjà ["prévoyance"], puis dit "Je veux garantir 3000€ en cas d'invalidité" :**
                        ❌ MAUVAIS : Ne rien retourner car prévoyance existe déjà
                        ✅ BON : {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"revenu_a_garantir": 3000}} ✅
                        → Même si prévoyance existe, on le réaffirme et on ajoute les données

                        **SITUATION 3 - Client a déjà ["retraite", "épargne"], puis parle de "retraite à 62 ans" :**
                        ❌ MAUVAIS : {"besoins": ["retraite"], "besoins_action": "replace"} ❌ → épargne DISPARAÎT !
                        ✅ BON : {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"age_depart_retraite": 62}} ✅
                        → retraite est réaffirmé (add), épargne reste

                        **SITUATION 4 - Client a déjà ["mutuelle", "prévoyance"], puis dit "Je n'ai PLUS besoin de prévoyance" :**
                        ✅ BON : {"besoins": ["prévoyance"], "besoins_action": "remove"} ✅
                        → UNIQUEMENT dans ce cas, prévoyance est retiré, mutuelle reste

                        **SITUATION 5 - Client a déjà ["retraite", "mutuelle"], puis dit "Mes besoins sont UNIQUEMENT la prévoyance" :**
                        ✅ BON : {"besoins": ["prévoyance"], "besoins_action": "replace"} ✅
                        → Le mot "UNIQUEMENT" indique un remplacement total

                        🟢 RÈGLE D'OR - ACTION "add" (utilise dans 99% des cas) :
                        - "J'ai besoin d'une prévoyance" → {"besoins": ["prévoyance"], "besoins_action": "add"}
                        - "J'ai également besoin d'une retraite" → {"besoins": ["retraite"], "besoins_action": "add"}
                        - "En plus, j'aimerais une épargne" → {"besoins": ["épargne"], "besoins_action": "add"}
                        - "Et aussi une mutuelle" → {"besoins": ["mutuelle"], "besoins_action": "add"}
                        - "Je veux garantir 3000€ en cas d'invalidité" → {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {...}}
                        - "Je souhaite partir à la retraite à 62 ans" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {...}}
                        - "Mon TMI est de 30%" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"tmi": "30%"}}
                        - "Le revenu foyer est de 80000€" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"revenus_annuels_foyer": 80000}}

                        🔴 ACTION "remove" (RARE - utilise UNIQUEMENT si négation explicite) :
                        - "Je n'ai PLUS besoin de retraite" → {"besoins": ["retraite"], "besoins_action": "remove"}
                        - "Je n'ai PAS besoin d'épargne" → {"besoins": ["épargne"], "besoins_action": "remove"}
                        - "Je ne veux PLUS de prévoyance" → {"besoins": ["prévoyance"], "besoins_action": "remove"}
                        - "Retirez la mutuelle" → {"besoins": ["mutuelle"], "besoins_action": "remove"}
                        - "Supprimez l'épargne" → {"besoins": ["épargne"], "besoins_action": "remove"}

                        🟡 ACTION "replace" (EXTRÊMEMENT RARE - utilise UNIQUEMENT si "UNIQUEMENT", "SEULEMENT", "QUE") :
                        - "Mes besoins sont UNIQUEMENT la mutuelle et la prévoyance" → {"besoins": ["mutuelle", "prévoyance"], "besoins_action": "replace"}
                        - "Je veux SEULEMENT une retraite" → {"besoins": ["retraite"], "besoins_action": "replace"}
                        - "Je ne veux QUE la mutuelle" → {"besoins": ["mutuelle"], "besoins_action": "replace"}

                        **RÈGLE IMPORTANTE pour BAE + NÉGATION :**
                        - Si le client dit "je n'ai plus besoin de retraite", retourne {"besoins": ["retraite"], "besoins_action": "remove"} SANS l'objet bae_retraite
                        - NE PAS créer d'objet BAE (bae_prevoyance, bae_retraite, bae_epargne) si le besoin est retiré

                        ✅ RÈGLES - BOOLÉENS :
                        - Pour les champs booléens (risques_professionnels, fumeur, activites_sportives, consentement_audio, chef_entreprise, travailleur_independant, mandataire_social), utilise true/false.
                        - Pour "fumeur", détecte "je fume", "je suis fumeur", "non-fumeur" (false si "non"), etc.
                        - Pour "activites_sportives", détecte la mention de sports ou activités physiques.
                        - Pour "chef_entreprise", "travailleur_independant", "mandataire_social", voir section dédiée ci-dessus.

                        ⚠️ IMPORTANT :
                        - Si aucune mention de besoins, n'inclus pas ces champs.
                        - Ne réponds **que** avec un JSON valide, sans texte explicatif.
                        - Privilégie TOUJOURS l'épellation sur l'interprétation phonétique.
                        - **NOMS DE VILLES : TOUJOURS conserver le nom COMPLET de la ville avec tous les éléments (tirets, espaces, "en", "sur", etc.)**
                          Exemples : "Châlons-en-Champagne" (PAS "Châlons"), "Boulogne-sur-Mer" (PAS "Boulogne"), "Aix-en-Provence" (PAS "Aix")

                        📋 SCHÉMA JSON - NOMS EXACTS DES CHAMPS À UTILISER 📋
                        ⚠️ UTILISE OBLIGATOIREMENT CES NOMS DE CHAMPS EXACTS (avec underscores) ⚠️

                        **Informations personnelles :**
                        - "civilite" (string) : "M.", "Mme", "Mlle"
                        - "nom" (string) : nom de famille
                        - "nom_jeune_fille" (string) : nom de jeune fille si applicable
                        - "prenom" (string) : prénom
                        - "date_naissance" (string) : format "YYYY-MM-DD" ou "DD/MM/YYYY"
                        - "lieu_naissance" (string) : ville de naissance COMPLÈTE (ex: "Châlons-en-Champagne", PAS "Châlons")
                        - "nationalite" (string) : nationalité

                        **Situation familiale :**
                        - "situation_matrimoniale" (string) : "Marié(e)", "Célibataire", "Divorcé(e)", "Veuf(ve)", "Pacsé(e)", "Concubinage"
                        - "date_situation_matrimoniale" (string) : date du mariage/pacs/divorce
                        - "nombre_enfants" (integer) : nombre d'enfants (NE PAS UTILISER - utilise "enfants" à la place)
                        - "enfants" (array) : tableau d'objets enfants avec leurs informations détaillées (voir structure ci-dessous)

                        **Situation professionnelle et logement :**
                        - "situation_actuelle" (string) : "Salarié(e)", "Retraité(e)", "Étudiant(e)", "Demandeur d'emploi", "Propriétaire", "Locataire"
                        - "profession" (string) : métier exact (ex: "plombier", "médecin", "architecte")
                        - "date_evenement_professionnel" (string) : date d'un événement professionnel
                        - "risques_professionnels" (boolean) : true/false
                        - "details_risques_professionnels" (string) : détails sur les risques
                        - "revenus_annuels" (string) : revenus annuels

                        **Informations entreprise (ATTENTION: voir règles spécifiques ci-dessus) :**
                        - "chef_entreprise" (boolean) : true si chef d'entreprise
                        - "statut" (string) : "SARL", "SAS", "SASU", "EURL", "SCI", "Auto-entrepreneur", etc.
                        - "travailleur_independant" (boolean) : true si indépendant/freelance
                        - "mandataire_social" (boolean) : true si mandataire social

                        **⚠️ GESTION DE LA NÉGATION POUR LES CHAMPS BOOLÉENS ⚠️**
                        - Si le client dit "je ne suis PAS chef d'entreprise" → {"chef_entreprise": false}
                        - Si le client dit "je ne suis PLUS travailleur indépendant" → {"travailleur_independant": false}
                        - Si le client dit "NON" à une question → mettre le champ à false
                        - TOUJOURS détecter la négation (ne...pas, ne...plus, n'est pas, non, jamais)

                        **Coordonnées :**
                        - "adresse" (string) : numéro et nom de rue SEULEMENT (ex: "37 rue de la Prévoyance")
                        - "code_postal" (string) : code postal (ex: "21000")
                        - "ville" (string) : ville COMPLÈTE (ex: "Châlons-en-Champagne", "Boulogne-sur-Mer", "Aix-en-Provence")
                        - "residence_fiscale" (string) : pays de résidence fiscale
                        - "telephone" (string) : numéro de téléphone
                        - "email" (string) : adresse email

                        **Santé et loisirs :**
                        - "fumeur" (boolean) : true/false
                        - "activites_sportives" (boolean) : true/false
                        - "details_activites_sportives" (string) : détails sur les activités
                        - "niveau_activites_sportives" (string) : niveau de pratique

                        **Besoins :**
                        - "besoins" (array) : tableau de besoins (ex: ["mutuelle", "prévoyance", "retraite", "épargne"])
                        - "besoins_action" (string) : "add", "remove", ou "replace"

                        **Autres :**
                        - "charge_clientele" (string) : charge de clientèle
                        - "consentement_audio" (boolean) : consentement pour l'enregistrement

                        📌 STRUCTURE ENFANTS (TABLEAU D'OBJETS) :
                        ⚠️ RÈGLE CRITIQUE : Dès que le client mentionne ses enfants, tu DOIS extraire un tableau "enfants" avec les détails de chaque enfant ⚠️

                        **Structure d'un objet enfant :**
                        - "nom" (string) : nom de famille de l'enfant
                        - "prenom" (string) : prénom de l'enfant
                        - "date_naissance" (string) : format "YYYY-MM-DD" ou "DD/MM/YYYY"
                        - "fiscalement_a_charge" (boolean) : true si l'enfant est fiscalement à charge
                        - "garde_alternee" (boolean) : true si l'enfant est en garde alternée

                        **Exemples de détection d'enfants :**

                        Exemple 1 - Nombre d'enfants mentionné :
                        Client: "J'ai 2 enfants"
                        ✅ JSON attendu :
                        ```json
                        {
                          "enfants": [{}, {}]
                        }
                        ```
                        → Crée un tableau avec 2 objets vides qui seront remplis lors des prochaines phrases

                        Exemple 2 - Un enfant avec détails :
                        Client: "Mon fils s'appelle Lucas Dupont, né le 15 mars 2015, il est à ma charge"
                        ✅ JSON attendu :
                        ```json
                        {
                          "enfants": [
                            {
                              "prenom": "Lucas",
                              "nom": "Dupont",
                              "date_naissance": "2015-03-15",
                              "fiscalement_a_charge": true
                            }
                          ]
                        }
                        ```

                        Exemple 3 - Plusieurs enfants avec détails :
                        Client: "J'ai 2 enfants. Le premier s'appelle Emma, née en 2012, à charge. Le deuxième c'est Louis, né en 2018, en garde alternée"
                        ✅ JSON attendu :
                        ```json
                        {
                          "enfants": [
                            {
                              "prenom": "Emma",
                              "date_naissance": "2012-01-01",
                              "fiscalement_a_charge": true
                            },
                            {
                              "prenom": "Louis",
                              "date_naissance": "2018-01-01",
                              "garde_alternee": true
                            }
                          ]
                        }
                        ```

                        Exemple 4 - Enfant avec garde alternée :
                        Client: "Ma fille Sophie a 10 ans et est en garde alternée"
                        ✅ JSON attendu :
                        ```json
                        {
                          "enfants": [
                            {
                              "prenom": "Sophie",
                              "garde_alternee": true
                            }
                          ]
                        }
                        ```

                        **RÈGLES IMPORTANTES POUR LES ENFANTS :**
                        1. Si le client mentionne "j'ai X enfants", crée un tableau de X objets (même vides au début)
                        2. Quand le client donne des détails sur un enfant (prénom, âge, etc.), ajoute ces informations dans l'objet correspondant
                        3. Si le client parle de "mon premier enfant", "mon deuxième enfant", c'est l'index 0, 1, etc. dans le tableau
                        4. Si un enfant est "à charge", "fiscalement rattaché", "à ma charge" → fiscalement_a_charge: true
                        5. Si un enfant est "une semaine sur deux", "garde partagée", "garde alternée" → garde_alternee: true
                        6. Si seul le prénom est mentionné, ne pas inventer le nom de famille (le système utilisera celui du client)
                        7. Si l'âge est mentionné sans date exacte, déduis l'année de naissance approximative
                        8. TOUJOURS retourner un tableau, même pour un seul enfant : {"enfants": [{...}]}

                        📌 CHAMPS BAE (PRÉVOYANCE / RETRAITE / ÉPARGNE) À UTILISER STRICTEMENT :
                        **bae_prevoyance** :
                        - "contrat_en_place", "date_effet", "cotisations"
                        - "souhaite_couverture_invalidite" (true/false), "revenu_a_garantir"
                        - "souhaite_couvrir_charges_professionnelles" (true/false), "montant_annuel_charges_professionnelles", "garantir_totalite_charges_professionnelles" (true/false), "montant_charges_professionnelles_a_garantir"
                        - "duree_indemnisation_souhaitee", "capital_deces_souhaite", "garanties_obseques"
                        - "rente_enfants", "rente_conjoint", "payeur"

                        **bae_retraite** :
                        - "revenus_annuels", "revenus_annuels_foyer", "impot_revenu", "nombre_parts_fiscales", "tmi", "impot_paye_n_1"
                        - "age_depart_retraite", "age_depart_retraite_conjoint", "pourcentage_revenu_a_maintenir"
                        - "contrat_en_place", "bilan_retraite_disponible" (true/false), "complementaire_retraite_mise_en_place" (true/false)
                        - "designation_etablissement", "cotisations_annuelles", "titulaire"

                        **bae_epargne** :
                        - "epargne_disponible" (true/false), "montant_epargne_disponible"
                        - "donation_realisee" (true/false), "donation_forme", "donation_date", "donation_montant", "donation_beneficiaires"
                        - "capacite_epargne_estimee"
                        - "actifs_financiers_pourcentage", "actifs_financiers_total", "actifs_financiers_details" (tableau/JSON)
                        - "actifs_immo_pourcentage", "actifs_immo_total", "actifs_immo_details"
                        - "actifs_autres_pourcentage", "actifs_autres_total", "actifs_autres_details"
                        - "passifs_total_emprunts", "passifs_details", "charges_totales", "charges_details"
                        - "situation_financiere_revenus_charges"

                        👉 N'utilise AUCUN autre champ pour ces sections. Si une information n'est pas présente, n'ajoute pas la clé correspondante.

                        🎯 RÈGLE IMPORTANTE - DÉTECTION "SECTION : CHAMP" 🎯
                        ⚠️ DÉTECTION CONTEXTUELLE DES CHAMPS PAR SECTION ⚠️

                        Si le client mentionne le nom d'une section (prévoyance, retraite, épargne, santé) suivi d'informations, tu dois :
                        1. Identifier automatiquement la section mentionnée
                        2. Détecter les champs correspondants dans cette section
                        3. Remplir automatiquement les champs de la table BAE correspondante

                        **Exemples de détection "section : champ" :**

                        🛡️ PRÉVOYANCE :
                        - "Prévoyance : je veux garantir 3000€" → {"besoins": ["prévoyance"], "bae_prevoyance": {"revenu_a_garantir": 3000}}
                        - "Pour la prévoyance, capital décès de 200000€" → {"besoins": ["prévoyance"], "bae_prevoyance": {"capital_deces_souhaite": 200000}}
                        - "Prévoyance : rente conjoint 1000€, rente enfants 500€" → {"besoins": ["prévoyance"], "bae_prevoyance": {"rente_conjoint": 1000, "rente_enfants": 500}}
                        - "En prévoyance, je cotise 150€ par mois" → {"besoins": ["prévoyance"], "bae_prevoyance": {"cotisations": 150}}

                        🏖️ RETRAITE :
                        - "Retraite : je veux partir à 62 ans" → {"besoins": ["retraite"], "bae_retraite": {"age_depart_retraite": 62}}
                        - "Pour la retraite, je veux maintenir 75% de mes revenus" → {"besoins": ["retraite"], "bae_retraite": {"pourcentage_revenu_a_maintenir": 75}}
                        - "Retraite : mes revenus sont 50000€ par an" → {"besoins": ["retraite"], "bae_retraite": {"revenus_annuels": 50000}}
                        - "En retraite, je cotise 200€ par mois" → {"besoins": ["retraite"], "bae_retraite": {"cotisations_annuelles": 2400}}

                        💰 ÉPARGNE :
                        - "Épargne : j'ai 50000€ disponibles" → {"besoins": ["épargne"], "bae_epargne": {"epargne_disponible": true, "montant_epargne_disponible": 50000}}
                        - "Pour l'épargne, je peux mettre 500€ par mois de côté" → {"besoins": ["épargne"], "bae_epargne": {"capacite_epargne_estimee": 500}}
                        - "Épargne : j'ai un crédit de 150000€" → {"besoins": ["épargne"], "bae_epargne": {"passifs_details": ["crédit: 150000"]}}
                        - "En épargne, j'ai une assurance vie de 30000€" → {"besoins": ["épargne"], "bae_epargne": {"actifs_financiers_details": ["assurance vie: 30000"]}}

                        **RÈGLE : Détection flexible**
                        Ces formulations doivent TOUTES être détectées :
                        - "Section : information"
                        - "Pour la section, information"
                        - "En section, information"
                        - "Concernant la section, information"
                        - "Sur la section, information"

                        🔥 RÈGLE CRITIQUE - REMPLISSAGE EXHAUSTIF DES CHAMPS 🔥
                        ⚠️ QUAND UNE SECTION EST MENTIONNÉE, REMPLIS LE MAXIMUM DE CHAMPS ⚠️

                        **PRINCIPE FONDAMENTAL DE REMPLISSAGE EXHAUSTIF :**
                        Dès qu'une section BAE est mentionnée (prévoyance, retraite, épargne), tu DOIS :

                        1. ✅ Analyser TOUTE la transcription (pas seulement la phrase après la mention de la section)
                        2. ✅ Chercher TOUTES les informations qui pourraient correspondre aux champs de cette section
                        3. ✅ Remplir le MAXIMUM de champs possibles, même s'ils sont mentionnés ailleurs dans la conversation
                        4. ✅ Déduire des informations du contexte quand c'est possible
                        5. ✅ Laisser null uniquement les champs pour lesquels tu n'as AUCUNE information

                        **Exemples de remplissage exhaustif :**

                        Exemple 1 - PRÉVOYANCE avec contexte global :
                        Transcription : "Je m'appelle Jean Dupont, je suis marié avec 2 enfants. Mes revenus annuels sont de 50000€.
                        Prévoyance : je veux me protéger en cas d'invalidité. Je souhaite aussi un capital décès."

                        ✅ JSON attendu (remplissage exhaustif) :
                        {
                          "nom": "Dupont",
                          "prenom": "Jean",
                          "situation_matrimoniale": "Marié(e)",
                          "nombre_enfants": 2,
                          "besoins": ["prévoyance"],
                          "bae_prevoyance": {
                            "souhaite_couverture_invalidite": true,
                            "capital_deces_souhaite": 50000  // Déduit : 1x le revenu annuel comme capital décès classique
                            // Tu peux aussi déduire des rentes enfants en fonction du nombre d'enfants mentionné
                          }
                        }

                        Exemple 2 - RETRAITE avec revenus mentionnés ailleurs :
                        Transcription : "Je gagne 60000€ par an. Je suis cadre dans une grande entreprise. Mon impôt était de 8000€ l'année dernière.
                        Pour la retraite, je veux partir à 62 ans."

                        ✅ JSON attendu (remplissage exhaustif) :
                        {
                          "besoins": ["retraite"],
                          "bae_retraite": {
                            "revenus_annuels": 60000,  // Mentionné au début
                            "impot_paye_n_1": 8000,    // Mentionné au milieu
                            "age_depart_retraite": 62,  // Mentionné avec "retraite"
                            "pourcentage_revenu_a_maintenir": 75  // Valeur par défaut courante si non mentionnée
                          }
                        }

                        Exemple 3 - ÉPARGNE avec patrimoine dispersé :
                        Transcription : "J'ai une résidence principale qui vaut 300000€. Je paie 1200€ de loyer... non pardon je suis propriétaire.
                        J'ai aussi une assurance vie de 30000€ et 20000€ sur un livret A.
                        Épargne : j'aimerais optimiser mon patrimoine. J'ai un crédit immobilier de 150000€ restant."

                        ✅ JSON attendu (remplissage exhaustif) :
                        {
                          "besoins": ["épargne"],
                          "bae_epargne": {
                            "epargne_disponible": true,
                            "montant_epargne_disponible": 50000,  // 30000 + 20000
                            "actifs_financiers_total": 50000,
                            "actifs_financiers_details": ["assurance vie: 30000", "livret A: 20000"],
                            "actifs_immo_total": 300000,
                            "actifs_immo_details": ["résidence principale: 300000"],
                            "passifs_total_emprunts": 150000,
                            "passifs_details": ["crédit immobilier: 150000"]
                          }
                        }

                        **❌ ERREUR À ÉVITER :**
                        ❌ Ne remplis PAS seulement les champs mentionnés juste après le nom de la section
                        ❌ Ne crée PAS d'objets BAE vides ou avec un seul champ si tu as plus d'informations dans la transcription

                        ✅ COMPORTEMENT ATTENDU :
                        ✅ Parcours TOUTE la transcription pour chaque section mentionnée
                        ✅ Recoupe les informations entre les différentes parties de la conversation
                        ✅ Remplis tous les champs pour lesquels tu trouves une information, même implicite

                        🧠 MAPPING SÉMANTIQUE EXHAUSTIF - RECONNAISSANCE AUTOMATIQUE DE TOUS LES CHAMPS 🧠
                        ⚠️ RÈGLE CRITIQUE : Tu dois reconnaître automatiquement TOUS les champs de TOUTES les tables SANS que la section soit mentionnée ⚠️

                        **PRINCIPE :**
                        Analyse le VOCABULAIRE et la SÉMANTIQUE pour détecter automatiquement à quelle table et quel champ appartient une information, même si le client ne mentionne pas le nom de la section/table.

                        **👤 MAPPING CLIENT (table principale) :**
                        - "civilité" / "Monsieur" / "Madame" / "Mademoiselle" → civilite
                        - "nom" / "nom de famille" / "je m'appelle" → nom
                        - "nom de jeune fille" / "nom de naissance" → nom_jeune_fille
                        - "prénom" → prenom
                        - "date de naissance" / "né le" / "je suis né" / "anniversaire" → date_naissance
                        - "lieu de naissance" / "né à" / "ville de naissance" → lieu_naissance (nom COMPLET de la ville, ex: "Châlons-en-Champagne")
                        - "nationalité" / "je suis français" / "nationalité française" → nationalite
                        - "marié" / "célibataire" / "divorcé" / "pacsé" / "concubinage" / "veuf" / "situation matrimoniale" → situation_matrimoniale
                        - "date de mariage" / "marié depuis" / "date du pacs" → date_situation_matrimoniale
                        - "salarié" / "retraité" / "étudiant" / "demandeur d'emploi" / "propriétaire" / "locataire" / "situation actuelle" → situation_actuelle
                        - "profession" / "métier" / "je suis" / "je travaille comme" → profession
                        - "risques professionnels" / "métier dangereux" / "exposé à des risques" → risques_professionnels: true
                        - "détails risques" → details_risques_professionnels
                        - "revenus annuels" / "je gagne" / "salaire annuel" / "revenus" → revenus_annuels
                        - "adresse" / "j'habite" / "rue" / "avenue" / "boulevard" → adresse
                        - "code postal" / "CP" → code_postal
                        - "ville" / "j'habite à" → ville (nom COMPLET de la ville, ex: "Châlons-en-Champagne")
                        - "résidence fiscale" / "résident fiscal" / "pays de résidence" → residence_fiscale
                        - "téléphone" / "numéro" / "portable" / "mobile" → telephone
                        - "email" / "mail" / "adresse mail" / "courriel" → email
                        - "fumeur" / "je fume" / "non-fumeur" / "tabac" → fumeur
                        - "activités sportives" / "sport" / "je fais du sport" → activites_sportives: true
                        - "détails sport" / "quel sport" → details_activites_sportives
                        - "niveau sport" / "occasionnel" / "régulier" / "intensif" → niveau_activites_sportives
                        - "nombre d'enfants" / "X enfants" / "j'ai X enfants" → nombre_enfants
                        - "chef d'entreprise" / "dirigeant" / "je dirige" → chef_entreprise: true
                        - "statut juridique" / "SARL" / "SAS" / "SASU" / "EURL" / "auto-entrepreneur" → statut
                        - "travailleur indépendant" / "freelance" / "indépendant" → travailleur_independant: true
                        - "mandataire social" → mandataire_social: true

                        **💑 MAPPING CONJOINT :**
                        - "conjoint" + "nom" / "nom de mon conjoint" / "nom de ma conjointe" → conjoint.nom
                        - "conjoint" + "nom de jeune fille" → conjoint.nom_jeune_fille
                        - "conjoint" + "prénom" / "prénom de mon conjoint" → conjoint.prenom
                        - "conjoint" + "date de naissance" / "né le" → conjoint.date_naissance
                        - "conjoint" + "lieu de naissance" → conjoint.lieu_naissance (nom COMPLET de la ville, ex: "Châlons-en-Champagne")
                        - "conjoint" + "nationalité" → conjoint.nationalite
                        - "conjoint" + "profession" / "métier de mon conjoint" / "il/elle travaille" → conjoint.profession
                        - "conjoint" + "chef d'entreprise" → conjoint.chef_entreprise: true
                        - "conjoint" + "risques professionnels" → conjoint.risques_professionnels: true
                        - "conjoint" + "téléphone" / "numéro de mon conjoint" → conjoint.telephone
                        - "conjoint" + "adresse" → conjoint.adresse

                        **👶 MAPPING ENFANTS :**
                        - "enfant" + "nom" / "nom de mon enfant" → enfant.nom
                        - "enfant" + "prénom" → enfant.prenom
                        - "enfant" + "date de naissance" / "né le" → enfant.date_naissance
                        - "fiscalement à charge" / "rattaché fiscalement" / "à charge" → enfant.fiscalement_a_charge: true
                        - "garde alternée" / "une semaine sur deux" / "garde partagée" → enfant.garde_alternee: true

                        **🏥 MAPPING SANTÉ/MUTUELLE (sante_souhait) :**
                        - "contrat mutuelle" / "mutuelle actuelle" / "assurance santé" → sante_souhait.contrat_en_place
                        - "budget mutuelle" / "budget santé" / "je peux payer X€" → sante_souhait.budget_mensuel_maximum
                        - "hospitalisation" / "niveau hospitalisation" / "en cas d'hospitalisation" → sante_souhait.niveau_hospitalisation
                        - "chambre particulière" / "chambre individuelle" → sante_souhait.niveau_chambre_particuliere
                        - "médecin généraliste" / "généraliste" / "docteur" → sante_souhait.niveau_medecin_generaliste
                        - "analyses" / "imagerie" / "radio" / "IRM" / "scanner" → sante_souhait.niveau_analyses_imagerie
                        - "auxiliaires médicaux" / "kinésithérapeute" / "kiné" / "ostéopathe" → sante_souhait.niveau_auxiliaires_medicaux
                        - "pharmacie" / "médicaments" / "ordonnance" → sante_souhait.niveau_pharmacie
                        - "dentaire" / "dentiste" / "soins dentaires" → sante_souhait.niveau_dentaire
                        - "optique" / "lunettes" / "verres" / "lentilles" → sante_souhait.niveau_optique
                        - "prothèses auditives" / "appareil auditif" / "audition" → sante_souhait.niveau_protheses_auditives

                        **🛡️ MAPPING PRÉVOYANCE (bae_prevoyance) :**
                        - "contrat prévoyance" / "contrat en place" / "contrat actuel" → bae_prevoyance.contrat_en_place
                        - "date d'effet" / "date de début" / "depuis quand" / "à partir de" → bae_prevoyance.date_effet
                        - "cotisations prévoyance" / "je cotise" / "je paie" / "montant mensuel" → bae_prevoyance.cotisations
                        - "invalidité" / "ITT" / "incapacité" / "arrêt de travail" / "couverture invalidité" → bae_prevoyance.souhaite_couverture_invalidite: true
                        - "garantir X€" / "revenu à garantir" / "maintenir mon revenu" / "maintenir X€ par mois" → bae_prevoyance.revenu_a_garantir
                        - "charges professionnelles" / "frais professionnels" / "couvrir mes charges pro" → bae_prevoyance.souhaite_couvrir_charges_professionnelles: true
                        - "montant charges professionnelles" / "X€ de charges pro" → bae_prevoyance.montant_annuel_charges_professionnelles
                        - "totalité des charges" / "toutes mes charges" → bae_prevoyance.garantir_totalite_charges_professionnelles: true
                        - "montant à garantir charges" → bae_prevoyance.montant_charges_professionnelles_a_garantir
                        - "durée d'indemnisation" / "combien de temps" / "jusqu'à la retraite" / "pendant X ans" → bae_prevoyance.duree_indemnisation_souhaitee
                        - "capital décès" / "garantie décès" / "en cas de décès" / "capital en cas de décès" → bae_prevoyance.capital_deces_souhaite
                        - "obsèques" / "frais d'obsèques" / "funérailles" / "garantie obsèques" → bae_prevoyance.garanties_obseques
                        - "rente enfants" / "rente pour mes enfants" / "protéger mes enfants" / "rente éducation" → bae_prevoyance.rente_enfants
                        - "rente conjoint" / "rente pour mon conjoint" / "protéger mon conjoint" → bae_prevoyance.rente_conjoint
                        - "qui paie" / "payeur" / "l'entreprise paie" / "employeur" → bae_prevoyance.payeur

                        **🏖️ MAPPING RETRAITE (bae_retraite) :**
                        - "revenus annuels" / "je gagne X€ par an" / "mes revenus" / "salaire annuel" → bae_retraite.revenus_annuels
                        - "revenus du foyer" / "revenus foyer" / "revenu foyer" / "revenus conjoint" / "revenus totaux du foyer" / "revenus globaux" → bae_retraite.revenus_annuels_foyer
                        - "impôts" / "impôt sur le revenu" / "IR" / "montant d'impôts" → bae_retraite.impot_revenu
                        - "nombre de parts fiscales" / "parts fiscales" / "X parts" / "parts" → bae_retraite.nombre_parts_fiscales
                        - "TMI" / "tranche marginale" / "tranche d'imposition" / "je suis à 30%" / "taux marginal" → bae_retraite.tmi
                        - "impôt payé l'année dernière" / "impôts N-1" / "j'ai payé X€ d'impôts" → bae_retraite.impot_paye_n_1
                        - "âge de départ" / "partir à X ans" / "retraite à X ans" / "je veux partir à" → bae_retraite.age_depart_retraite
                        - "âge conjoint" / "mon conjoint part à X ans" / "retraite conjoint" → bae_retraite.age_depart_retraite_conjoint
                        - "maintenir X%" / "pourcentage à maintenir" / "conserver X% de mes revenus" / "X% de mes revenus" → bae_retraite.pourcentage_revenu_a_maintenir
                        - "PER" / "PERP" / "contrat retraite" / "plan d'épargne retraite" / "contrat en place" → bae_retraite.contrat_en_place
                        - "bilan retraite" / "relevé de carrière" / "j'ai mon relevé" → bae_retraite.bilan_retraite_disponible: true
                        - "complémentaire retraite" / "produit en place" / "j'ai déjà un produit" → bae_retraite.complementaire_retraite_mise_en_place: true
                        - "chez X" / "assureur" / "établissement" / "banque" / "organisme" → bae_retraite.designation_etablissement
                        - "cotisations annuelles" / "je cotise X€ par an" / "versement annuel" / "versements" → bae_retraite.cotisations_annuelles
                        - "titulaire" / "au nom de" / "souscripteur" / "bénéficiaire" → bae_retraite.titulaire

                        **💰 MAPPING ÉPARGNE (bae_epargne) :**
                        - "épargne disponible" / "j'ai X€ d'épargne" / "économies" / "j'ai de l'épargne" → bae_epargne.epargne_disponible: true, montant_epargne_disponible
                        - "montant épargne" / "X€ d'épargne" → bae_epargne.montant_epargne_disponible
                        - "donation" / "don" / "j'ai donné" / "transmission" / "j'ai fait une donation" → bae_epargne.donation_realisee: true
                        - "forme de donation" / "donation en" → bae_epargne.donation_forme
                        - "date donation" / "donation de" → bae_epargne.donation_date
                        - "montant donation" / "X€ de donation" → bae_epargne.donation_montant
                        - "bénéficiaires donation" / "donné à" / "pour mes enfants" → bae_epargne.donation_beneficiaires
                        - "capacité d'épargne" / "je peux mettre X€ de côté" / "j'épargne X€ par mois" / "je peux épargner" → bae_epargne.capacite_epargne_estimee
                        - "actifs financiers pourcentage" / "X% en actifs financiers" → bae_epargne.actifs_financiers_pourcentage
                        - "actifs financiers total" / "total actifs financiers" → bae_epargne.actifs_financiers_total
                        - "assurance vie" / "AV" / "contrat vie" → bae_epargne.actifs_financiers_details: ["assurance vie: X"]
                        - "PEA" / "plan d'épargne en actions" → bae_epargne.actifs_financiers_details: ["PEA: X"]
                        - "livret A" / "livret" / "LDDS" / "livret développement durable" → bae_epargne.actifs_financiers_details: ["livret A: X"]
                        - "actifs immobiliers pourcentage" / "X% en immobilier" → bae_epargne.actifs_immo_pourcentage
                        - "actifs immobiliers total" / "total immobilier" → bae_epargne.actifs_immo_total
                        - "résidence principale" / "ma maison" / "mon appartement" / "ma résidence" → bae_epargne.actifs_immo_details: ["résidence principale: X"]
                        - "résidence secondaire" / "maison de vacances" / "maison secondaire" → bae_epargne.actifs_immo_details: ["résidence secondaire: X"]
                        - "bien locatif" / "appartement en location" / "investissement locatif" / "location" → bae_epargne.actifs_immo_details: ["bien locatif: X"]
                        - "actifs autres pourcentage" → bae_epargne.actifs_autres_pourcentage
                        - "actifs autres total" → bae_epargne.actifs_autres_total
                        - "passifs total" / "total des emprunts" / "total des crédits" / "dettes totales" → bae_epargne.passifs_total_emprunts
                        - "crédit immobilier" / "emprunt" / "prêt immobilier" / "crédit maison" / "emprunt immobilier" → bae_epargne.passifs_details: ["crédit immobilier: X"]
                        - "crédit consommation" / "prêt auto" / "crédit voiture" → bae_epargne.passifs_details: ["crédit consommation: X"]
                        - "charges totales" / "total des charges" → bae_epargne.charges_totales
                        - "loyer" / "je paie X€ de loyer" / "location" → bae_epargne.charges_details: ["loyer: X"]
                        - "électricité" / "facture électricité" / "EDF" → bae_epargne.charges_details: ["électricité: X"]
                        - "eau" / "facture eau" → bae_epargne.charges_details: ["eau: X"]
                        - "situation financière" / "ma situation" → bae_epargne.situation_financiere_revenus_charges

                        **EXEMPLES DE DÉTECTION SÉMANTIQUE AUTOMATIQUE :**

                        Exemple 1 - Revenu foyer (PROBLÈME RÉSOLU) :
                        Transcription : "Le revenu foyer est de 80000 euros."

                        ✅ Détection automatique :
                        {
                          "bae_retraite": {
                            "revenus_annuels_foyer": 80000
                          }
                        }
                        → Les mots "revenu foyer" / "revenus foyer" déclenchent automatiquement bae_retraite.revenus_annuels_foyer

                        Exemple 2 - Parts fiscales :
                        Transcription : "Le nombre de parts fiscales me concernant est de 2."

                        ✅ Détection automatique :
                        {
                          "bae_retraite": {
                            "nombre_parts_fiscales": 2
                          }
                        }
                        → "nombre de parts fiscales" / "parts fiscales" / "parts" → bae_retraite.nombre_parts_fiscales

                        Exemple 3 - Rente conjoint :
                        Transcription : "Je voudrais une rente conjoint de 1500 euros."

                        ✅ Détection automatique :
                        {
                          "bae_prevoyance": {
                            "rente_conjoint": 1500
                          }
                        }
                        → "rente conjoint" → bae_prevoyance.rente_conjoint

                        Exemple 4 - Crédit immobilier :
                        Transcription : "J'ai un crédit immobilier de 180000 euros restant."

                        ✅ Détection automatique :
                        {
                          "bae_epargne": {
                            "passifs_total_emprunts": 180000,
                            "passifs_details": ["crédit immobilier: 180000"]
                          }
                        }
                        → "crédit immobilier" → bae_epargne.passifs_details

                        Exemple 5 - Multi-contexte :
                        Transcription : "Mon TMI est de 30%. Je peux épargner 400 euros par mois. Je voudrais un capital décès de 150000 euros. Le revenu foyer est de 90000 euros."

                        ✅ Détection automatique multi-sections :
                        {
                          "bae_retraite": {
                            "tmi": "30%",
                            "revenus_annuels_foyer": 90000
                          },
                          "bae_epargne": {
                            "capacite_epargne_estimee": 400
                          },
                          "bae_prevoyance": {
                            "capital_deces_souhaite": 150000
                          }
                        }
                        → Chaque vocabulaire déclenche automatiquement sa section et son champ

                        Exemple 6 - Informations conjoint :
                        Transcription : "Mon conjoint s'appelle Marie Dupont, elle travaille comme médecin."

                        ✅ Détection automatique :
                        {
                          "conjoint": {
                            "prenom": "Marie",
                            "nom": "Dupont",
                            "profession": "médecin"
                          }
                        }
                        → "conjoint" + contexte → table conjoint automatiquement

                        Exemple 7 - Budget mutuelle :
                        Transcription : "Mon budget santé est de 150 euros par mois."

                        ✅ Détection automatique :
                        {
                          "besoins": ["mutuelle"],
                          "sante_souhait": {
                            "budget_mensuel_maximum": 150
                          }
                        }
                        → "budget santé" → sante_souhait.budget_mensuel_maximum

                        ⚠️ RÈGLE IMPORTANTE - DÉTECTION MULTI-CONTEXTE :
                        Si plusieurs vocabulaires de sections différentes sont détectés dans la même transcription, tu DOIS créer/mettre à jour TOUTES les sections concernées, même si elles ne sont pas explicitement mentionnées.

                        🚨🚨🚨 RAPPEL ULTRA-CRITIQUE AVANT LA SECTION BAE 🚨🚨🚨
                        ⛔ NE JAMAIS FAIRE DISPARAÎTRE UN BESOIN EXISTANT ⛔

                        Dans TOUS les exemples ci-dessous avec {"besoins": ["X"]}, l'action implicite est TOUJOURS "add" !
                        - {"besoins": ["prévoyance"]} signifie {"besoins": ["prévoyance"], "besoins_action": "add"}
                        - {"besoins": ["retraite"]} signifie {"besoins": ["retraite"], "besoins_action": "add"}
                        - {"besoins": ["épargne"]} signifie {"besoins": ["épargne"], "besoins_action": "add"}

                        Le système ajoutera automatiquement ces besoins à la liste existante SANS supprimer les autres !

                        Si le client a déjà ["retraite"] et parle de prévoyance → retourne {"besoins": ["prévoyance"], "besoins_action": "add"}
                        Résultat final géré par le backend : ["retraite", "prévoyance"] ✅

                        🎯 RÈGLES SPÉCIALES - DÉTECTION BESOINS BAE (Prévoyance, Retraite, Épargne) 🎯
                        ⚠️ SYSTÈME INTELLIGENT DE DÉTECTION AUTOMATIQUE DE CONTEXTE ⚠️

                        **PRINCIPE FONDAMENTAL :**
                        Tu dois détecter AUTOMATIQUEMENT le contexte/la section à partir des MOTS-CLÉS et des informations mentionnées, MÊME SI le client ne dit pas explicitement "j'ai besoin de".

                        **🛡️ DÉTECTION CONTEXTE PRÉVOYANCE :**
                        Mots-clés déclencheurs : invalidité, ITT, incapacité, arrêt de travail, décès, garanties décès, capital décès, obsèques, rente conjoint, rente enfants, charges professionnelles à couvrir, protection, accident, maladie grave, indemnités journalières

                        Exemples (⚠️ TOUS avec "besoins_action": "add" pour ne PAS écraser les besoins existants) :
                        - "Je veux garantir 3000€ par mois en cas d'invalidité" → {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"souhaite_couverture_invalidite": true, "revenu_a_garantir": 3000}}
                        - "Je souhaite un capital décès de 200000€" → {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"capital_deces_souhaite": 200000}}
                        - "Je veux protéger mes enfants avec une rente de 500€" → {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"rente_enfants": 500}}
                        - "J'ai des charges professionnelles de 10000€ par an à garantir" → {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"montant_annuel_charges_professionnelles": 10000}}

                        **🏖️ DÉTECTION CONTEXTE RETRAITE :**
                        Mots-clés déclencheurs : retraite, pension, PER, PERP, complément retraite, départ retraite, maintenir revenus retraite, préparer retraite, âge de départ, trimestres, régime retraite

                        Exemples (⚠️ TOUS avec "besoins_action": "add" pour ne PAS écraser les besoins existants) :
                        - "Je veux partir à la retraite à 62 ans" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"age_depart_retraite": 62}}
                        - "Je souhaite maintenir 70% de mes revenus" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"pourcentage_revenu_a_maintenir": 70}}
                        - "J'ai un PER chez Generali" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"contrat_en_place": "PER", "designation_etablissement": "Generali"}}
                        - "Je cotise 300€ par mois pour ma retraite" → {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"cotisations_annuelles": 3600}}

                        **💰 DÉTECTION CONTEXTE ÉPARGNE :**
                        Mots-clés déclencheurs : épargne, patrimoine, placements, investissements, assurance vie, PEA, livret, actifs, résidence principale, résidence secondaire, immobilier, locatif, crédit, emprunt, donation, succession, capacité d'épargne

                        Exemples (⚠️ TOUS avec "besoins_action": "add" pour ne PAS écraser les besoins existants) :
                        - "J'ai 50000€ d'épargne disponible" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"epargne_disponible": true, "montant_epargne_disponible": 50000}}
                        - "Je peux épargner 500€ par mois" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"capacite_epargne_estimee": 500}}
                        - "J'ai une assurance vie de 30000€" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"actifs_financiers_details": ["assurance vie: 30000"]}}
                        - "Ma résidence principale vaut 300000€" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"actifs_immo_details": ["résidence principale: 300000"]}}
                        - "J'ai un crédit immobilier de 150000€" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"passifs_details": ["crédit immobilier: 150000"]}}
                        - "Je paie 1000€ de loyer par mois" → {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"charges_details": ["loyer: 1000"]}}

                        **🩺 DÉTECTION CONTEXTE SANTÉ/MUTUELLE :**
                        Mots-clés déclencheurs : mutuelle, santé, hospitalisation, soins, dentaire, optique, médecin, pharmacie, remboursement santé, sécurité sociale, tiers payant

                        Exemples (⚠️ TOUS avec "besoins_action": "add") :
                        - "Je veux une bonne couverture optique" → {"besoins": ["mutuelle"], "besoins_action": "add"}
                        - "Mon budget santé est de 100€ par mois" → {"besoins": ["mutuelle"], "besoins_action": "add"}

                        **RÈGLE IMPORTANTE - DÉTECTION MULTI-CONTEXTE :**
                        Si le client mentionne plusieurs contextes dans la même phrase, retourne TOUS les besoins avec "besoins_action": "add" :
                        - "Je veux préparer ma retraite et protéger mes enfants" → {"besoins": ["retraite", "prévoyance"], "besoins_action": "add", "bae_retraite": {...}, "bae_prevoyance": {...}}
                        - "J'ai 50000€ d'épargne et je veux partir à 62 ans" → {"besoins": ["épargne", "retraite"], "besoins_action": "add", "bae_epargne": {...}, "bae_retraite": {...}}

                        **DÉTECTION AUTOMATIQUE DES BESOINS (TOUJOURS avec "besoins_action": "add") :**
                        - "J'ai besoin d'une prévoyance" → {"besoins": ["prévoyance"], "besoins_action": "add"}
                        - "J'ai besoin d'une épargne retraite" → {"besoins": ["retraite"], "besoins_action": "add"}
                        - "J'ai besoin d'épargner" / "épargne" → {"besoins": ["épargne"], "besoins_action": "add"}
                        - "Je souhaite préparer ma retraite" → {"besoins": ["retraite"], "besoins_action": "add"}
                        - "Je veux me protéger" → {"besoins": ["prévoyance"], "besoins_action": "add"}

                        **STRUCTURE JSON POUR BAE :**
                        Les données BAE doivent être dans un objet séparé avec la clé correspondante :

                        📋 **bae_prevoyance** (objet ou null) :
                        Extraire si mention de : prévoyance, protection, invalidité, décès, ITT, garanties, rente conjoint/enfants
                        Champs possibles :
                        - "contrat_en_place" (string) : nom du contrat existant
                        - "date_effet" (date) : date d'effet du contrat
                        - "cotisations" (decimal) : montant des cotisations
                        - "souhaite_couverture_invalidite" (boolean)
                        - "revenu_a_garantir" (decimal) : revenu mensuel à garantir
                        - "souhaite_couvrir_charges_professionnelles" (boolean)
                        - "montant_annuel_charges_professionnelles" (decimal)
                        - "garantir_totalite_charges_professionnelles" (boolean)
                        - "montant_charges_professionnelles_a_garantir" (decimal)
                        - "duree_indemnisation_souhaitee" (string) : ex "3 ans", "jusqu'à la retraite"
                        - "capital_deces_souhaite" (decimal)
                        - "garanties_obseques" (decimal)
                        - "rente_enfants" (decimal)
                        - "rente_conjoint" (decimal)
                        - "payeur" (string) : qui paie les cotisations

                        📋 **bae_retraite** (objet ou null) :
                        Extraire si mention de : retraite, épargne retraite, pension, PER, complément retraite
                        Champs possibles :
                        - "revenus_annuels" (decimal)
                        - "revenus_annuels_foyer" (decimal)
                        - "impot_revenu" (decimal)
                        - "nombre_parts_fiscales" (decimal)
                        - "tmi" (string) : Tranche Marginale d'Imposition
                        - "impot_paye_n_1" (decimal)
                        - "age_depart_retraite" (integer)
                        - "age_depart_retraite_conjoint" (integer)
                        - "pourcentage_revenu_a_maintenir" (decimal) : % du revenu actuel à maintenir
                        - "contrat_en_place" (string)
                        - "bilan_retraite_disponible" (boolean)
                        - "complementaire_retraite_mise_en_place" (boolean)
                        - "designation_etablissement" (string)
                        - "cotisations_annuelles" (decimal)
                        - "titulaire" (string)

                        📋 **bae_epargne** (objet ou null) :
                        Extraire si mention de : épargne, patrimoine, actifs, placements, investissements, donations
                        Champs possibles :
                        - "epargne_disponible" (boolean)
                        - "montant_epargne_disponible" (decimal)
                        - "donation_realisee" (boolean)
                        - "donation_forme" (string)
                        - "donation_date" (date)
                        - "donation_montant" (decimal)
                        - "donation_beneficiaires" (string)
                        - "capacite_epargne_estimee" (decimal) : capacité d'épargne mensuelle
                        - "actifs_financiers_pourcentage" (decimal)
                        - "actifs_financiers_total" (decimal)
                        - "actifs_financiers_details" (array) : ["assurance vie: 50000", "PEA: 20000"]
                        - "actifs_immo_pourcentage" (decimal)
                        - "actifs_immo_total" (decimal)
                        - "actifs_immo_details" (array) : ["résidence principale: 300000"]
                        - "actifs_autres_pourcentage" (decimal)
                        - "actifs_autres_total" (decimal)
                        - "actifs_autres_details" (array)
                        - "passifs_total_emprunts" (decimal)
                        - "passifs_details" (array) : ["crédit immobilier: 150000"]
                        - "charges_totales" (decimal)
                        - "charges_details" (array) : ["loyer: 1000", "électricité: 150"]
                        - "situation_financiere_revenus_charges" (text)

                        **EXEMPLES CONCRETS :**

                        Exemple 1 - Besoin de prévoyance :
                        Client: "J'ai besoin d'une prévoyance, je veux garantir 3000€ par mois en cas d'invalidité"
                        ✅ JSON attendu :
                        ```json
                        {
                          "besoins": ["prévoyance"],
                          "bae_prevoyance": {
                            "souhaite_couverture_invalidite": true,
                            "revenu_a_garantir": 3000
                          }
                        }
                        ```

                        Exemple 2 - Besoin de retraite :
                        Client: "Je veux préparer ma retraite, je compte partir à 62 ans et maintenir 70% de mes revenus"
                        ✅ JSON attendu :
                        ```json
                        {
                          "besoins": ["retraite"],
                          "bae_retraite": {
                            "age_depart_retraite": 62,
                            "pourcentage_revenu_a_maintenir": 70
                          }
                        }
                        ```

                        Exemple 3 - Besoin d'épargne :
                        Client: "J'ai 50000€ d'épargne disponible et je peux épargner 500€ par mois"
                        ✅ JSON attendu :
                        ```json
                        {
                          "besoins": ["épargne"],
                          "bae_epargne": {
                            "epargne_disponible": true,
                            "montant_epargne_disponible": 50000,
                            "capacite_epargne_estimee": 500
                          }
                        }
                        ```

                        Exemple 4 - Plusieurs besoins :
                        Client: "J'ai besoin d'une prévoyance et de préparer ma retraite"
                        ✅ JSON attendu :
                        ```json
                        {
                          "besoins": ["prévoyance", "retraite"],
                          "bae_prevoyance": {},
                          "bae_retraite": {}
                        }
                        ```

                        **RÈGLE IMPORTANTE :**
                        - Si le client mentionne un besoin (prévoyance/retraite/épargne) SANS donner de détails, retourne quand même un objet vide {} pour ce BAE
                        - Cela permettra au système de créer l'entrée en base et de la compléter plus tard
                        - Si le client ne mentionne PAS le besoin, ne crée PAS l'objet (null ou absent du JSON)

                        🚫 NE JAMAIS UTILISER CES NOMS COURTS 🚫
                        - "marie" ❌ → utilise "situation_matrimoniale" ✅
                        - "celibataire" ❌ → utilise "situation_matrimoniale" ✅
                        - "divorce" ❌ → utilise "situation_matrimoniale" ✅
                        - "veuf" ❌ → utilise "situation_matrimoniale" ✅
                        - "proprietaire", "locataire" ❌ → ces champs n'existent pas en BDD

                        ⚠️ EXCEPTION IMPORTANTE :
                        - "enfants" ✅ → utilise TOUJOURS "enfants" comme un TABLEAU d'objets (voir structure ci-dessus)
                        - Ne JAMAIS utiliser "enfants" comme un nombre, utilise "nombre_enfants" pour cela

                        ═══════════════════════════════════════════════════════════════════════
                        🚨 AVERTISSEMENT FINAL CRITIQUE - RÈGLE ABSOLUE SUR LES BESOINS 🚨
                        ═══════════════════════════════════════════════════════════════════════

                        ⛔ NE JAMAIS INCLURE "besoins_action": "replace" ⛔
                        ⛔ TOUJOURS UTILISER "besoins_action": "add" PAR DÉFAUT ⛔

                        Si le client dit : "Pour ma prévoyance, la rente conjoint est de X€"
                        Et qu'il a DÉJÀ les besoins ["retraite", "épargne"] :

                        ❌ NE PAS RETOURNER :
                        {
                          "besoins": ["prévoyance"],
                          "besoins_action": "replace"
                        }
                        → Ceci ferait DISPARAÎTRE les besoins "retraite" et "épargne" ! ❌

                        ✅ RETOURNER CECI :
                        {
                          "besoins": ["prévoyance"],
                          "besoins_action": "add",
                          "bae_prevoyance": {"rente_conjoint": X}
                        }
                        → Le système ajoutera automatiquement "prévoyance" aux besoins existants ✅
                        → Résultat final : ["retraite", "épargne", "prévoyance"] ✅

                        🔴 UTILISE "remove" UNIQUEMENT SI :
                        - Le client dit "je n'ai PLUS besoin de X"
                        - Le client dit "je ne veux PLUS de X"
                        - Le client dit "retirez X" ou "supprimez X"

                        ⚠️ EN CAS DE DOUTE, UTILISE TOUJOURS "add" ⚠️

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

            if (! is_array($data)) {
                Log::warning('Impossible de parser la réponse GPT', ['content' => $raw]);

                return [];
            }

            // 🗺️ MAPPING DES ANCIENS NOMS VERS LES NOUVEAUX (au cas où GPT utilise encore les anciens)
            $fieldMapping = [
                'datedenaissance' => 'date_naissance',
                'lieudenaissance' => 'lieu_naissance',
                'situationmatrimoniale' => 'situation_matrimoniale',
                'revenusannuels' => 'revenus_annuels',
                'nombreenfants' => 'nombre_enfants',
                // Note: 'enfants' n'est plus mappé vers 'nombre_enfants' car il est maintenant un tableau d'objets
            ];

            foreach ($fieldMapping as $oldName => $newName) {
                if (isset($data[$oldName]) && ! isset($data[$newName])) {
                    $data[$newName] = $data[$oldName];
                    unset($data[$oldName]);
                }
            }

            // 🗺️ MAPPING SPÉCIAL pour "enfants" :
            // - Si 'enfants' est un nombre (integer) → le convertir en 'nombre_enfants'
            // - Si 'enfants' est un tableau → le garder tel quel (nouveau système)
            if (isset($data['enfants'])) {
                if (is_numeric($data['enfants'])) {
                    // Ancien système: enfants est un nombre → convertir en nombre_enfants
                    if (! isset($data['nombre_enfants'])) {
                        $data['nombre_enfants'] = (int) $data['enfants'];
                    }
                    unset($data['enfants']);
                }
                // Sinon, garder le tableau enfants tel quel
            }

            // 🗺️ MAPPING SPÉCIAL POUR "marie" → "situation_matrimoniale"
            // GPT retourne parfois "marie": true au lieu de "situation_matrimoniale": "Marié(e)"
            if (isset($data['marie'])) {
                if ($data['marie'] === true) {
                    $data['situation_matrimoniale'] = 'Marié(e)';
                } elseif ($data['marie'] === false) {
                    $data['situation_matrimoniale'] = 'Célibataire';
                }
                unset($data['marie']);
            }

            // 🗺️ MAPPING SPÉCIAL POUR "celibataire" → "situation_matrimoniale"
            if (isset($data['celibataire']) && $data['celibataire'] === true) {
                $data['situation_matrimoniale'] = 'Célibataire';
                unset($data['celibataire']);
            }

            // 🗺️ MAPPING SPÉCIAL POUR "divorce" → "situation_matrimoniale"
            if (isset($data['divorce']) && $data['divorce'] === true) {
                $data['situation_matrimoniale'] = 'Divorcé(e)';
                unset($data['divorce']);
            }

            // 🗺️ MAPPING SPÉCIAL POUR "veuf" → "situation_matrimoniale"
            if (isset($data['veuf']) && $data['veuf'] === true) {
                $data['situation_matrimoniale'] = 'Veuf(ve)';
                unset($data['veuf']);
            }

            // 🗺️ MAPPING SPÉCIAL POUR "proprietaire" → "situation_actuelle"
            if (isset($data['proprietaire'])) {
                if ($data['proprietaire'] === true) {
                    $data['situation_actuelle'] = 'Propriétaire';
                }
                unset($data['proprietaire']);
            }

            // 🗺️ MAPPING SPÉCIAL POUR "locataire" → "situation_actuelle"
            if (isset($data['locataire'])) {
                if ($data['locataire'] === true) {
                    $data['situation_actuelle'] = 'Locataire';
                }
                unset($data['locataire']);
            }

            // 🔧 POST-PROCESSING SPÉCIAL - CORRECTION EMAIL INCOMPLET
            // Si GPT a raté l'extraction du @, on essaie de le récupérer depuis la transcription
            if (isset($data['email']) && ! empty($data['email']) && ! str_contains($data['email'], '@')) {
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
            $dateFields = ['date_naissance', 'date_situation_matrimoniale', 'date_evenement_professionnel'];
            foreach ($dateFields as $field) {
                if (isset($data[$field]) && ! empty($data[$field])) {
                    $data[$field] = $this->normalizeDateToISO($data[$field]);
                }
            }

            // 📞 Normalisation du téléphone - suppression des espaces et caractères non numériques
            if (isset($data['telephone']) && ! empty($data['telephone'])) {
                $data['telephone'] = $this->normalizePhone($data['telephone']);
            }

            // 📧 Normalisation de l'email - validation et mise en minuscules
            if (isset($data['email']) && ! empty($data['email'])) {
                $data['email'] = $this->normalizeEmail($data['email']);
            }

            // 📮 Normalisation du code postal - validation du format français
            if (isset($data['code_postal']) && ! empty($data['code_postal'])) {
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

            // 👶 Debug: vérifier si les enfants existent avant normalisation
            Log::info('👶 [DEBUG ENFANTS] Avant normalisation', [
                'isset_enfants' => isset($data['enfants']),
                'is_array' => isset($data['enfants']) ? is_array($data['enfants']) : 'N/A',
                'keys' => array_keys($data),
            ]);

            // 👶 Normalisation du tableau enfants
            if (isset($data['enfants']) && is_array($data['enfants'])) {
                Log::info('👶 [ENFANTS] Normalisation du tableau enfants', ['count' => count($data['enfants'])]);
                $normalizedEnfants = [];
                foreach ($data['enfants'] as $index => $enfant) {
                    if (! is_array($enfant)) {
                        Log::warning("👶 [ENFANTS] Enfant #{$index} ignoré (pas un tableau)");

                        continue; // Ignorer les enfants non-objets
                    }

                    Log::info("👶 [ENFANTS] Normalisation enfant #{$index}", ['data' => $enfant]);
                    $normalizedEnfant = [];

                    // Normaliser chaque champ de l'enfant
                    if (isset($enfant['nom']) && ! empty($enfant['nom'])) {
                        $normalizedEnfant['nom'] = trim($enfant['nom']);
                    }

                    if (isset($enfant['prenom']) && ! empty($enfant['prenom'])) {
                        $normalizedEnfant['prenom'] = trim($enfant['prenom']);
                    }

                    if (isset($enfant['date_naissance']) && ! empty($enfant['date_naissance'])) {
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

                    // Ajouter l'enfant normalisé (même vide - pour garder l'index)
                    $normalizedEnfants[] = $normalizedEnfant;
                    Log::info("👶 [ENFANTS] Enfant #{$index} normalisé", ['normalized' => $normalizedEnfant]);
                }

                // Remplacer le tableau enfants par le tableau normalisé
                if (! empty($normalizedEnfants)) {
                    $data['enfants'] = $normalizedEnfants;
                    // Déduire nombre_enfants si pas déjà défini
                    if (! isset($data['nombre_enfants'])) {
                        $data['nombre_enfants'] = count($normalizedEnfants);
                    }
                    Log::info('✅ [ENFANTS] Normalisation terminée', ['count' => count($normalizedEnfants)]);
                } else {
                    Log::warning('⚠️ [ENFANTS] Aucun enfant normalisé - suppression du champ');
                    unset($data['enfants']);
                }
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

            // 🛑 Gère explicitement les négations/affirmations orales (oui/non)
            $this->applyBooleanNegationsFromTranscript($transcription, $data);

            // 🎙️ Consentement audio - détection explicite dans la transcription
            $this->hydrateConsentementAudioFromTranscript($transcription, $data);

            // 🔁 Sécurise les drapeaux entreprise grâce à la transcription brute
            $this->hydrateEnterpriseFieldsFromTranscript($transcription, $data);

            // 🏠 Déduit code postal / ville quand l'adresse contient déjà tout
            $this->hydrateAddressComponents($data);

            // 🌍 Résidence fiscale - fallback depuis transcription brute
            $this->hydrateResidenceFiscaleFromTranscript($transcription, $data);

            // 🔤 PRIORITÉ ABSOLUE - Détection et application de l'épellation
            $this->detectAndApplySpelling($transcription, $data);

            // 🎯 Normalisation des besoins
            if (isset($data['besoins'])) {
                // S'assurer que besoins est un tableau
                if (is_string($data['besoins'])) {
                    // Si c'est une chaîne JSON, la décoder
                    $decoded = json_decode($data['besoins'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data['besoins'] = $decoded;
                    } else {
                        // Sinon, mettre la chaîne dans un tableau
                        $data['besoins'] = [$data['besoins']];
                    }
                } elseif (! is_array($data['besoins'])) {
                    $data['besoins'] = [];
                }

                // Nettoyer chaque besoin (supprimer espaces inutiles, normaliser)
                $data['besoins'] = array_map(function ($besoin) {
                    if (is_string($besoin)) {
                        // Si un besoin est lui-même une chaîne JSON, le décoder
                        $decoded = json_decode($besoin, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }

                        return trim($besoin);
                    }

                    return $besoin;
                }, $data['besoins']);

                // Aplatir le tableau si nécessaire (si on a des sous-tableaux)
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

            // Valider besoins_action
            if (isset($data['besoins_action'])) {
                if (! in_array($data['besoins_action'], ['add', 'remove', 'replace'])) {
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
     * @param  string  $date  Date à normaliser
     * @return string|null Date au format ISO ou null si invalide
     */
    private function normalizeDateToISO(string $date): ?string {
        try {
            // Nettoyer la date (supprimer espaces)
            $date = trim($date);
            if ($date === '') {
                return null;
            }

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

            // Tenter de parser avec Carbon (pour d'autres formats et mois FR)
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
    private function normalizeFrenchDateString(string $date): string {
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
            $normalized = preg_replace('/\b'.$fr.'\b/', $en, $normalized);
        }

        return $normalized;
    }

    /**
     * Normalise un numéro de téléphone (supprime espaces, points, tirets)
     *
     * @param  string  $phone  Numéro de téléphone
     * @return string|null Numéro normalisé ou null si invalide
     */
    private function normalizePhone(string $phone): ?string {
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
     * @param  string  $email  Adresse email
     * @return string|null Email normalisé ou null si invalide
     */
    private function normalizeEmail(string $email): ?string {
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
     * Détecte les épellations dans la transcription et les applique en priorité
     * Ex: "je suis né à Shalom... j'épelle C H Â L O N S" → force "Châlons"
     *
     * @param  string  $transcription  Transcription complète
     * @param  array  $data  Données extraites par GPT (modifiées par référence)
     */
    private function detectAndApplySpelling(string $transcription, array &$data): void {
        Log::info('🔤 Détection des épellations dans la transcription');

        $text = $transcription;

        // Champs susceptibles d'être épelés
        $fieldsToCheck = [
            'nom' => ['keywords' => ['nom', 'nom de famille', 'je m\'appelle', 'nom c\'est']],
            'prenom' => ['keywords' => ['prénom', 'prenom']],
            'ville' => ['keywords' => ['ville', 'j\'habite à', 'j\'habite']],
            'lieu_naissance' => ['keywords' => ['né à', 'née à', 'lieu de naissance', 'ville de naissance', 'naissance']],
            'email' => ['keywords' => ['email', 'mail', 'adresse mail']],
        ];

        foreach ($fieldsToCheck as $field => $config) {
            $spelledValue = $this->extractSpelledWord($text, $config['keywords']);

            if ($spelledValue !== null) {
                // Épellation détectée - PRIORITÉ ABSOLUE
                Log::info("✅ ÉPELLATION DÉTECTÉE pour '{$field}'", [
                    'field' => $field,
                    'spelled_value' => $spelledValue,
                    'old_value' => $data[$field] ?? 'non défini',
                ]);

                // Capitaliser la première lettre pour les noms propres
                if (in_array($field, ['nom', 'prenom', 'ville', 'lieu_naissance'])) {
                    $spelledValue = ucfirst(mb_strtolower($spelledValue, 'UTF-8'));
                }

                // FORCER la valeur épelée (priorité absolue)
                $data[$field] = $spelledValue;

                Log::info("🚨 PRIORITÉ ÉPELLATION - Valeur forcée pour '{$field}' : {$spelledValue}");
            }
        }
    }

    /**
     * Extrait un mot épelé depuis la transcription
     * Détecte les patterns : "X Y Z", "X comme ... Y comme ...", "j'épelle X Y Z"
     *
     * @param  string  $text  Transcription
     * @param  array  $keywords  Mots-clés précédant l'épellation (ex: "nom", "ville")
     * @return string|null Mot reconstruit ou null si pas d'épellation détectée
     */
    private function extractSpelledWord(string $text, array $keywords): ?string {
        $textLower = mb_strtolower($text, 'UTF-8');

        // Pattern 1: "j'épelle X Y Z" ou "je l'épelle X Y Z"
        if (preg_match('/(?:j\'?épelle|je\s+l\'?épelle)\s+([a-zàâäéèêëïîôùûüÿçæœ\s\-\']{3,})/ui', $text, $matches)) {
            $spelled = $this->reconstructSpelledWord($matches[1]);
            if ($spelled) {
                Log::info('🔤 Pattern "j\'épelle X Y Z" détecté', ['spelled' => $spelled]);

                return $spelled;
            }
        }

        // Pattern 2: Chercher autour des keywords
        foreach ($keywords as $keyword) {
            // Chercher "keyword c'est/est X Y Z" avec lettres espacées
            $pattern = '/'.preg_quote($keyword, '/').'\s+(?:c\'?est|est)?\s*([a-zàâäéèêëïîôùûüÿçæœ\s\-\']{3,})/ui';
            if (preg_match($pattern, $text, $matches)) {
                $spelled = $this->reconstructSpelledWord($matches[1]);
                if ($spelled) {
                    Log::info("🔤 Pattern \"$keyword c'est X Y Z\" détecté", ['spelled' => $spelled]);

                    return $spelled;
                }
            }
        }

        // Pattern 3: "X comme ... Y comme ..." (épellation phonétique)
        if (preg_match_all('/\b([a-z])\s+comme\s+[a-zàâäéèêëïîôùûüÿçæœ]+/ui', $text, $matches, PREG_SET_ORDER)) {
            if (count($matches) >= 3) {
                // Au moins 3 lettres épelées avec "comme"
                $letters = array_map(fn ($m) => mb_strtoupper($m[1], 'UTF-8'), $matches);
                $spelled = implode('', $letters);
                Log::info('🔤 Pattern "X comme ... Y comme ..." détecté', ['spelled' => $spelled]);

                return $spelled;
            }
        }

        return null;
    }

    /**
     * Reconstruit un mot à partir de lettres espacées
     * Ex: "D I J O N" → "Dijon", "C H Â L O N S" → "Châlons"
     *
     * @param  string  $text  Texte contenant des lettres espacées
     * @return string|null Mot reconstruit ou null si pas de pattern détecté
     */
    private function reconstructSpelledWord(string $text): ?string {
        $text = trim($text);

        // Détecter si le texte contient des lettres séparées par des espaces
        // Pattern: au moins 3 lettres séparées par des espaces
        if (preg_match_all('/\b([a-zàâäéèêëïîôùûüÿçæœ])\b/ui', $text, $matches)) {
            $letters = $matches[1];

            // Au moins 3 lettres pour être considéré comme une épellation
            if (count($letters) >= 3) {
                // Vérifier que les lettres sont bien espacées (pas un mot normal)
                $spacing = preg_match('/[a-zàâäéèêëïîôùûüÿçæœ]\s+[a-zàâäéèêëïîôùûüÿçæœ]/ui', $text);

                if ($spacing) {
                    $word = implode('', $letters);
                    Log::info('🔤 Lettres espacées reconstruites', [
                        'original' => $text,
                        'letters' => $letters,
                        'word' => $word,
                    ]);

                    return $word;
                }
            }
        }

        return null;
    }

    /**
     * Convertit les nombres verbaux français en chiffres
     * Exemples: "cinquante-et-un" → "51", "cinquante-et-un cent" → "51100"
     *
     * @param  string  $text  Texte contenant potentiellement des nombres verbaux
     * @return string Texte avec les nombres convertis en chiffres
     */
    private function convertFrenchVerbalNumbers(string $text): string {
        // Dictionnaire des nombres de base
        $numbers = [
            'zéro' => 0, 'zero' => 0,
            'un' => 1, 'une' => 1,
            'deux' => 2,
            'trois' => 3,
            'quatre' => 4,
            'cinq' => 5,
            'six' => 6,
            'sept' => 7,
            'huit' => 8,
            'neuf' => 9,
            'dix' => 10,
            'onze' => 11,
            'douze' => 12,
            'treize' => 13,
            'quatorze' => 14,
            'quinze' => 15,
            'seize' => 16,
            'vingt' => 20,
            'trente' => 30,
            'quarante' => 40,
            'cinquante' => 50,
            'soixante' => 60,
            'cent' => 100,
            'cents' => 100,
            'mille' => 1000,
        ];

        // Nombres composés courants (pour optimisation)
        $composedNumbers = [
            'vingt-et-un' => 21, 'vingt et un' => 21,
            'vingt-deux' => 22, 'vingt deux' => 22,
            'vingt-trois' => 23, 'vingt trois' => 23,
            'vingt-quatre' => 24, 'vingt quatre' => 24,
            'vingt-cinq' => 25, 'vingt cinq' => 25,
            'vingt-six' => 26, 'vingt six' => 26,
            'vingt-sept' => 27, 'vingt sept' => 27,
            'vingt-huit' => 28, 'vingt huit' => 28,
            'vingt-neuf' => 29, 'vingt neuf' => 29,
            'trente-et-un' => 31, 'trente et un' => 31,
            'trente-deux' => 32, 'trente deux' => 32,
            'trente-trois' => 33, 'trente trois' => 33,
            'trente-quatre' => 34, 'trente quatre' => 34,
            'trente-cinq' => 35, 'trente cinq' => 35,
            'trente-six' => 36, 'trente six' => 36,
            'trente-sept' => 37, 'trente sept' => 37,
            'trente-huit' => 38, 'trente huit' => 38,
            'trente-neuf' => 39, 'trente neuf' => 39,
            'quarante-et-un' => 41, 'quarante et un' => 41,
            'quarante-deux' => 42, 'quarante deux' => 42,
            'quarante-trois' => 43, 'quarante trois' => 43,
            'quarante-quatre' => 44, 'quarante quatre' => 44,
            'quarante-cinq' => 45, 'quarante cinq' => 45,
            'quarante-six' => 46, 'quarante six' => 46,
            'quarante-sept' => 47, 'quarante sept' => 47,
            'quarante-huit' => 48, 'quarante huit' => 48,
            'quarante-neuf' => 49, 'quarante neuf' => 49,
            'cinquante-et-un' => 51, 'cinquante et un' => 51,
            'cinquante-deux' => 52, 'cinquante deux' => 52,
            'cinquante-trois' => 53, 'cinquante trois' => 53,
            'cinquante-quatre' => 54, 'cinquante quatre' => 54,
            'cinquante-cinq' => 55, 'cinquante cinq' => 55,
            'cinquante-six' => 56, 'cinquante six' => 56,
            'cinquante-sept' => 57, 'cinquante sept' => 57,
            'cinquante-huit' => 58, 'cinquante huit' => 58,
            'cinquante-neuf' => 59, 'cinquante neuf' => 59,
            'soixante-et-un' => 61, 'soixante et un' => 61,
            'soixante-deux' => 62, 'soixante deux' => 62,
            'soixante-trois' => 63, 'soixante trois' => 63,
            'soixante-quatre' => 64, 'soixante quatre' => 64,
            'soixante-cinq' => 65, 'soixante cinq' => 65,
            'soixante-six' => 66, 'soixante six' => 66,
            'soixante-sept' => 67, 'soixante sept' => 67,
            'soixante-huit' => 68, 'soixante huit' => 68,
            'soixante-neuf' => 69, 'soixante neuf' => 69,
            'soixante-dix' => 70, 'soixante dix' => 70,
            'soixante-et-onze' => 71, 'soixante et onze' => 71,
            'soixante-douze' => 72, 'soixante douze' => 72,
            'soixante-treize' => 73, 'soixante treize' => 73,
            'soixante-quatorze' => 74, 'soixante quatorze' => 74,
            'soixante-quinze' => 75, 'soixante quinze' => 75,
            'soixante-seize' => 76, 'soixante seize' => 76,
            'quatre-vingts' => 80, 'quatre vingts' => 80,
            'quatre-vingt' => 80, 'quatre vingt' => 80,
            'quatre-vingt-un' => 81, 'quatre vingt un' => 81,
            'quatre-vingt-deux' => 82, 'quatre vingt deux' => 82,
            'quatre-vingt-dix' => 90, 'quatre vingt dix' => 90,
            'quatre-vingt-onze' => 91, 'quatre vingt onze' => 91,
        ];

        $textLower = mb_strtolower($text, 'UTF-8');

        // Cas spécial pour les codes postaux : "XX cent" → "XX100"
        // Ex: "cinquante-et-un cent" → "51100"
        $textLower = preg_replace_callback(
            '/\b((?:vingt|trente|quarante|cinquante|soixante)(?:[\s-](?:et[\s-])?(?:un|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize))?|quatre[\s-]vingt(?:[\s-](?:un|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize))?|soixante[\s-]dix|un|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize|dix[\s-]sept|dix[\s-]huit|dix[\s-]neuf)\s+(cent|mille)\b/u',
            function ($matches) use ($composedNumbers, $numbers) {
                $firstPart = trim($matches[1]);
                $secondPart = trim($matches[2]);

                // Convertir la première partie
                $firstNumber = $composedNumbers[$firstPart] ?? $numbers[$firstPart] ?? null;

                if ($firstNumber !== null) {
                    // Pour les codes postaux: concaténation, pas multiplication
                    if ($secondPart === 'cent') {
                        // "51 cent" → "51100"
                        return str_pad($firstNumber, 2, '0', STR_PAD_LEFT).'100';
                    } elseif ($secondPart === 'mille') {
                        // "51 mille" → "51000"
                        return str_pad($firstNumber, 2, '0', STR_PAD_LEFT).'000';
                    }
                }

                return $matches[0]; // Pas de conversion possible
            },
            $textLower
        );

        // Remplacer les nombres composés (plus longs en premier)
        foreach ($composedNumbers as $verbal => $numeric) {
            $pattern = '/\b'.preg_quote($verbal, '/').'\b/u';
            $textLower = preg_replace($pattern, (string) $numeric, $textLower);
        }

        // Remplacer les nombres simples
        foreach ($numbers as $verbal => $numeric) {
            $pattern = '/\b'.preg_quote($verbal, '/').'\b/u';
            $textLower = preg_replace($pattern, (string) $numeric, $textLower);
        }

        return $textLower;
    }

    /**
     * Normalise un code postal français
     *
     * @param  string  $postalCode  Code postal
     * @return string|null Code postal normalisé (5 chiffres) ou null si invalide
     */
    private function normalizePostalCode(string $postalCode): ?string {
        try {
            // ÉTAPE 1: Convertir les nombres verbaux français en chiffres
            // Ex: "cinquante-et-un cent" → "51100"
            $converted = $this->convertFrenchVerbalNumbers($postalCode);

            Log::info('🔢 Conversion nombres verbaux pour code postal', [
                'original' => $postalCode,
                'converted' => $converted,
            ]);

            // ÉTAPE 2: Supprimer les espaces
            $normalized = trim($converted);

            // ÉTAPE 3: Supprimer tous les caractères non numériques
            $normalized = preg_replace('/[^0-9]/', '', $normalized);

            // ÉTAPE 4: Validation - doit être exactement 5 chiffres pour la France
            if (preg_match('/^\d{5}$/', $normalized)) {
                Log::info('✅ Code postal normalisé avec succès', ['result' => $normalized]);

                return $normalized;
            }

            Log::warning('Format code postal invalide après conversion', [
                'code_postal' => $postalCode,
                'converted' => $converted,
                'normalized' => $normalized,
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::warning('Impossible de normaliser le code postal', [
                'code_postal' => $postalCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Normalise les entrées booléennes, y compris les réponses orales (oui/non).
     */
    private function normalizeBoolean(mixed $value): ?bool {
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
     * Analyse la transcription pour comprendre les affirmations/négations sur les champs booléens.
     */
    private function applyBooleanNegationsFromTranscript(string $transcription, array &$data): void {
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
                    "/aucune?\s+activité\s+sportive/u",
                ],
                'positive' => [
                    "/je\s+fais\s+du\s+sport/u",
                    "/je\s+pratique\s+un\s+sport/u",
                    "/je\s+fais\s+de\s+l['e]\s+sport/u",
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
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:un\s+|une\s+)?chef\s+d['’\s]?entreprise/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:un\s+|une\s+)?chef\s+d['’\s]?entreprise/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:chef\s+d['’\s]?entreprise)/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:chef\s+d['’\s]?entreprise)/u",
                    "/pas\s+chef\s+d['’\s]?entreprise/u",
                    "/plus\s+chef\s+d['’\s]?entreprise/u",
                    "/ni\s+chef\s+d['’\s]?entreprise/u",
                ],
                'positive' => [
                    "/\bchef\s+d['’\s]?entreprise/u",
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

            if (! empty($patterns['positive'])) {
                foreach ($patterns['positive'] as $regex) {
                    if (preg_match($regex, $text)) {
                        if (! array_key_exists($field, $data) || $data[$field] === null) {
                            $data[$field] = true;
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * Détecte explicitement le consentement audio dans la transcription.
     */
    private function hydrateConsentementAudioFromTranscript(string $transcription, array &$data): void {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", $transcription), 'UTF-8');

        $negative = [
            "/je\s+ne\s+souhaite\s+pas\s+être\s+enregistr[ée]/u",
            "/je\s+ne\s+veux\s+pas\s+être\s+enregistr[ée]/u",
            "/je\s+refuse\s+l['e]\s+enregistrement/u",
            "/pas\s+d['e]\s+enregistrement/u",
            "/sans\s+enregistrement/u",
            "/je\s+ne\s+suis\s+pas\s+d['e]\s+accord\s+pour\s+l['e]\s+enregistrement/u",
            "/je\s+ne\s+suis\s+pas\s+d['e]\s+accord\s+pour\s+enregistrer/u",
            "/je\s+n['e]\s+accepte\s+pas\s+l['e]\s+enregistrement/u",
            "/je\s+n['e]\s+accepte\s+pas\s+que\s+.*\s+soit\s+enregistr[ée]/u",
            "/ne\s+pas\s+enregistrer\s+(?:le\s+)?(?:rdv|rendez-vous|entretien|conversation)/u",
        ];

        foreach ($negative as $regex) {
            if (preg_match($regex, $text)) {
                Log::info('🔍 [CONSENTEMENT] Refus détecté', ['pattern' => $regex]);
                $data['consentement_audio'] = false;

                return;
            }
        }

        $positive = [
            "/j['e]\s+accepte\s+l['e]\s+enregistrement/u",
            "/j['e]\s+accepte\s+que\s+.*\s+soit\s+enregistr[ée]/u",
            "/je\s+suis\s+d['e]\s+accord\s+pour\s+l['e]\s+enregistrement/u",
            "/je\s+suis\s+d['e]\s+accord\s+pour\s+enregistrer/u",
            "/ok\s+pour\s+l['e]\s+enregistrement/u",
            "/oui\s+pour\s+l['e]\s+enregistrement/u",
            "/vous\s+pouvez\s+enregistrer/u",
            "/vous\s+pouvez\s+enregistrer\s+(?:le\s+)?(?:rdv|rendez-vous|entretien|conversation)/u",
            "/c['e]\s+est\s+bon\s+pour\s+l['e]\s+enregistrement/u",
        ];

        foreach ($positive as $regex) {
            if (preg_match($regex, $text)) {
                Log::info('✅ [CONSENTEMENT] Accord détecté', ['pattern' => $regex]);
                $data['consentement_audio'] = true;

                return;
            }
        }
    }

    /**
     * Détecte les mentions vocales d'informations entreprise pour fiabiliser les drapeaux.
     */
    private function hydrateEnterpriseFieldsFromTranscript(string $transcription, array &$data): void {
        $text = mb_strtolower(str_replace(['’', '‘'], "'", $transcription), 'UTF-8');

        $patterns = [
            'chef_entreprise' => [
                'positive' => [
                    "/\bchef\s+d['’\s]?entreprise/u",
                    "/je\s+dirige\s+(?:ma|mon|une)\s+(?:entreprise|société)/u",
                    "/je\s+gère\s+(?:ma|mon|une)\s+(?:propre\s+)?entreprise/u",
                    "/(?:ma|mon)\s+(?:propre\s+)?entreprise/u",
                ],
                'negative' => [
                    "/je\s+(?:ne\s+)?suis\s+pas\s+(?:un\s+|une\s+)?chef\s+d['’\s]?entreprise/u",
                    "/je\s+(?:ne\s+)?suis\s+plus\s+(?:un\s+|une\s+)?chef\s+d['’\s]?entreprise/u",
                    "/on\s+(?:n['e]\s+)?est\s+pas\s+(?:chef\s+d['’\s]?entreprise)/u",
                    "/on\s+(?:n['e]\s+)?est\s+plus\s+(?:chef\s+d['’\s]?entreprise)/u",
                    "/pas\s+chef\s+d['’\s]?entreprise/u",
                    "/plus\s+chef\s+d['’\s]?entreprise/u",
                    "/ni\s+chef\s+d['’\s]?entreprise/u",
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
            // Tient compte des négations explicites EN PRIORITÉ
            foreach ($regexes['negative'] as $negativeRegex) {
                if (preg_match($negativeRegex, $text)) {
                    Log::info("🔍 [ENTREPRISE] Pattern négatif trouvé pour $field", ['pattern' => $negativeRegex]);
                    $data[$field] = false;

                    continue 2; // Skip ce champ et passer au suivant
                }
            }

            // Chercher les patterns positifs (TOUJOURS vérifier, même si GPT a déjà extrait false)
            $matched = false;
            foreach ($regexes['positive'] as $positiveRegex) {
                if (preg_match($positiveRegex, $text)) {
                    Log::info("✅ [ENTREPRISE] Pattern positif trouvé pour $field", ['pattern' => $positiveRegex]);
                    $data[$field] = true;
                    $matched = true;
                    break; // Pattern trouvé, passer au champ suivant
                }
            }

            if (! $matched) {
                Log::info("❌ [ENTREPRISE] Aucun pattern trouvé pour $field");
            }

            // Si aucun pattern positif trouvé et que le champ n'existe pas encore, le laisser undefined
            // (ne pas forcer à false, car l'absence d'information ≠ false)
        }

        Log::info('🔍 [ENTREPRISE] Résultat après analyse', [
            'chef_entreprise' => $data['chef_entreprise'] ?? 'non défini',
            'travailleur_independant' => $data['travailleur_independant'] ?? 'non défini',
            'mandataire_social' => $data['mandataire_social'] ?? 'non défini',
            'statut' => $data['statut'] ?? 'non défini',
        ]);

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
                $pattern = '/\b'.preg_quote($needle, '/').'\b/u';
                if (preg_match($pattern, $text)) {
                    $data['statut'] = $label;
                    break;
                }
            }
        }
    }

    /**
     * Recherche la ville correspondant à un code postal dans la base de données
     *
     * @param  string  $postalCode  Code postal normalisé (5 chiffres)
     * @return string|null Ville trouvée ou null
     */
    private function lookupCityFromPostalCode(string $postalCode): ?string {
        try {
            // Chercher dans la table clients les villes existantes pour ce code postal
            $city = \App\Models\Client::where('code_postal', $postalCode)
                ->whereNotNull('ville')
                ->where('ville', '!=', '')
                ->groupBy('ville')
                ->orderByRaw('COUNT(*) DESC')
                ->value('ville');

            if ($city) {
                Log::info('🏙️ Ville trouvée pour le code postal', [
                    'code_postal' => $postalCode,
                    'ville' => $city,
                ]);

                return $city;
            }

            Log::info('🔍 Aucune ville trouvée en BDD pour ce code postal', [
                'code_postal' => $postalCode,
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::warning('Erreur lors de la recherche de ville par code postal', [
                'code_postal' => $postalCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Analyse l'adresse complète et isole code postal / ville si besoin.
     */
    private function hydrateAddressComponents(array &$data): void {
        if (empty($data['adresse'])) {
            return;
        }

        $address = trim($data['adresse']);
        if ($address === '') {
            return;
        }

        $postalMatches = [];
        if (preg_match_all('/\b(\d{5})\b(?:\s+([A-Za-zÀ-ÖØ-öø-ÿ\'\-\s]+))?/u', $address, $postalMatches, PREG_SET_ORDER)) {
            $match = end($postalMatches);

            if (! empty($match[1]) && (empty($data['code_postal']) || strlen((string) $data['code_postal']) < 5)) {
                $normalizedPostal = $this->normalizePostalCode($match[1]);
                if ($normalizedPostal) {
                    $data['code_postal'] = $normalizedPostal;
                }
            }

            if (empty($data['ville']) && ! empty($match[2])) {
                $cityCandidate = trim(preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\'\-\s]/u', '', $match[2]));
                if ($cityCandidate !== '') {
                    $data['ville'] = $cityCandidate;
                }
            }
        }

        if (empty($data['ville'])) {
            $segments = preg_split('/[,;\\n]/u', $address);
            $lastSegment = trim(end($segments));
            $lastSegment = preg_replace('/^\d{5}\s*/', '', $lastSegment);

            if ($lastSegment !== '' && ! preg_match('/\d{3,}/', $lastSegment)) {
                $data['ville'] = $lastSegment;
            }
        }

        // 🏙️ RECHERCHE AUTOMATIQUE DE LA VILLE PAR CODE POSTAL
        // Si on a un code postal mais pas de ville, chercher en BDD
        if (! empty($data['code_postal']) && empty($data['ville'])) {
            $lookedUpCity = $this->lookupCityFromPostalCode($data['code_postal']);
            if ($lookedUpCity) {
                $data['ville'] = $lookedUpCity;
                Log::info('✅ Ville auto-complétée depuis le code postal', [
                    'code_postal' => $data['code_postal'],
                    'ville' => $lookedUpCity,
                ]);
            }
        }
    }

    private function hydrateResidenceFiscaleFromTranscript(string $transcription, array &$data): void {
        if (! empty($data['residence_fiscale'])) {
            return;
        }

        $value = $this->extractResidenceFiscale($transcription);
        if ($value) {
            $data['residence_fiscale'] = $value;
            Log::info('🌍 Résidence fiscale détectée depuis transcription', [
                'residence_fiscale' => $value,
            ]);
        }
    }

    private function extractResidenceFiscale(string $transcription): ?string {
        $patterns = [
            '/résidence fiscale[^\\p{L}0-9]{0,6}([\\p{L}][\\p{L}\\s\\-\'’]{1,60})/iu',
            '/résident fiscal[^\\p{L}0-9]{0,6}([\\p{L}][\\p{L}\\s\\-\'’]{1,60})/iu',
            '/pays de résidence(?: fiscale)?[^\\p{L}0-9]{0,6}([\\p{L}][\\p{L}\\s\\-\'’]{1,60})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $transcription, $matches)) {
                continue;
            }

            $candidate = trim($matches[1]);
            $candidate = preg_split('/[\\n\\r\\.?;:!]+/u', $candidate)[0] ?? $candidate;
            $candidate = preg_split('/\\b(conseiller|client|courtier)\\b/iu', $candidate)[0] ?? $candidate;
            $candidate = trim($candidate, " \t\n\r\0\x0B-");

            if ($candidate === '' || mb_strlen($candidate) < 2) {
                continue;
            }

            $lower = mb_strtolower($candidate);
            if (in_array($lower, ['oui', 'non', 'ok', "d'accord"], true)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * Tente de corriger un email incomplet en analysant la transcription originale
     *
     * @param  string  $transcription  Transcription vocale complète
     * @param  string  $incompleteEmail  Email incomplet extrait par GPT
     * @return string|null Email corrigé ou null si impossible
     */
    private function tryFixIncompleteEmail(string $transcription, string $incompleteEmail): ?string {
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

                    if (! empty($local) && ! empty($domain) && str_contains($domain, '.')) {
                        $finalEmail = strtolower($local.'@'.$domain);
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
     * Sauvegarde les données du questionnaire de risque si présentes dans les données extraites
     *
     * @param  int  $clientId  ID du client
     * @param  array  $data  Données extraites contenant potentiellement questionnaire_risque
     */
    public function saveQuestionnaireRisque(int $clientId, array $data): void {
        try {
            // Vérifier si des données de questionnaire de risque sont présentes
            if (! isset($data['questionnaire_risque']) || empty($data['questionnaire_risque'])) {
                Log::info('Aucune donnée de questionnaire de risque à sauvegarder', ['client_id' => $clientId]);

                return;
            }

            $questionnaireData = $data['questionnaire_risque'];

            // Vérifier qu'il y a au moins des données financières ou de connaissances
            if (empty($questionnaireData['financier']) && empty($questionnaireData['connaissances'])) {
                Log::info('Données de questionnaire vides, abandon', ['client_id' => $clientId]);

                return;
            }

            Log::info('💾 Sauvegarde du questionnaire de risque', [
                'client_id' => $clientId,
                'has_financier' => ! empty($questionnaireData['financier']),
                'has_connaissances' => ! empty($questionnaireData['connaissances']),
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

            // Sauvegarder les données financières/comportementales si présentes
            if (! empty($questionnaireData['financier']) && is_array($questionnaireData['financier'])) {
                $financierData = array_filter($questionnaireData['financier'], function ($value) {
                    return ! is_null($value) && $value !== '';
                });

                if (! empty($financierData)) {
                    $questionnaire->financier()->updateOrCreate(
                        ['questionnaire_risque_id' => $questionnaire->id],
                        $financierData
                    );
                    Log::info('✅ Données financières sauvegardées', ['data' => $financierData]);
                }
            }

            // Sauvegarder les connaissances si présentes
            if (! empty($questionnaireData['connaissances']) && is_array($questionnaireData['connaissances'])) {
                $connaissancesData = array_filter($questionnaireData['connaissances'], function ($value) {
                    return ! is_null($value) && $value !== '';
                });

                if (! empty($connaissancesData)) {
                    $questionnaire->connaissances()->updateOrCreate(
                        ['questionnaire_risque_id' => $questionnaire->id],
                        $connaissancesData
                    );
                    Log::info('✅ Connaissances sauvegardées', ['data' => $connaissancesData]);
                }
            }

            // Recalculer le score avec le ScoringService
            $scoringService = app(\App\Services\ScoringService::class);
            $updatedQuestionnaire = $scoringService->scorerEtSauvegarder($questionnaire, [
                'financier' => $questionnaireData['financier'] ?? [],
                'connaissances' => $questionnaireData['connaissances'] ?? [],
                'quiz' => [], // Pas de quiz rempli par vocal pour l'instant
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
