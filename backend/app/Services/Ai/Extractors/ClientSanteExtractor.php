<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour SANTÉ (complémentaire santé / mutuelle).
 *
 * Responsabilité :
 * - Détection du besoin "santé"
 * - Extraction des données sante_souhait
 * - TOUJOURS utiliser "add" pour besoins_action (sauf négation explicite)
 */
class ClientSanteExtractor
{
    use LlmClientTrait;

    /**
     * Extrait les données de santé depuis la transcription.
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
                Log::warning('[ClientSanteExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientSanteExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte si le client parle de SANTÉ / MUTUELLE / COMPLÉMENTAIRE SANTÉ.

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide, sans aucun texte avant ou après.
PROMPT;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de besoins SANTÉ (complémentaire santé / mutuelle).

[OBJECTIF]
Détecter si le client exprime un besoin de complémentaire santé et extraire les données associées.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS SANTÉ]
Mutuelle, complémentaire santé, couverture santé, frais médicaux, hospitalisation, dentaire, optique, lunettes, soins, remboursements, médecin, pharmacie, prothèses, santé

[SI DÉTECTÉ - SANTÉ]

Retourne :
{
  "besoins": ["santé"],
  "besoins_action": "add",
  "sante_souhait": {
    // Champs ci-dessous SEULEMENT si mentionnés
  }
}

[CHAMPS sante_souhait] (tous optionnels)
- "contrat_en_place" (string) : "oui" ou "non" - si le client a déjà un contrat santé
- "budget_mensuel_maximum" (decimal) : budget mensuel maximum en euros
- "niveau_hospitalisation" (integer 0-10) : niveau de couverture souhaité
- "niveau_chambre_particuliere" (integer 0-10)
- "niveau_medecin_generaliste" (integer 0-10)
- "niveau_analyses_imagerie" (integer 0-10)
- "niveau_auxiliaires_medicaux" (integer 0-10)
- "niveau_pharmacie" (integer 0-10)
- "niveau_dentaire" (integer 0-10)
- "niveau_optique" (integer 0-10)
- "niveau_protheses_auditives" (integer 0-10)
- "souhaite_medecine_douce" (boolean)
- "souhaite_cures_thermales" (boolean)
- "souhaite_autres_protheses" (boolean)
- "souhaite_protection_juridique" (boolean)
- "souhaite_protection_juridique_conjoint" (boolean)

[RÈGLE CRITIQUE - besoins_action]
- Par défaut : "add" (TOUJOURS)
- "remove" UNIQUEMENT si le client dit : "je n'ai PLUS besoin de mutuelle", "supprimez la santé"
- NE JAMAIS utiliser "replace"

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "J'ai besoin d'une mutuelle avec un budget de 50 euros par mois"
Output: {"besoins": ["santé"], "besoins_action": "add", "sante_souhait": {"budget_mensuel_maximum": 50}}

Input: "Je cherche une complémentaire santé, j'ai déjà un contrat en place"
Output: {"besoins": ["santé"], "besoins_action": "add", "sante_souhait": {"contrat_en_place": "oui"}}

Input: "J'ai besoin d'un contrat santé, mon budget maximum c'est 550 euros"
Output: {"besoins": ["santé"], "besoins_action": "add", "sante_souhait": {"budget_mensuel_maximum": 550}}

Input: "Je veux une bonne couverture dentaire et optique"
Output: {"besoins": ["santé"], "besoins_action": "add", "sante_souhait": {"niveau_dentaire": 8, "niveau_optique": 8}}

Input: "Je n'ai plus besoin de mutuelle"
Output: {"besoins": ["santé"], "besoins_action": "remove"}

Input: "Je veux préparer ma retraite"
Output: {}
PROMPT;
    }
}
