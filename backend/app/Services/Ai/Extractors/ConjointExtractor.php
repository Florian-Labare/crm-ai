<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
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
    use LlmClientTrait;

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
            $data = $this->callLlm(
                $this->getSystemPrompt(),
                $prompt,
                0.1,
                true
            );

            if (!is_array($data)) {
                Log::warning('[ConjointExtractor] Impossible de parser la réponse LLM');

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

IMPORTANT :
- Cherche les mentions : "mon conjoint", "ma femme", "mon mari", "mon épouse", "elle/il" (parlant du conjoint)
- N'extrais JAMAIS des informations d'enfants ("mon fils", "ma fille", "mes enfants" = PAS des conjoints)
- IGNORE complètement les informations du client principal (celui qui dit "je", "moi")

Transcription :
---
$transcription
---

Réponds UNIQUEMENT avec un JSON valide concernant le conjoint (ou {} si aucune info), sans texte avant ou après.
PROMPT;
    }

    /**
     * Retourne le prompt système pour l'extraction du conjoint.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de données CONJOINT pour un CRM d'assurance.

[OBJECTIF]
Détecter si le client parle de son CONJOINT et extraire les données associées.

[ÉPELLATION / DICTÉE]
- Si une valeur est épelée lettre par lettre (ex: "D U P O N T"), reconstruis le mot complet.
- Pour email : "arobase" = "@", "point" = ".", "tiret" = "-", "underscore" = "_"
- Pour téléphone : concatène tous les chiffres en une seule chaîne.

[RÈGLES ABSOLUES]

1. N'extrais QUE le CONJOINT : informations introduites par "mon conjoint", "ma femme", "mon mari", "mon épouse", "mon époux", "ma/mon partenaire", "elle/il" (contexte conjoint)

2. IGNORE TOTALEMENT le CLIENT PRINCIPAL : "je m'appelle...", "je suis...", "mon métier..." = client, pas conjoint

3. IGNORE LES ENFANTS : "mon fils", "ma fille", "mes enfants" = PAS des conjoints

4. En cas de doute sur qui est concerné : N'extrais PAS l'information

[SI DÉTECTÉ - CONJOINT]

Retourne :
{
  "conjoint": {
    // Champs ci-dessous SEULEMENT si mentionnés
  }
}

[CHAMPS conjoint] (tous optionnels)

- "nom" (string) : nom de famille du conjoint
- "nom_jeune_fille" (string) : nom de jeune fille si applicable
- "prenom" (string) : prénom du conjoint
- "date_naissance" (string) : format "YYYY-MM-DD"
- "lieu_naissance" (string) : ville complète
- "nationalite" (string) : nationalité
- "profession" (string) : métier exact (ex: "infirmière", "avocat")
- "situation_actuelle_statut" (string) : "Salarié(e)", "Retraité(e)", "Indépendant(e)", "Demandeur d'emploi"
- "chef_entreprise" (boolean) : true si chef d'entreprise
- "date_evenement_professionnel" (string) : date d'un événement pro
- "risques_professionnels" (boolean)
- "details_risques_professionnels" (string)
- "telephone" (string)
- "adresse" (string) : si différente du client

[RÈGLES IMPORTANTES]
1. UNIQUEMENT le CONJOINT : informations introduites par "mon conjoint/ma femme/mon mari/elle/il"
2. JAMAIS le CLIENT PRINCIPAL : "je", "moi", "mon métier" (parlant du client) = IGNORER
3. Ne jamais inventer de données
4. Ne remplir un champ QUE si l'information est claire et concerne le CONJOINT
5. Respecter l'épellation lettre par lettre
6. Si aucune information sur le conjoint, retourner : {}
7. Répondre UNIQUEMENT avec du JSON valide

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "Ma femme s'appelle Sophie Martin, elle est infirmière, née le 20 août 1982"
Output: {"conjoint": {"nom": "Martin", "prenom": "Sophie", "date_naissance": "1982-08-20", "profession": "infirmière"}}

Input: "Mon mari est médecin"
Output: {"conjoint": {"profession": "médecin"}}

Input: "Je suis architecte, j'ai 45 ans"
Output: {}

Input: "Je m'appelle Jean Dupont, je suis architecte. Ma femme s'appelle Sophie, elle est infirmière."
Output: {"conjoint": {"prenom": "Sophie", "profession": "infirmière"}}
Note: Jean Dupont est le client principal, pas le conjoint.
PROMPT;
    }
}
