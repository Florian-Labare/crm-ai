<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour REVENUS CLIENT.
 *
 * Responsabilité :
 * - Extraction des sources de revenus multiples (salaires, pensions, revenus locatifs, etc.)
 * - Retourne un array de revenus avec nature, periodicite, montant
 */
class ClientRevenusExtractor
{
    use LlmClientTrait;

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

            if (! is_array($data)) {
                Log::warning('[ClientRevenusExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientRevenusExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte les REVENUS du client.

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
Tu es un assistant spécialisé en extraction de REVENUS clients.

[OBJECTIF]
Détecter et extraire toutes les sources de revenus mentionnées par le client.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS REVENUS]
Salaire, revenus, rémunération, pension, retraite, loyer, revenus locatifs, dividendes, BNC, BIC, revenus fonciers, allocations, indemnités, SCI, SCPI, rente, fermage

[SI DÉTECTÉ - REVENUS]

Retourne :
{
  "client_revenus": [
    {
      "nature": "salaire|pension|revenus_locatifs|dividendes|SCI|SCPI|BNC|BIC|autre",
      "details": "précision si nature=autre",
      "periodicite": "mensuel|annuel|trimestriel",
      "montant": 3500.00
    }
  ]
}

[CHAMPS pour chaque revenu]
- "nature" (string, requis) : Type de revenu
  - "salaire" : revenus salariaux, rémunération
  - "pension" : retraite, pension de réversion
  - "revenus_locatifs" : loyers perçus sur immobilier en direct
  - "SCI" : revenus de Société Civile Immobilière
  - "SCPI" : revenus de parts de SCPI
  - "dividendes" : dividendes d'actions ou parts sociales
  - "BNC" : Bénéfices Non Commerciaux (professions libérales)
  - "BIC" : Bénéfices Industriels et Commerciaux
  - "autre" : tout autre type de revenu non listé ci-dessus
- "details" (string, optionnel) : Précision, OBLIGATOIRE si nature="autre"
- "periodicite" (string, optionnel) : mensuel, annuel, trimestriel
- "montant" (decimal, optionnel) : Montant

[RÈGLES IMPORTANTES]
- Créer une entrée séparée pour CHAQUE source de revenu
- Si plusieurs revenus mentionnés, retourner un array avec plusieurs objets
- Si montant annuel mentionné, periodicite="annuel"
- Si montant mensuel, periodicite="mensuel"
- Les revenus de SCI/SCPI sont généralement annuels

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "Je gagne 3500€ par mois"
Output: {"client_revenus": [{"nature": "salaire", "periodicite": "mensuel", "montant": 3500}]}

Input: "Je touche 4000€ de salaire mensuel et 800€ de loyers"
Output: {"client_revenus": [{"nature": "salaire", "periodicite": "mensuel", "montant": 4000}, {"nature": "revenus_locatifs", "periodicite": "mensuel", "montant": 800}]}

Input: "Je perçois 2500€ de retraite par mois"
Output: {"client_revenus": [{"nature": "pension", "periodicite": "mensuel", "montant": 2500}]}

Input: "Mes revenus annuels sont de 60000€"
Output: {"client_revenus": [{"nature": "salaire", "periodicite": "annuel", "montant": 60000}]}

Input: "Je suis en BNC avec 80000€ de CA annuel"
Output: {"client_revenus": [{"nature": "BNC", "periodicite": "annuel", "montant": 80000}]}

Input: "J'ai une SCI qui me rapporte 25000 euros par an"
Output: {"client_revenus": [{"nature": "SCI", "periodicite": "annuel", "montant": 25000}]}

Input: "Je touche une rente viagère de 500€ par mois"
Output: {"client_revenus": [{"nature": "autre", "details": "rente viagère", "periodicite": "mensuel", "montant": 500}]}

Input: "Je veux partir à la retraite à 62 ans"
Output: {}
PROMPT;
    }
}
