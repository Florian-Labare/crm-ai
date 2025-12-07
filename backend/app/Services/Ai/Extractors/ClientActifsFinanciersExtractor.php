<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour ACTIFS FINANCIERS CLIENT.
 *
 * Responsabilité :
 * - Extraction des actifs financiers multiples (assurance-vie, PEA, compte-titres, etc.)
 * - Retourne un array d'actifs avec nature, etablissement, detenteur, date, valeur
 */
class ClientActifsFinanciersExtractor
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

            Log::info('[ClientActifsFinanciersExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientActifsFinanciersExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientActifsFinanciersExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte les ACTIFS FINANCIERS du client.

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
Tu es un assistant spécialisé en extraction d'ACTIFS FINANCIERS clients.

🎯 OBJECTIF :
Détecter et extraire tous les actifs financiers mentionnés par le client (assurance-vie, PEA, compte-titres, livrets, etc.).

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS ACTIFS FINANCIERS :
Assurance-vie, PEA, PER, compte-titres, livret A, LDDS, LDD, LEP, livret jeune, CEL, PEL, SCPI, OPCVM, actions, obligations, fonds euro

✅ SI LE CLIENT PARLE D'ACTIFS FINANCIERS :

Retourne :
{
  "client_actifs_financiers": [
    {
      "nature": "assurance-vie|PEA|PER|compte-titres|livret-A|LDDS|PEL|CEL|SCPI|autre",
      "etablissement": "AXA",
      "detenteur": "client|conjoint|commun",
      "date_ouverture_souscription": "2020-01-15",
      "valeur_actuelle": 50000.00
    }
  ]
}

📋 CHAMPS pour chaque actif :
- "nature" (string, requis) : Type de produit (assurance-vie, PEA, PER, compte-titres, livret-A, LDDS, PEL, CEL, SCPI, OPCVM, autre)
- "etablissement" (string, optionnel) : Nom de la banque/assurance
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "date_ouverture_souscription" (date, optionnel) : Date au format YYYY-MM-DD
- "valeur_actuelle" (decimal, optionnel) : Valeur/montant actuel

⚠️ RÈGLES IMPORTANTES :
- Créer une entrée séparée pour chaque produit
- Si plusieurs produits mentionnés, retourner un array avec plusieurs objets
- Si "contrat" ou "assurance vie" → nature = "assurance-vie"
- Si année seulement mentionnée, utiliser YYYY-01-01

❌ SI LE CLIENT NE PARLE PAS D'ACTIFS FINANCIERS :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Assurance-vie :
"J'ai une assurance-vie chez AXA de 50000€ ouverte en 2020"
→ {"client_actifs_financiers": [{"nature": "assurance-vie", "etablissement": "AXA", "valeur_actuelle": 50000, "date_ouverture_souscription": "2020-01-01"}]}

Exemple 2 - PEA :
"J'ai un PEA à la Société Générale avec 30000€"
→ {"client_actifs_financiers": [{"nature": "PEA", "etablissement": "Société Générale", "valeur_actuelle": 30000}]}

Exemple 3 - Multiples produits :
"J'ai un PEA de 20000€ et un livret A de 15000€"
→ {"client_actifs_financiers": [
  {"nature": "PEA", "valeur_actuelle": 20000},
  {"nature": "livret-A", "valeur_actuelle": 15000}
]}

Exemple 4 - SCPI :
"Je possède des parts de SCPI pour 80000€"
→ {"client_actifs_financiers": [{"nature": "SCPI", "valeur_actuelle": 80000}]}

Exemple 5 - Avec détenteur :
"Mon épouse a une assurance-vie de 40000€ chez Generali"
→ {"client_actifs_financiers": [{"nature": "assurance-vie", "etablissement": "Generali", "detenteur": "conjoint", "valeur_actuelle": 40000}]}

Exemple 6 - PER :
"J'ai ouvert un PER en 2022 avec 10000€"
→ {"client_actifs_financiers": [{"nature": "PER", "date_ouverture_souscription": "2022-01-01", "valeur_actuelle": 10000}]}

Exemple 7 - Pas concerné :
"Je veux partir à la retraite à 62 ans"
→ {}
PROMPT;
    }
}
