<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
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

            Log::info('[ClientRevenusExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientRevenusExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
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

🎯 OBJECTIF :
Détecter et extraire toutes les sources de revenus mentionnées par le client.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS REVENUS :
Salaire, revenus, rémunération, pension, retraite, loyer, revenus locatifs, dividendes, BNC, BIC, revenus fonciers, allocations, indemnités

✅ SI LE CLIENT PARLE DE REVENUS :

Retourne :
{
  "client_revenus": [
    {
      "nature": "salaire|pension|revenus_locatifs|dividendes|autre",
      "periodicite": "mensuel|annuel|trimestriel",
      "montant": 3500.00
    }
  ]
}

📋 CHAMPS pour chaque revenu :
- "nature" (string, requis) : Type de revenu (salaire, pension, revenus locatifs, dividendes, BNC, BIC, autre)
- "periodicite" (string, optionnel) : Fréquence (mensuel, annuel, trimestriel)
- "montant" (decimal, optionnel) : Montant

⚠️ RÈGLES IMPORTANTES :
- Créer une entrée séparée pour chaque source de revenu
- Si plusieurs revenus mentionnés, retourner un array avec plusieurs objets
- Si montant annuel mentionné pour salaire, le noter tel quel avec periodicite="annuel"
- Si montant mensuel, periodicite="mensuel"

❌ SI LE CLIENT NE PARLE PAS DE REVENUS :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Salaire seul :
"Je gagne 3500€ par mois"
→ {"client_revenus": [{"nature": "salaire", "periodicite": "mensuel", "montant": 3500}]}

Exemple 2 - Salaire + revenus locatifs :
"Je touche 4000€ de salaire mensuel et 800€ de loyers"
→ {"client_revenus": [
  {"nature": "salaire", "periodicite": "mensuel", "montant": 4000},
  {"nature": "revenus_locatifs", "periodicite": "mensuel", "montant": 800}
]}

Exemple 3 - Pension de retraite :
"Je perçois 2500€ de retraite par mois"
→ {"client_revenus": [{"nature": "pension", "periodicite": "mensuel", "montant": 2500}]}

Exemple 4 - Revenus annuels :
"Mes revenus annuels sont de 60000€"
→ {"client_revenus": [{"nature": "salaire", "periodicite": "annuel", "montant": 60000}]}

Exemple 5 - Revenus BNC :
"Je suis en BNC avec 80000€ de CA annuel"
→ {"client_revenus": [{"nature": "BNC", "periodicite": "annuel", "montant": 80000}]}

Exemple 6 - Multiples sources :
"J'ai 3000€ de salaire, 500€ de loyers et 200€ de dividendes par mois"
→ {"client_revenus": [
  {"nature": "salaire", "periodicite": "mensuel", "montant": 3000},
  {"nature": "revenus_locatifs", "periodicite": "mensuel", "montant": 500},
  {"nature": "dividendes", "periodicite": "mensuel", "montant": 200}
]}

Exemple 7 - Pas concerné :
"Je veux partir à la retraite à 62 ans"
→ {}
PROMPT;
    }
}
