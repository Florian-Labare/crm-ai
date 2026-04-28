<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
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
                Log::warning('[RetraiteExtractor] Impossible de parser la réponse LLM');

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

[FORMAT DE TRANSCRIPTION]
La transcription peut être labelisée avec 2 locuteurs :
- "[Courtier]: ..." → le conseiller financier (courtier) — IGNORER ses phrases pour l'extraction
- "[Client]: ..." → le client (assuré) — SOURCE UNIQUE des données à extraire
Si les labels sont absents, identifier le client par les formulations à la 1ère personne ("je", "moi", "mon", "ma").

[OBJECTIF]
Détecter si le client exprime un besoin de retraite et extraire les données associées.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS RETRAITE]
Retraite, pension, PER, PERP, complément retraite, départ retraite, maintenir revenus retraite, préparer retraite, âge de départ, trimestres, régime retraite, épargne retraite

[SI DÉTECTÉ - RETRAITE]

Retourne :
{
  "besoins": ["retraite"],
  "besoins_action": "add",
  "bae_retraite": {
    // Champs ci-dessous SEULEMENT si mentionnés
  }
}

[CHAMPS bae_retraite] (tous optionnels)
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
- "bilan_retraite_disponible" (boolean) : true si relevé de carrière disponible
- "complementaire_retraite_mise_en_place" (boolean) : true si produit déjà en place
- "designation_etablissement" (string) : assureur/banque/organisme
- "cotisations_annuelles" (decimal) : montant des cotisations annuelles
- "titulaire" (string) : titulaire du contrat

[RÈGLE CRITIQUE - besoins_action]
- Par défaut : "add" (TOUJOURS)
- "remove" UNIQUEMENT si le client dit : "je n'ai PLUS besoin de retraite", "supprimez la retraite"
- NE JAMAIS utiliser "replace"

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "Je veux partir à la retraite à 62 ans et maintenir 70% de mes revenus"
Output: {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"age_depart_retraite": 62, "pourcentage_revenu_a_maintenir": 70}}

Input: "Mon TMI est de 30%. Le revenu foyer est de 80000 euros."
Output: {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {"tmi": "30%", "revenus_annuels_foyer": 80000}}

Input: "Je veux préparer ma retraite"
Output: {"besoins": ["retraite"], "besoins_action": "add", "bae_retraite": {}}

Input: "Je n'ai plus besoin de retraite"
Output: {"besoins": ["retraite"], "besoins_action": "remove"}

Input: "Je veux garantir 3000€ en cas d'invalidité"
Output: {}
PROMPT;
    }
}
