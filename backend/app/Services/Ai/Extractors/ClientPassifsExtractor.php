<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour PASSIFS CLIENT (prêts, emprunts, dettes).
 *
 * Responsabilité :
 * - Extraction des emprunts multiples (immobilier, consommation, etc.)
 * - Retourne un array de passifs avec nature, preteur, periodicite, montants, durée
 */
class ClientPassifsExtractor
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

            Log::info('[ClientPassifsExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[ClientPassifsExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientPassifsExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte les PRÊTS/EMPRUNTS du client.

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
Tu es un assistant spécialisé en extraction de PASSIFS clients (prêts, emprunts, dettes).

🎯 OBJECTIF :
Détecter et extraire tous les prêts et emprunts mentionnés par le client.

🔤 EPPELLATION / DICTÉE :
- Si une valeur est épelée lettre par lettre (ex: "D U P O N T" ou "D comme David"), reconstruis le mot complet en collant les lettres dans l'ordre.
- Ignore les séparateurs (espaces, tirets, points, pauses).
- Pour email/adresse : "arobase" → "@", "point" → ".", "tiret" → "-", "underscore" → "_".
- Pour téléphone : concatène tous les chiffres en une seule chaîne.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS PASSIFS :
Prêt, emprunt, crédit, dette, mensualité, remboursement, capital restant dû, CRD, échéance, banque, prêteur

✅ SI LE CLIENT PARLE DE PRÊTS :

Retourne :
{
  "client_passifs": [
    {
      "nature": "immobilier|consommation|auto|travaux|autre",
      "preteur": "Crédit Agricole",
      "periodicite": "mensuel|annuel",
      "montant_remboursement": 1200.00,
      "capital_restant_du": 150000.00,
      "duree_restante": 120
    }
  ]
}

📋 CHAMPS pour chaque prêt :
- "nature" (string, requis) : Type de prêt (immobilier, consommation, auto, travaux, professionnel, autre)
- "preteur" (string, optionnel) : Nom de la banque/organisme prêteur
- "periodicite" (string, optionnel) : Fréquence des remboursements (mensuel, annuel)
- "montant_remboursement" (decimal, optionnel) : Montant de l'échéance
- "capital_restant_du" (decimal, optionnel) : Capital restant dû (CRD)
- "duree_restante" (integer, optionnel) : Durée restante en mois

⚠️ RÈGLES IMPORTANTES :
- Créer une entrée séparée pour chaque prêt
- Si plusieurs prêts mentionnés, retourner un array avec plusieurs objets
- Convertir les années en mois pour duree_restante (ex: 10 ans = 120 mois)
- Si "crédit immobilier" ou "prêt immo" → nature = "immobilier"
- Si "crédit conso" ou "prêt personnel" → nature = "consommation"

❌ SI LE CLIENT NE PARLE PAS DE PRÊTS :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Crédit immobilier :
"J'ai un crédit immobilier au Crédit Agricole, je paie 1200€ par mois et il me reste 150000€ sur 10 ans"
→ {"client_passifs": [{"nature": "immobilier", "preteur": "Crédit Agricole", "periodicite": "mensuel", "montant_remboursement": 1200, "capital_restant_du": 150000, "duree_restante": 120}]}

Exemple 2 - Crédit auto :
"J'ai un prêt auto de 300€ par mois pendant encore 3 ans"
→ {"client_passifs": [{"nature": "auto", "periodicite": "mensuel", "montant_remboursement": 300, "duree_restante": 36}]}

Exemple 3 - Multiples crédits :
"J'ai un crédit immo de 1500€/mois avec 200000€ restants, et un crédit conso de 200€/mois"
→ {"client_passifs": [
  {"nature": "immobilier", "periodicite": "mensuel", "montant_remboursement": 1500, "capital_restant_du": 200000},
  {"nature": "consommation", "periodicite": "mensuel", "montant_remboursement": 200}
]}

Exemple 4 - Crédit professionnel :
"J'ai un prêt professionnel à la BNP de 80000€ restants"
→ {"client_passifs": [{"nature": "professionnel", "preteur": "BNP", "capital_restant_du": 80000}]}

Exemple 5 - Pas de prêt :
"Je n'ai aucun crédit en cours"
→ {}

Exemple 6 - Pas concerné :
"Je veux optimiser mon patrimoine"
→ {}
PROMPT;
    }
}
