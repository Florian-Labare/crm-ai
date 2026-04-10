<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour PASSIFS CLIENT (prêts, emprunts, dettes).
 *
 * Responsabilité :
 * - Extraction des emprunts multiples (immobilier, consommation, etc.)
 * - Retourne un array de passifs avec nature, preteur, periodicite, montants, durée
 */
class ClientPassifsExtractor {
    use LlmClientTrait;

    public function extract(string $transcription, array $currentData = []): array {
        $prompt = $this->buildPrompt($transcription);

        try {
            $data = $this->callLlm(
                $this->getSystemPrompt(),
                $prompt,
                0.1,
                true
            );

            if (! is_array($data)) {
                Log::warning('[ClientPassifsExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            // 🔀 Déduplication intelligente des passifs
            if (isset($data['client_passifs']) && is_array($data['client_passifs'])) {
                $data['client_passifs'] = $this->deduplicatePassifs($data['client_passifs']);
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientPassifsExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function buildPrompt(string $transcription): string {
        return <<<PROMPT
Analyse cette transcription et détecte les PRÊTS/EMPRUNTS du client.

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide, sans aucun texte avant ou après.
PROMPT;
    }

    /**
     * Déduplique et fusionne les passifs qui concernent le même crédit
     *
     * Logique avancée :
     * 1. Regrouper par nature + prêteur si les deux sont spécifiés
     * 2. Si un passif n'a pas de prêteur, le fusionner avec un passif de même nature qui en a un
     * 3. Si deux passifs de même nature n'ont pas de prêteur, les fusionner
     */
    private function deduplicatePassifs(array $passifs): array {
        if (count($passifs) <= 1) {
            return $passifs;
        }

        // Étape 1: Séparer les passifs avec et sans prêteur
        $withPreteur = [];
        $withoutPreteur = [];

        foreach ($passifs as $passif) {
            $nature = strtolower($passif['nature'] ?? 'autre');
            $preteur = trim($passif['preteur'] ?? '');

            if (! empty($preteur)) {
                $key = $nature.'_'.strtolower($preteur);
                if (! isset($withPreteur[$key])) {
                    $withPreteur[$key] = $passif;
                } else {
                    $withPreteur[$key] = $this->mergePassifData($withPreteur[$key], $passif);
                }
            } else {
                $withoutPreteur[] = ['nature' => $nature, 'data' => $passif];
            }
        }

        // Étape 2: Fusionner les passifs sans prêteur avec ceux qui en ont un (même nature)
        foreach ($withoutPreteur as $item) {
            $nature = $item['nature'];
            $passif = $item['data'];
            $merged = false;

            // Chercher un passif de même nature avec prêteur
            foreach ($withPreteur as $key => &$existing) {
                if (str_starts_with($key, $nature.'_')) {
                    $withPreteur[$key] = $this->mergePassifData($existing, $passif);
                    $merged = true;
                    Log::info('[ClientPassifsExtractor] 🔀 Fusion sans prêteur → avec prêteur', [
                        'nature' => $nature,
                        'preteur_existant' => $existing['preteur'] ?? 'inconnu',
                    ]);
                    break;
                }
            }

            // Si pas trouvé, ajouter comme entrée séparée par nature
            if (! $merged) {
                if (! isset($withPreteur[$nature])) {
                    $withPreteur[$nature] = $passif;
                } else {
                    $withPreteur[$nature] = $this->mergePassifData($withPreteur[$nature], $passif);
                }
            }
        }

        $result = array_values($withPreteur);

        if (count($result) < count($passifs)) {
            Log::info('[ClientPassifsExtractor] 🔀 Déduplication effectuée', [
                'avant' => count($passifs),
                'après' => count($result),
                'passifs_fusionnés' => array_map(fn ($p) => ($p['nature'] ?? 'inconnu').' ('.($p['preteur'] ?? 'sans prêteur').')', $result),
            ]);
        }

        return $result;
    }

    /**
     * Fusionne deux passifs en gardant les informations les plus complètes
     */
    private function mergePassifData(array $existing, array $new): array {
        $fields = ['nature', 'preteur', 'periodicite', 'montant_remboursement', 'capital_restant_du', 'duree_restante'];

        foreach ($fields as $field) {
            // Si le champ existe dans new et pas dans existing (ou est vide/null)
            if (isset($new[$field]) && ! empty($new[$field])) {
                if (! isset($existing[$field]) || empty($existing[$field])) {
                    $existing[$field] = $new[$field];
                }
                // Si les deux ont une valeur, garder celle de existing (première mention)
                // sauf si new a une valeur plus précise (non-nulle et différente de 0)
            }
        }

        return $existing;
    }

    private function getSystemPrompt(): string {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de PASSIFS clients (prêts, emprunts, dettes).

[OBJECTIF]
Détecter et extraire tous les prêts et emprunts mentionnés par le client.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS PASSIFS]
Prêt, emprunt, crédit, dette, mensualité, remboursement, capital restant dû, CRD, échéance, banque, prêteur

[SI DÉTECTÉ - PRÊTS]

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

[CHAMPS pour chaque prêt]
- "nature" (string, requis) : immobilier, consommation, auto, travaux, professionnel, autre
- "preteur" (string, optionnel) : Nom de la banque/organisme prêteur
- "periodicite" (string, optionnel) : mensuel, annuel
- "montant_remboursement" (decimal, optionnel) : Montant de l'échéance
- "capital_restant_du" (decimal, optionnel) : Capital restant dû (CRD)
- "duree_restante" (integer, optionnel) : Durée restante en mois

[RÈGLES IMPORTANTES]
- Créer une entrée séparée pour chaque TYPE de prêt DIFFÉRENT
- Si plusieurs prêts DE MÊME TYPE sont mentionnés, FUSIONNER en UNE SEULE entrée
- Convertir les années en mois pour duree_restante (10 ans = 120 mois)
- "crédit immobilier" ou "prêt immo" = nature "immobilier"
- "crédit conso" ou "prêt personnel" = nature "consommation"

[RÈGLE DE FUSION]
- Si même type de crédit mentionné plusieurs fois, REGROUPER en UNE SEULE entrée
- Ne PAS créer de doublons pour le même crédit

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "J'ai un crédit immobilier au Crédit Agricole, je paie 1200€ par mois et il me reste 150000€ sur 10 ans"
Output: {"client_passifs": [{"nature": "immobilier", "preteur": "Crédit Agricole", "periodicite": "mensuel", "montant_remboursement": 1200, "capital_restant_du": 150000, "duree_restante": 120}]}

Input: "J'ai un prêt auto de 300€ par mois pendant encore 3 ans"
Output: {"client_passifs": [{"nature": "auto", "periodicite": "mensuel", "montant_remboursement": 300, "duree_restante": 36}]}

Input: "J'ai un crédit immo de 1500€/mois avec 200000€ restants, et un crédit conso de 200€/mois"
Output: {"client_passifs": [{"nature": "immobilier", "periodicite": "mensuel", "montant_remboursement": 1500, "capital_restant_du": 200000}, {"nature": "consommation", "periodicite": "mensuel", "montant_remboursement": 200}]}

Input: "J'ai un prêt professionnel à la BNP de 80000€ restants"
Output: {"client_passifs": [{"nature": "professionnel", "preteur": "BNP", "capital_restant_du": 80000}]}

Input: "Je n'ai aucun crédit en cours"
Output: {}

Input: "Je veux optimiser mon patrimoine"
Output: {}
PROMPT;
    }
}
