<?php

namespace App\Services\Ai\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour RETRAITE.
 * 
 * Responsabilité :
 * - Détection du besoin "retraite"
 * - Extraction des données bae_retraite
 * - TOUJOURS utiliser "add" pour besoins_action (sauf négation explicite)
 */
class RetraiteExtractor
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

            Log::info('[RetraiteExtractor] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('[RetraiteExtractor] Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[RetraiteExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détecte si le client parle de RETRAITE.

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
Tu es un assistant spécialisé en extraction de besoins RETRAITE.

🎯 OBJECTIF :
Détecter si le client exprime un besoin de retraite et extraire les données associées.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

🔍 MOTS-CLÉS RETRAITE :
Retraite, pension, PER, PERP, complément retraite, départ retraite, maintenir revenus retraite, préparer retraite, âge de départ, trimestres, régime retraite, épargne retraite

✅ SI LE CLIENT PARLE DE RETRAITE :

Retourne :
{
  "besoins": ["retraite"],
  "besoins_action": "add",
  "bae_retraite": {
    // Remplis les champs ci-dessous SEULEMENT si mentionnés
  }
}

📋 CHAMPS bae_retraite (optionnels) :
- "revenus_annuels" (decimal) : revenus annuels du client
- "revenus_annuels_foyer" (decimal) : revenus du foyer
- "impot_revenu" (decimal) : impôt sur le revenu
- "nombre_parts_fiscales" (decimal) : nombre de parts fiscales
- "tmi" (string) : Tranche Marginale d'Imposition (ex: "30%")
- "impot_paye_n_1" (decimal) : impôt payé l'année dernière
- "age_depart_retraite" (integer) : âge de départ souhaité
- "age_depart_retraite_conjoint" (integer) : âge de départ du conjoint
- "pourcentage_revenu_a_maintenir" (decimal) : % du revenu actuel à maintenir
- "contrat_en_place" (string) : nom du contrat existant (PER, PERP, etc.)
- "bilan_retraite_disponible" (boolean) : true si le client a son relevé de carrière
- "complementaire_retraite_mise_en_place" (boolean) : true si produit déjà en place
- "designation_etablissement" (string) : assureur/banque/organisme
- "cotisations_annuelles" (decimal) : montant des cotisations annuelles
- "titulaire" (string) : titulaire du contrat

⚠️ RÈGLE CRITIQUE - besoins_action :
- Par défaut : "add" (TOUJOURS)
- "remove" UNIQUEMENT si le client dit : "je n'ai PLUS besoin de retraite", "supprimez la retraite"
- NE JAMAIS utiliser "replace"

❌ SI LE CLIENT NE PARLE PAS DE RETRAITE :
Retourne un objet vide :
{}

📌 EXEMPLES :

Exemple 1 - Besoin détaillé :
"Je veux partir à la retraite à 62 ans et maintenir 70% de mes revenus"
→ {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"age_depart_retraite": 62, "pourcentage_revenu_a_maintenir": 70}}

Exemple 2 - Avec TMI et revenus foyer :
"Mon TMI est de 30%. Le revenu foyer est de 80000 euros."
→ {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"tmi": "30%", "revenus_annuels_foyer": 80000}}

Exemple 3 - Besoin générique :
"Je veux préparer ma retraite"
→ {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {}}

Exemple 4 - Négation :
"Je n'ai plus besoin de retraite"
→ {"besoins": ["retraite"], "besoins_action": "remove"}

Exemple 5 - Pas concerné :
"Je veux garantir 3000€ en cas d'invalidité"
→ {}
PROMPT;
    }
}
