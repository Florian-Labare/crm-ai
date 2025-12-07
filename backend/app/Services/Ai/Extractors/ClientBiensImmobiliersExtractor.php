<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour BIENS IMMOBILIERS CLIENT.
 *
 * Responsabilité :
 * - Extraction des biens immobiliers multiples (résidence principale, secondaire, locatif, etc.)
 * - Retourne un array de biens avec designation, detenteur, forme, valeurs, année
 */
class ClientBiensImmobiliersExtractor
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

            Log::info('[ClientBiensImmobiliersExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientBiensImmobiliersExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientBiensImmobiliersExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte les BIENS IMMOBILIERS du client.

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
Tu es un assistant spécialisé en extraction de BIENS IMMOBILIERS clients.

🎯 OBJECTIF :
Détecter et extraire tous les biens immobiliers mentionnés par le client (résidence principale, secondaire, locatif, etc.).

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS IMMOBILIER :
Maison, appartement, résidence principale, résidence secondaire, bien locatif, immeuble, terrain, propriété, SCI, indivision, pleine propriété, nue-propriété, usufruit

✅ SI LE CLIENT PARLE DE BIENS IMMOBILIERS :

Retourne :
{
  "client_biens_immobiliers": [
    {
      "designation": "Résidence principale - Maison à Paris",
      "detenteur": "client|conjoint|commun",
      "forme_propriete": "pleine-propriete|indivision|SCI|nue-propriete|usufruit",
      "valeur_actuelle_estimee": 400000.00,
      "annee_acquisition": 2015,
      "valeur_acquisition": 350000.00
    }
  ]
}

📋 CHAMPS pour chaque bien :
- "designation" (string, requis) : Description du bien (type + localisation si mentionnée)
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "forme_propriete" (string, optionnel) : pleine-propriete, indivision, SCI, nue-propriete, usufruit
- "valeur_actuelle_estimee" (decimal, optionnel) : Valeur estimée actuelle
- "annee_acquisition" (integer, optionnel) : Année d'achat
- "valeur_acquisition" (decimal, optionnel) : Prix d'achat

⚠️ RÈGLES IMPORTANTES :
- Créer une entrée séparée pour chaque bien immobilier
- Si plusieurs biens mentionnés, retourner un array avec plusieurs objets
- Inclure le type de bien dans la désignation (maison, appartement, terrain, etc.)
- Si localisation mentionnée, l'inclure dans la désignation

❌ SI LE CLIENT NE PARLE PAS DE BIENS IMMOBILIERS :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Résidence principale :
"Ma maison principale vaut 400000€, je l'ai achetée 350000€ en 2015"
→ {"client_biens_immobiliers": [{"designation": "Résidence principale - Maison", "valeur_actuelle_estimee": 400000, "annee_acquisition": 2015, "valeur_acquisition": 350000}]}

Exemple 2 - Avec localisation :
"J'ai un appartement à Paris estimé à 500000€"
→ {"client_biens_immobiliers": [{"designation": "Appartement à Paris", "valeur_actuelle_estimee": 500000}]}

Exemple 3 - Bien locatif :
"Je possède un studio en location à Lyon, acheté 120000€ en 2018, qui vaut maintenant 150000€"
→ {"client_biens_immobiliers": [{"designation": "Studio locatif à Lyon", "valeur_actuelle_estimee": 150000, "annee_acquisition": 2018, "valeur_acquisition": 120000}]}

Exemple 4 - Multiples biens :
"J'ai ma maison principale de 400000€ et une résidence secondaire de 200000€"
→ {"client_biens_immobiliers": [
  {"designation": "Résidence principale - Maison", "valeur_actuelle_estimee": 400000},
  {"designation": "Résidence secondaire", "valeur_actuelle_estimee": 200000}
]}

Exemple 5 - SCI :
"J'ai un immeuble en SCI estimé à 800000€"
→ {"client_biens_immobiliers": [{"designation": "Immeuble", "forme_propriete": "SCI", "valeur_actuelle_estimee": 800000}]}

Exemple 6 - Indivision :
"Mon conjoint et moi possédons en indivision un appartement de 350000€"
→ {"client_biens_immobiliers": [{"designation": "Appartement", "detenteur": "commun", "forme_propriete": "indivision", "valeur_actuelle_estimee": 350000}]}

Exemple 7 - Terrain :
"Je possède un terrain à construire acheté 80000€ en 2020"
→ {"client_biens_immobiliers": [{"designation": "Terrain à construire", "annee_acquisition": 2020, "valeur_acquisition": 80000}]}

Exemple 8 - Pas concerné :
"Je veux optimiser mon patrimoine financier"
→ {}
PROMPT;
    }
}
