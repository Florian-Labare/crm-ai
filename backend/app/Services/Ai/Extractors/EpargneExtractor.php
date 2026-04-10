<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour ÉPARGNE.
 *
 * Responsabilité :
 * - Détection du besoin "épargne"
 * - Extraction des données bae_epargne
 * - TOUJOURS utiliser "add" pour besoins_action (sauf négation explicite)
 */
class EpargneExtractor {
    use LlmClientTrait;

    public function extract(string $transcription, array $currentData = []): array {
        $prompt = $this->buildPrompt($transcription);

        try {
            $data = $this->callLlm(
                $this->getSystemPrompt(),
                $prompt,
                0.1,
                true
            );

            if (! is_array($data)) {
                Log::warning('[EpargneExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[EpargneExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $transcription): string {
        return <<<PROMPT
Analyse cette transcription et détecte si le client parle d'ÉPARGNE.

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide, sans aucun texte avant ou après.
PROMPT;
    }

    private function getSystemPrompt(): string {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de besoins ÉPARGNE.

[OBJECTIF]
Détecter si le client exprime un besoin d'épargne et extraire les données associées.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS ÉPARGNE]
Épargne, patrimoine, placements, investissements, assurance vie, PEA, livret, actifs, résidence principale, résidence secondaire, immobilier, locatif, crédit, emprunt, donation, succession, capacité d'épargne

[SI DÉTECTÉ - ÉPARGNE]

Retourne :
{
  "besoins": ["épargne"],
  "besoins_action": "add",
  "bae_epargne": {
    // Champs ci-dessous SEULEMENT si mentionnés
  }
}

[CHAMPS bae_epargne] (tous optionnels)
- "epargne_disponible" (boolean) : true si le client a de l'épargne
- "montant_epargne_disponible" (decimal) : montant total épargné
- "donation_realisee" (boolean) : true si donation effectuée
- "donation_forme" (string) : forme de la donation
- "donation_date" (string) : date de la donation
- "donation_montant" (decimal) : montant de la donation
- "donation_beneficiaires" (string) : bénéficiaires
- "capacite_epargne_estimee" (decimal) : capacité mensuelle d'épargne
- "actifs_financiers_pourcentage" (decimal) : % actifs financiers
- "actifs_financiers_total" (decimal) : total actifs financiers
- "actifs_financiers_details" (array) : ["assurance vie: 30000", "PEA: 20000"]
- "actifs_immo_pourcentage" (decimal) : % actifs immobiliers
- "actifs_immo_total" (decimal) : total actifs immobiliers
- "actifs_immo_details" (array) : ["résidence principale: 300000"]
- "actifs_autres_pourcentage" (decimal)
- "actifs_autres_total" (decimal)
- "actifs_autres_details" (array)
- "passifs_total_emprunts" (decimal) : total des emprunts
- "passifs_details" (array) : ["crédit immobilier: 150000"]
- "charges_totales" (decimal)
- "charges_details" (array) : ["loyer: 1000", "électricité: 150"]
- "situation_financiere_revenus_charges" (text)

[RÈGLE CRITIQUE - besoins_action]
- Par défaut : "add" (TOUJOURS)
- "remove" UNIQUEMENT si le client dit : "je n'ai PLUS besoin d'épargne", "supprimez l'épargne"
- NE JAMAIS utiliser "replace"

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "J'ai 50000€ d'épargne disponible"
Output: {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"epargne_disponible": true, "montant_epargne_disponible": 50000}}

Input: "Je peux épargner 500€ par mois"
Output: {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"capacite_epargne_estimee": 500}}

Input: "Ma résidence principale vaut 300000€ et j'ai un crédit de 150000€"
Output: {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {"actifs_immo_total": 300000, "actifs_immo_details": ["résidence principale: 300000"], "passifs_total_emprunts": 150000, "passifs_details": ["crédit immobilier: 150000"]}}

Input: "Je veux optimiser mon patrimoine"
Output: {"besoins": ["épargne"], "besoins_action": "add", "bae_epargne": {}}

Input: "Je n'ai plus besoin d'épargne"
Output: {"besoins": ["épargne"], "besoins_action": "remove"}

Input: "Je veux partir à la retraite à 62 ans"
Output: {}
PROMPT;
    }
}
