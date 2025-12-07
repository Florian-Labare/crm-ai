<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour les informations du CONJOINT.
 *
 * Responsabilité :
 * - Identité du conjoint (civilité, nom, prénom, date_naissance, etc.)
 * - Situation professionnelle du conjoint
 * - Coordonnées du conjoint (téléphone, adresse)
 * - Risques professionnels du conjoint
 *
 * N'extrait PAS les données du client principal (géré par ClientExtractor).
 */
class ConjointExtractor
{
    /**
     * Extrait les données du conjoint depuis la transcription.
     *
     * @param string $transcription Transcription vocale
     * @param array $currentData Données existantes (optionnel)
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

            Log::info('[ConjointExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ConjointExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ConjointExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Construit le prompt utilisateur.
     */
    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et extrais UNIQUEMENT les informations concernant le CONJOINT (époux/épouse, partenaire de PACS, concubin(e)).

⚠️ IMPORTANT :
- Cherche les mentions : "mon conjoint", "ma femme", "mon mari", "mon épouse", "elle/il" (parlant du conjoint)
- IGNORE complètement les informations du client principal (celui qui dit "je", "moi")

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide concernant UNIQUEMENT le conjoint (ou {} si aucune info sur le conjoint), sans aucun texte avant ou après.
PROMPT;
    }

    /**
     * Retourne le prompt système pour l'extraction du conjoint.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de données CONJOINT pour un CRM d'assurance.

🎯 OBJECTIF :
Détecter si le client parle de son CONJOINT et extraire les données associées.

🚫 RÈGLES ABSOLUES - DISTINCTION CLIENT PRINCIPAL vs CONJOINT :
1. **N'extrais QUE le CONJOINT** : Cherche UNIQUEMENT les informations introduites par :
   - "mon conjoint", "ma femme", "mon mari", "mon épouse", "mon époux"
   - "ma/mon partenaire", "ma/mon compagne/compagnon"
   - "elle" ou "il" (quand le contexte indique clairement qu'il s'agit du conjoint)

2. **IGNORE TOTALEMENT le CLIENT PRINCIPAL** :
   - Si le client dit "je m'appelle...", "je suis...", "mon métier..." → IGNORE, c'est le client principal
   - Cherche UNIQUEMENT les phrases qui parlent d'une AUTRE personne (le conjoint)

3. **Exemples de détection** :
   - ✅ "Ma femme s'appelle Sophie" → Extraire : {"prenom": "Sophie"}
   - ✅ "Mon mari est médecin" → Extraire : {"profession": "médecin"}
   - ✅ "Elle est née en 1985" (si contexte = conjoint) → Extraire : {"date_naissance": "1985-XX-XX"}
   - ❌ "Je m'appelle Jean" → IGNORER (c'est le client principal)
   - ❌ "Je suis architecte" → IGNORER (c'est le client principal)

4. En cas de doute sur qui est concerné → N'extrais PAS l'information

✅ SI LE CLIENT PARLE DE SON CONJOINT :

Retourne :
{
  "conjoint": {
    // Remplis les champs ci-dessous SEULEMENT si mentionnés
  }
}

📋 CHAMPS conjoint (optionnels) :

- "nom" (string) : nom de famille du conjoint
- "nom_jeune_fille" (string) : nom de jeune fille si applicable
- "prenom" (string) : prénom du conjoint
- "date_naissance" (string) : format "YYYY-MM-DD" ou "DD/MM/YYYY"
- "lieu_naissance" (string) : ville COMPLÈTE (ex: "Marseille")
- "nationalite" (string) : nationalité du conjoint
- "profession" (string) : métier exact (ex: "infirmière", "avocat")
- "situation_actuelle_statut" (string) : "Salarié(e)", "Retraité(e)", "Indépendant(e)", "Demandeur d'emploi"
- "chef_entreprise" (boolean) : true si le conjoint est chef d'entreprise
- "date_evenement_professionnel" (string) : date d'un événement pro
- "risques_professionnels" (boolean) : true/false
- "details_risques_professionnels" (string) : détails sur les risques professionnels
- "telephone" (string) : numéro de téléphone du conjoint
- "adresse" (string) : adresse complète si différente du client

📌 RÈGLES IMPORTANTES :
1. **UNIQUEMENT LE CONJOINT** : N'extrais QUE les informations introduites par "mon conjoint/ma femme/mon mari/elle/il"
2. **JAMAIS LE CLIENT PRINCIPAL** : Si tu vois "je", "moi", "mon métier" (parlant du client) → IGNORE complètement
3. Ne jamais inventer de données
4. Ne remplir un champ QUE si l'information est claire et concerne bien le CONJOINT (pas le client)
5. Respecter l'épellation lettre par lettre si énoncé
6. Si aucune information sur le conjoint n'est mentionnée, retourner un JSON vide : {}
7. Répondre UNIQUEMENT avec du JSON strict, sans texte explicatif

❌ SI LE CLIENT NE PARLE PAS DE SON CONJOINT :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Conjoint détecté avec détails :
"Ma femme s'appelle Sophie Martin, elle est infirmière, née le 20 août 1982"
→ {
  "conjoint": {
    "nom": "Martin",
    "prenom": "Sophie",
    "date_naissance": "1982-08-20",
    "profession": "infirmière"
  }
}

Exemple 2 - Conjoint détecté, infos partielles :
"Mon mari est médecin"
→ {
  "conjoint": {
    "profession": "médecin"
  }
}

Exemple 3 - Pas de conjoint mentionné :
"Je suis architecte, j'ai 45 ans"
→ {}

❌ EXEMPLE À NE PAS FAIRE - Extraire les infos du client principal :
Transcription : "Je m'appelle Jean Dupont, je suis architecte. Ma femme s'appelle Sophie, elle est infirmière."
MAUVAIS → {"conjoint": {"nom": "Dupont", "prenom": "Jean", "profession": "architecte"}}  // ❌ C'est le client !
BON → {"conjoint": {"prenom": "Sophie", "profession": "infirmière"}}  // ✅ Uniquement le conjoint
PROMPT;
    }
}
