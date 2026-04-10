<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour AUTRES ÉPARGNES CLIENT.
 *
 * Responsabilité :
 * - Extraction des autres formes d'épargne non catégorisées (or, crypto, objets de valeur, etc.)
 * - Retourne un array d'épargnes avec designation, detenteur, valeur
 */
class ClientAutresEpargnesExtractor
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
                Log::warning('[ClientAutresEpargnesExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientAutresEpargnesExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte les AUTRES FORMES D'ÉPARGNE du client (or, crypto, objets de valeur, etc.).

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
Tu es un assistant spécialisé en extraction d'AUTRES ÉPARGNES clients.

[OBJECTIF]
Détecter et extraire les autres formes d'épargne non catégorisées ailleurs (or, cryptomonnaies, objets de valeur, collections, etc.).

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client
- N'EXTRAIT PAS les produits financiers classiques (assurance-vie, PEA, livrets) = ClientActifsFinanciersExtractor
- N'EXTRAIT PAS l'immobilier = ClientBiensImmobiliersExtractor

[MOTS-CLÉS AUTRES ÉPARGNES]
- CRYPTOMONNAIES : crypto, Bitcoin, BTC, Ethereum, ETH, Solana, Ripple, XRP, Cardano, Dogecoin, NFT, token, altcoin, wallet crypto
- MÉTAUX PRÉCIEUX : or, lingot, lingots, pièces d'or, argent métal, platine, napoléon, once d'or
- ART & COLLECTIONS : objets d'art, tableaux, sculptures, œuvres d'art, collection de timbres, numismatique, montres de luxe, vins, antiquités
- BIJOUX : bijoux, diamants, pierres précieuses, joaillerie
- AUTRES : argent liquide, cash, espèces

[SI DÉTECTÉ - AUTRES ÉPARGNES]

Retourne :
{
  "client_autres_epargnes": [
    {
      "designation": "Lingots d'or",
      "detenteur": "client|conjoint|commun",
      "valeur": 15000.00
    }
  ]
}

[CHAMPS pour chaque épargne]
- "designation" (string, requis) : Description de l'épargne (or, crypto, objets d'art, etc.)
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "valeur" (decimal, optionnel) : Valeur estimée

[RÈGLES IMPORTANTES]
- Créer une entrée séparée pour chaque type d'épargne
- Si plusieurs formes mentionnées, retourner un array avec plusieurs objets
- Être spécifique dans la désignation (ex: "Bitcoin" plutôt que "crypto")

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "J'ai 15000€ de lingots d'or"
Output: {"client_autres_epargnes": [{"designation": "Lingots d'or", "valeur": 15000}]}

Input: "Je possède du Bitcoin pour environ 20000€"
Output: {"client_autres_epargnes": [{"designation": "Bitcoin", "valeur": 20000}]}

Input: "J'ai une collection d'œuvres d'art estimée à 50000€"
Output: {"client_autres_epargnes": [{"designation": "Collection d'œuvres d'art", "valeur": 50000}]}

Input: "J'ai de l'or pour 10000€ et des cryptos pour 15000€"
Output: {"client_autres_epargnes": [{"designation": "Or", "valeur": 10000}, {"designation": "Cryptomonnaies", "valeur": 15000}]}

Input: "Je garde 5000€ en liquide à la maison"
Output: {"client_autres_epargnes": [{"designation": "Argent liquide", "valeur": 5000}]}

Input: "Mon épouse a des bijoux de famille estimés à 12000€"
Output: {"client_autres_epargnes": [{"designation": "Bijoux de famille", "detenteur": "conjoint", "valeur": 12000}]}

Input: "J'ai une assurance-vie de 50000€"
Output: {}
Note: Produit financier classique, géré par ClientActifsFinanciersExtractor

Input: "Ma maison vaut 400000€"
Output: {}
Note: Immobilier, géré par ClientBiensImmobiliersExtractor

Input: "Je veux optimiser ma retraite"
Output: {}
PROMPT;
    }
}
