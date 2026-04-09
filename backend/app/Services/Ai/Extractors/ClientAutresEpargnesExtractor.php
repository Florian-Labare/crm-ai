<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
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
                        'temperature' => 0.1,
                        'response_format' => ['type' => 'json_object'],
                    ]);

            $json = $response->json();
            $raw = $json['choices'][0]['message']['content'] ?? '';

            Log::info('[ClientAutresEpargnesExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientAutresEpargnesExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
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

🎯 OBJECTIF :
Détecter et extraire les autres formes d'épargne non catégorisées ailleurs (or, cryptomonnaies, objets de valeur, collections, etc.).

🔤 EPPELLATION / DICTÉE :
- Si une valeur est épelée lettre par lettre (ex: "D U P O N T" ou "D comme David"), reconstruis le mot complet en collant les lettres dans l'ordre.
- Ignore les séparateurs (espaces, tirets, points, pauses).
- Pour email/adresse : "arobase" → "@", "point" → ".", "tiret" → "-", "underscore" → "_".
- Pour téléphone : concatène tous les chiffres en une seule chaîne.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client
- N'EXTRAIT PAS les produits financiers classiques (assurance-vie, PEA, livrets) → ils sont gérés par ClientActifsFinanciersExtractor
- N'EXTRAIT PAS l'immobilier → il est géré par ClientBiensImmobiliersExtractor

🔍 MOTS-CLÉS AUTRES ÉPARGNES (ACTIFS ALTERNATIFS) :
- CRYPTOMONNAIES : crypto, Bitcoin, BTC, Ethereum, ETH, Solana, Ripple, XRP, Cardano, Dogecoin, NFT, token, altcoin, wallet crypto
- MÉTAUX PRÉCIEUX : or, lingot, lingots, pièces d'or, argent métal, platine, napoléon, once d'or
- ART & COLLECTIONS : objets d'art, tableaux, sculptures, œuvres d'art, collection de timbres, numismatique, montres de luxe, vins, antiquités
- BIJOUX : bijoux, diamants, pierres précieuses, joaillerie
- AUTRES : argent liquide, cash, espèces

✅ SI LE CLIENT PARLE D'AUTRES ÉPARGNES :

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

📋 CHAMPS pour chaque épargne :
- "designation" (string, requis) : Description de l'épargne (or, crypto, objets d'art, etc.)
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "valeur" (decimal, optionnel) : Valeur estimée

⚠️ RÈGLES IMPORTANTES :
- Créer une entrée séparée pour chaque type d'épargne
- Si plusieurs formes mentionnées, retourner un array avec plusieurs objets
- Être spécifique dans la désignation (ex: "Bitcoin" plutôt que "crypto")

❌ SI LE CLIENT NE PARLE PAS D'AUTRES ÉPARGNES :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Or :
"J'ai 15000€ de lingots d'or"
→ {"client_autres_epargnes": [{"designation": "Lingots d'or", "valeur": 15000}]}

Exemple 2 - Cryptomonnaies :
"Je possède du Bitcoin pour environ 20000€"
→ {"client_autres_epargnes": [{"designation": "Bitcoin", "valeur": 20000}]}

Exemple 3 - Objets d'art :
"J'ai une collection d'œuvres d'art estimée à 50000€"
→ {"client_autres_epargnes": [{"designation": "Collection d'œuvres d'art", "valeur": 50000}]}

Exemple 4 - Multiples :
"J'ai de l'or pour 10000€ et des cryptos pour 15000€"
→ {"client_autres_epargnes": [
  {"designation": "Or", "valeur": 10000},
  {"designation": "Cryptomonnaies", "valeur": 15000}
]}

Exemple 5 - Argent liquide :
"Je garde 5000€ en liquide à la maison"
→ {"client_autres_epargnes": [{"designation": "Argent liquide", "valeur": 5000}]}

Exemple 6 - Collection :
"Ma collection de timbres vaut environ 8000€"
→ {"client_autres_epargnes": [{"designation": "Collection de timbres", "valeur": 8000}]}

Exemple 7 - Avec détenteur :
"Mon épouse a des bijoux de famille estimés à 12000€"
→ {"client_autres_epargnes": [{"designation": "Bijoux de famille", "detenteur": "conjoint", "valeur": 12000}]}

Exemple 8 - À IGNORER (produit financier classique) :
"J'ai une assurance-vie de 50000€"
→ {} (sera géré par ClientActifsFinanciersExtractor)

Exemple 9 - À IGNORER (immobilier) :
"Ma maison vaut 400000€"
→ {} (sera géré par ClientBiensImmobiliersExtractor)

Exemple 10 - Pas concerné :
"Je veux optimiser ma retraite"
→ {}
PROMPT;
    }
}
