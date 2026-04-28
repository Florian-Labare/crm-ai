<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
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
                Log::warning('[ClientBiensImmobiliersExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            // 🔀 Déduplication intelligente des biens immobiliers
            if (isset($data['client_biens_immobiliers']) && is_array($data['client_biens_immobiliers'])) {
                $data['client_biens_immobiliers'] = $this->deduplicateBiens($data['client_biens_immobiliers']);
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

    /**
     * Déduplique et fusionne les biens immobiliers qui concernent le même bien
     *
     * Logique : Si 2 biens ont une désignation similaire (même type de bien),
     * on les fusionne en gardant toutes les informations disponibles.
     */
    private function deduplicateBiens(array $biens): array
    {
        if (count($biens) <= 1) {
            return $biens;
        }

        $merged = [];

        foreach ($biens as $bien) {
            $key = $this->normalizeBienKey($bien['designation'] ?? '');

            if (! isset($merged[$key])) {
                $merged[$key] = $bien;
            } else {
                $merged[$key] = $this->mergeBienData($merged[$key], $bien);
            }
        }

        $result = array_values($merged);

        if (count($result) < count($biens)) {
            Log::info('[ClientBiensImmobiliersExtractor] 🔀 Déduplication effectuée', [
                'avant' => count($biens),
                'après' => count($result),
                'biens_fusionnés' => array_map(fn ($b) => $b['designation'] ?? 'inconnu', $result),
            ]);
        }

        return $result;
    }

    /**
     * Normalise la clé d'un bien pour la déduplication
     * Ex: "Studio locatif" et "Studio en location" → "studio_locatif"
     */
    private function normalizeBienKey(string $designation): string
    {
        $designation = strtolower($designation);

        // Types de biens principaux
        $types = [
            'residence_principale' => ['résidence principale', 'residence principale', 'rp', 'maison principale'],
            'residence_secondaire' => ['résidence secondaire', 'residence secondaire', 'rs'],
            'studio' => ['studio'],
            'appartement' => ['appartement', 'appart'],
            'maison' => ['maison'],
            'terrain' => ['terrain'],
            'immeuble' => ['immeuble'],
            'locatif' => ['locatif', 'location', 'loué', 'louée', 'investissement'],
        ];

        $key = '';
        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($designation, $keyword)) {
                    $key .= $type.'_';
                }
            }
        }

        return $key ?: 'bien_'.substr(md5($designation), 0, 8);
    }

    /**
     * Fusionne deux biens en gardant les informations les plus complètes
     */
    private function mergeBienData(array $existing, array $new): array
    {
        $fields = ['designation', 'detenteur', 'forme_propriete', 'valeur_actuelle_estimee', 'annee_acquisition', 'valeur_acquisition'];

        foreach ($fields as $field) {
            if (isset($new[$field]) && ! empty($new[$field])) {
                if (! isset($existing[$field]) || empty($existing[$field])) {
                    $existing[$field] = $new[$field];
                }
            }
        }

        // Pour la désignation, garder la plus longue (plus descriptive)
        if (isset($new['designation']) && strlen($new['designation']) > strlen($existing['designation'] ?? '')) {
            $existing['designation'] = $new['designation'];
        }

        return $existing;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de BIENS IMMOBILIERS clients.

[FORMAT DE TRANSCRIPTION]
La transcription peut être labelisée avec 2 locuteurs :
- "[Courtier]: ..." → le conseiller financier (courtier) — IGNORER ses phrases pour l'extraction
- "[Client]: ..." → le client (assuré) — SOURCE UNIQUE des données à extraire
Si les labels sont absents, identifier le client par les formulations à la 1ère personne ("je", "moi", "mon", "ma").

[OBJECTIF]
Détecter et extraire tous les biens immobiliers mentionnés par le client (résidence principale, secondaire, locatif, etc.).

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS IMMOBILIER]
Maison, appartement, résidence principale, résidence secondaire, bien locatif, immeuble, terrain, propriété, SCI, indivision, pleine propriété, nue-propriété, usufruit

[SI DÉTECTÉ - BIENS IMMOBILIERS]

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

[CHAMPS pour chaque bien]
- "designation" (string, requis) : Description du bien (type + localisation si mentionnée)
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "forme_propriete" (string, optionnel) : pleine-propriete, indivision, SCI, nue-propriete, usufruit
- "valeur_actuelle_estimee" (decimal, optionnel) : Valeur estimée actuelle
- "annee_acquisition" (integer, optionnel) : Année d'achat
- "valeur_acquisition" (decimal, optionnel) : Prix d'achat

[RÈGLES IMPORTANTES]
- Créer une entrée séparée pour chaque bien immobilier DIFFÉRENT
- Si même bien mentionné plusieurs fois, FUSIONNER en UNE SEULE entrée
- Inclure le type de bien dans la désignation (maison, appartement, terrain, etc.)
- Si localisation mentionnée, l'inclure dans la désignation

[RÈGLE DE FUSION]
- Si même bien mentionné plusieurs fois, REGROUPER en UNE SEULE entrée
- Ne PAS créer de doublons

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "Ma maison principale vaut 400000€, je l'ai achetée 350000€ en 2015"
Output: {"client_biens_immobiliers": [{"designation": "Résidence principale - Maison", "valeur_actuelle_estimee": 400000, "annee_acquisition": 2015, "valeur_acquisition": 350000}]}

Input: "J'ai un appartement à Paris estimé à 500000€"
Output: {"client_biens_immobiliers": [{"designation": "Appartement à Paris", "valeur_actuelle_estimee": 500000}]}

Input: "Je possède un studio en location à Lyon, acheté 120000€ en 2018, qui vaut maintenant 150000€"
Output: {"client_biens_immobiliers": [{"designation": "Studio locatif à Lyon", "valeur_actuelle_estimee": 150000, "annee_acquisition": 2018, "valeur_acquisition": 120000}]}

Input: "J'ai ma maison principale de 400000€ et une résidence secondaire de 200000€"
Output: {"client_biens_immobiliers": [{"designation": "Résidence principale - Maison", "valeur_actuelle_estimee": 400000}, {"designation": "Résidence secondaire", "valeur_actuelle_estimee": 200000}]}

Input: "J'ai un immeuble en SCI estimé à 800000€"
Output: {"client_biens_immobiliers": [{"designation": "Immeuble", "forme_propriete": "SCI", "valeur_actuelle_estimee": 800000}]}

Input: "Mon conjoint et moi possédons en indivision un appartement de 350000€"
Output: {"client_biens_immobiliers": [{"designation": "Appartement", "detenteur": "commun", "forme_propriete": "indivision", "valeur_actuelle_estimee": 350000}]}

Input: "Je possède un terrain à construire acheté 80000€ en 2020"
Output: {"client_biens_immobiliers": [{"designation": "Terrain à construire", "annee_acquisition": 2020, "valeur_acquisition": 80000}]}

Input: "Je veux optimiser mon patrimoine financier"
Output: {}
PROMPT;
    }
}
