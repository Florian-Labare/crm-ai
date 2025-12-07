<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour PRÉVOYANCE.
 * 
 * Responsabilité :
 * - Détection du besoin "prévoyance"
 * - Extraction des données bae_prevoyance
 * - TOUJOURS utiliser "add" pour besoins_action (sauf négation explicite)
 */
class PrevoyanceExtractor
{
    /**
     * Extrait les données de prévoyance depuis la transcription.
     *
     * @param string $transcription Transcription vocale
     * @param array $currentData Données existantes (optionnel)
     * @return array Données extraites
     */
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

            Log::info('[PrevoyanceExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[PrevoyanceExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[PrevoyanceExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte si le client parle de PRÉVOYANCE.

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
Tu es un assistant spécialisé en extraction de besoins PRÉVOYANCE.

🎯 OBJECTIF :
Détecter si le client exprime un besoin de prévoyance et extraire les données associées.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS PRÉVOYANCE :
Invalidité, ITT, incapacité, arrêt de travail, décès, capital décès, obsèques, rente conjoint, rente enfants, charges professionnelles, protection, accident, maladie grave, indemnités journalières

✅ SI LE CLIENT PARLE DE PRÉVOYANCE :

Retourne :
{
  "besoins": ["prévoyance"],
  "besoins_action": "add",
  "bae_prevoyance": {
    // Remplis les champs ci-dessous SEULEMENT si mentionnés
  }
}

📋 CHAMPS bae_prevoyance (optionnels) :
- "contrat_en_place" (string) : nom du contrat existant
- "date_effet" (string) : date d'effet du contrat
- "cotisations" (decimal) : montant des cotisations
- "souhaite_couverture_invalidite" (boolean) : true si mention invalidité/ITT
- "revenu_a_garantir" (decimal) : revenu mensuel à garantir
- "souhaite_couvrir_charges_professionnelles" (boolean)
- "montant_annuel_charges_professionnelles" (decimal)
- "garantir_totalite_charges_professionnelles" (boolean)
- "montant_charges_professionnelles_a_garantir" (decimal)
- "duree_indemnisation_souhaitee" (string) : ex "3 ans", "jusqu'à la retraite"
- "capital_deces_souhaite" (decimal)
- "garanties_obseques" (decimal)
- "rente_enfants" (decimal)
- "rente_conjoint" (decimal)
- "payeur" (string) : qui paie les cotisations

⚠️ RÈGLE CRITIQUE - besoins_action :
- Par défaut : "add" (TOUJOURS)
- "remove" UNIQUEMENT si le client dit : "je n'ai PLUS besoin de prévoyance", "supprimez la prévoyance"
- NE JAMAIS utiliser "replace"

❌ SI LE CLIENT NE PARLE PAS DE PRÉVOYANCE :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Besoin détecté :
"Je veux garantir 3000€ par mois en cas d'invalidité"
→ {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {"souhaite_couverture_invalidite": true, "revenu_a_garantir": 3000}}

Exemple 2 - Besoin générique :
"J'ai besoin d'une prévoyance"
→ {"besoins": ["prévoyance"], "besoins_action": "add", "bae_prevoyance": {}}

Exemple 3 - Négation :
"Je n'ai plus besoin de prévoyance"
→ {"besoins": ["prévoyance"], "besoins_action": "remove"}

Exemple 4 - Pas concerné :
"Je veux préparer ma retraite"
→ {}
PROMPT;
    }
}
