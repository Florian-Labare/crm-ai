<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour les informations CLIENT.
 * 
 * Responsabilité :
 * - Identité (civilite, nom, prenom, date_naissance, etc.)
 * - Situation matrimoniale / familiale
 * - Enfants (tableau d'objets)
 * - Coordonnées (adresse, téléphone, email)
 * - Situation professionnelle
 * 
 * N'extrait PAS les BAE (gérés par d'autres extractors).
 */
class ClientExtractor
{
    /**
     * Extrait les données client depuis la transcription.
     *
     * @param string $transcription Transcription vocale
     * @param array $currentData Données client existantes (optionnel)
     * @return array Données extraites
     */
    public function extract(string $transcription, array $currentData = []): array
    {
        $prompt = $this->buildPrompt($transcription);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'OpenAI-Organization' => env('OPENAI_ORG_ID'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => $this->getSystemPrompt()],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.1, // Extraction déterministe
                        'response_format' => ['type' => 'json_object'],
                    ]);

            $json = $response->json();
            $raw = $json['choices'][0]['message']['content'] ?? '';

            Log::info('[ClientExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Construit le prompt utilisateur.
     */
    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et extrais UNIQUEMENT les informations personnelles du CLIENT PRINCIPAL (celui qui parle, qui dit "je").

⚠️ IMPORTANT : IGNORE complètement les informations sur le conjoint/époux/épouse ("ma femme", "mon mari", etc.).

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide concernant UNIQUEMENT le client principal, sans aucun texte avant ou après.
PROMPT;
    }

    /**
     * Retourne le prompt système pour l'extraction client.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de données client pour un CRM d'assurance.

🎯 OBJECTIF :
Extraire UNIQUEMENT les informations personnelles du client depuis la transcription vocale.

🔤 EPPELLATION / DICTÉE :
- Si une valeur est épelée lettre par lettre (ex: "D U P O N T" ou "D comme David"), reconstruis le mot complet en collant les lettres dans l'ordre.
- Ignore les séparateurs (espaces, tirets, points, pauses).
- Pour email/adresse : "arobase" → "@", "point" → ".", "tiret" → "-", "underscore" → "_".
- Pour téléphone : concatène tous les chiffres en une seule chaîne.

🚫 RÈGLES ABSOLUES - DISTINCTION CONSEILLER vs CLIENT vs CONJOINT :
1. **Ignore le CONSEILLER** : Ignore TOUTES les phrases du conseiller (questions, propositions, énumérations d'options)
2. **N'extrais QUE le CLIENT PRINCIPAL** : Ne tiens compte QUE des réponses du client principal (phrases avec "je", "moi", "mon", "ma", "mes")
3. **IGNORE TOTALEMENT le CONJOINT** : Si tu détectes des informations sur le conjoint/époux/épouse/partenaire, NE LES EXTRAIS PAS
   - Mots-clés à IGNORER : "mon conjoint", "ma femme", "mon mari", "mon épouse", "mon époux", "ma/mon partenaire", "ma/mon compagne/compagnon"
   - Exemple : "Ma femme s'appelle Sophie, elle est médecin" → N'EXTRAIS RIEN, ces infos concernent le conjoint
4. En cas de doute sur qui parle → N'extrais PAS l'information

✅ CHAMPS À EXTRAIRE (si mentionnés) :

**Identité :**
- "civilite" (string) : "M.", "Mme", "Mlle"
- "nom" (string) : nom de famille
- "nom_jeune_fille" (string) : nom de jeune fille si applicable
- "prenom" (string) : prénom
- "date_naissance" (string) : format "YYYY-MM-DD" ou "DD/MM/YYYY"
- "lieu_naissance" (string) : ville COMPLÈTE (ex: "Châlons-en-Champagne", PAS "Châlons")
- "nationalite" (string) : nationalité

**Situation familiale :**
- "situation_matrimoniale" (string) : "Marié(e)", "Célibataire", "Divorcé(e)", "Veuf(ve)", "Pacsé(e)", "Concubinage"
- "date_situation_matrimoniale" (string) : date du mariage/pacs/divorce
- "enfants" (array) : tableau d'objets enfants (voir structure ci-dessous)

**Coordonnées :**
- "adresse" (string) : numéro et nom de rue SEULEMENT
- "code_postal" (string) : 5 chiffres
- "ville" (string) : nom COMPLET de la ville
- "telephone" (string) : numéro de téléphone
- "email" (string) : adresse email
- "residence_fiscale" (string) : pays de résidence fiscale

**Situation professionnelle :**
- "situation_actuelle" (string) : "Salarié(e)", "Retraité(e)", "Étudiant(e)", "Demandeur d'emploi"
- "profession" (string) : métier exact (ex: "plombier", "médecin")
- "revenus_annuels" (string) : revenus annuels
- "risques_professionnels" (boolean) : true/false
- "details_risques_professionnels" (string) : détails sur les risques
- "date_evenement_professionnel" (string) : date d'un événement pro

**Informations entreprise :**
- "chef_entreprise" (boolean) : true si chef d'entreprise
- "travailleur_independant" (boolean) : true si freelance/indépendant
- "mandataire_social" (boolean) : true si mandataire social
- "statut" (string) : "SARL", "SAS", "SASU", "EURL", "SCI", "Auto-entrepreneur", etc.

⚠️ IMPORTANT - Champs entreprise :
- NE JAMAIS mettre "chef d'entreprise" dans "profession"
- NE JAMAIS mettre "travailleur indépendant" dans "profession"
- NE JAMAIS mettre "mandataire social" dans "profession"
- Utiliser UNIQUEMENT les champs booléens dédiés

**Santé et loisirs :**
- "fumeur" (boolean) : true/false
- "activites_sportives" (boolean) : true/false
- "details_activites_sportives" (string) : détails
- "niveau_activites_sportives" (string) : niveau de pratique

**Autres :**
- "consentement_audio" (boolean) : consentement pour l'enregistrement

📋 STRUCTURE ENFANTS (tableau d'objets) :
Si le client mentionne ses enfants, retourne un tableau avec ces champs par enfant :
- "nom" (string) : nom de famille
- "prenom" (string) : prénom
- "date_naissance" (string) : format "YYYY-MM-DD"
- "fiscalement_a_charge" (boolean) : true si à charge
- "garde_alternee" (boolean) : true si garde alternée

🚨 TRÈS IMPORTANT - CAPTURER TOUS LES ENFANTS :
- Si le client dit "j'ai deux enfants, Alicia et Léana", tu DOIS retourner un tableau avec LES DEUX enfants
- Si le client dit "j'ai trois enfants : Paul, Marie et Sophie", tu DOIS retourner LES TROIS
- Ne JAMAIS oublier un enfant mentionné dans la liste !

Exemples :
"J'ai deux enfants, Emma et Louis"
→ {
  "enfants": [
    {"prenom": "Emma"},
    {"prenom": "Louis"}
  ]
}

"Mes enfants s'appellent Paul, Marie et Sophie"
→ {
  "enfants": [
    {"prenom": "Paul"},
    {"prenom": "Marie"},
    {"prenom": "Sophie"}
  ]
}

"J'ai un enfant, Thomas, né le 15 mars 2012"
→ {
  "enfants": [
    {"prenom": "Thomas", "date_naissance": "2012-03-15"}
  ]
}

🚫 NE PAS EXTRAIRE :
- **Les informations du CONJOINT** → gérées par ConjointExtractor
  - Si le client dit "mon conjoint/ma femme/mon mari s'appelle X", "mon épouse fait Y", etc. → IGNORE complètement
  - Seules les infos du CLIENT PRINCIPAL doivent être extraites
- Les besoins (prévoyance, retraite, épargne, mutuelle) → gérés par d'autres extractors
- Les données BAE (bae_prevoyance, bae_retraite, bae_epargne) → gérés par d'autres extractors
- Les données de santé/mutuelle (sante_souhait) → gérées par d'autres extractors

📌 RÈGLES IMPORTANTES :
1. **UNIQUEMENT LE CLIENT PRINCIPAL** : N'extrais QUE les informations du client qui parle (celui qui dit "je", "moi")
2. **JAMAIS LE CONJOINT** : Si tu vois "mon conjoint", "ma femme", "mon mari", "elle/il" (parlant du conjoint) → IGNORE
3. Ne jamais inventer de données
4. Ne remplir un champ QUE si l'information est claire et concerne le CLIENT PRINCIPAL
5. Respecter l'épellation lettre par lettre si le client épelle
6. Garder les noms de villes COMPLETS (ex: "Aix-en-Provence", pas "Aix")
7. Respecter la négation (ex: "je ne suis PAS fumeur" → fumeur: false)
8. Répondre UNIQUEMENT avec du JSON strict, sans texte explicatif

🏃 ACTIVITÉS SPORTIVES - RÈGLES CRITIQUES :
- Si le client dit "oui", "oui tout à fait", "je fais du sport" en réponse à une question sur le sport → activites_sportives: true
- Si le client mentionne un sport (foot, tennis, natation, musculation, course, etc.) → activites_sportives: true
- Si le client dit "non", "pas vraiment", "je ne fais pas de sport" → activites_sportives: false
- TOUJOURS mettre activites_sportives à true si le client pratique une activité physique, même occasionnelle

Exemples sports :
- "Est-ce que vous faites du sport ?" "Oui" → activites_sportives: true
- "Je fais de la musculation" → activites_sportives: true, details_activites_sportives: "musculation"
- "Je cours le week-end" → activites_sportives: true, details_activites_sportives: "course à pied"
- "Non je ne fais pas de sport" → activites_sportives: false

🚬 FUMEUR - RÈGLES :
- "Vous fumez ?" "Oui" → fumeur: true
- "Vous fumez ?" "Non" → fumeur: false
- "Je ne fume pas" → fumeur: false
- "Je suis fumeur" → fumeur: true

Exemple JSON valide (CLIENT PRINCIPAL uniquement) :
{
  "civilite": "M.",
  "nom": "Dupont",
  "prenom": "Jean",
  "date_naissance": "1980-05-15",
  "lieu_naissance": "Paris",
  "situation_matrimoniale": "Marié(e)",
  "telephone": "0601020304",
  "email": "jean.dupont@example.com",
  "profession": "architecte",
  "chef_entreprise": true,
  "statut": "SARL",
  "fumeur": false,
  "activites_sportives": true,
  "details_activites_sportives": "tennis, natation",
  "niveau_activites_sportives": "loisir",
  "enfants": [
    {"prenom": "Marie", "date_naissance": "2010-01-01", "fiscalement_a_charge": true}
  ]
}

❌ EXEMPLE À NE PAS FAIRE - Extraire les infos du conjoint :
Transcription : "Je m'appelle Jean Dupont. Ma femme s'appelle Sophie Martin, elle est médecin."
MAUVAIS → {"nom": "Martin", "prenom": "Sophie", "profession": "médecin"}  // ❌ C'est le conjoint !
BON → {"nom": "Dupont", "prenom": "Jean"}  // ✅ Uniquement le client principal
PROMPT;
    }
}
