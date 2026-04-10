<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
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
                Log::warning('[ClientActifsFinanciersExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            // 🔀 Déduplication intelligente des actifs financiers
            if (isset($data['client_actifs_financiers']) && is_array($data['client_actifs_financiers'])) {
                $data['client_actifs_financiers'] = $this->deduplicateActifs($data['client_actifs_financiers']);
                $data['client_actifs_financiers'] = $this->sanitizeActifs($data['client_actifs_financiers']);
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

    /**
     * Déduplique et fusionne les actifs financiers qui concernent le même produit
     *
     * Logique : Si 2 actifs ont la même nature (et même établissement si spécifié),
     * on les fusionne en gardant toutes les informations disponibles.
     */
    private function deduplicateActifs(array $actifs): array
    {
        if (count($actifs) <= 1) {
            return $actifs;
        }

        // Étape 1: Séparer les actifs avec et sans établissement
        $withEtablissement = [];
        $withoutEtablissement = [];

        foreach ($actifs as $actif) {
            $nature = strtolower($actif['nature'] ?? 'autre');
            $etablissement = trim($actif['etablissement'] ?? '');

            if (! empty($etablissement)) {
                $key = $nature.'_'.strtolower($etablissement);
                if (! isset($withEtablissement[$key])) {
                    $withEtablissement[$key] = $actif;
                } else {
                    $withEtablissement[$key] = $this->mergeActifData($withEtablissement[$key], $actif);
                }
            } else {
                $withoutEtablissement[] = ['nature' => $nature, 'data' => $actif];
            }
        }

        // Étape 2: Fusionner les actifs sans établissement avec ceux qui en ont un (même nature)
        foreach ($withoutEtablissement as $item) {
            $nature = $item['nature'];
            $actif = $item['data'];
            $merged = false;

            // Chercher un actif de même nature avec établissement
            foreach ($withEtablissement as $key => &$existing) {
                if (str_starts_with($key, $nature.'_')) {
                    $withEtablissement[$key] = $this->mergeActifData($existing, $actif);
                    $merged = true;
                    Log::info('[ClientActifsFinanciersExtractor] 🔀 Fusion sans établissement → avec établissement', [
                        'nature' => $nature,
                        'etablissement_existant' => $existing['etablissement'] ?? 'inconnu',
                    ]);
                    break;
                }
            }

            // Si pas trouvé, ajouter comme entrée séparée par nature
            if (! $merged) {
                if (! isset($withEtablissement[$nature])) {
                    $withEtablissement[$nature] = $actif;
                } else {
                    $withEtablissement[$nature] = $this->mergeActifData($withEtablissement[$nature], $actif);
                }
            }
        }

        $result = array_values($withEtablissement);

        if (count($result) < count($actifs)) {
            Log::info('[ClientActifsFinanciersExtractor] 🔀 Déduplication effectuée', [
                'avant' => count($actifs),
                'après' => count($result),
                'actifs_fusionnés' => array_map(fn ($a) => ($a['nature'] ?? 'inconnu').' ('.($a['etablissement'] ?? 'sans établissement').')', $result),
            ]);
        }

        return $result;
    }

    /**
     * Fusionne deux actifs en gardant les informations les plus complètes
     */
    private function mergeActifData(array $existing, array $new): array
    {
        $fields = ['nature', 'etablissement', 'detenteur', 'date_ouverture_souscription', 'valeur_actuelle'];

        foreach ($fields as $field) {
            if (isset($new[$field]) && ! empty($new[$field])) {
                if (! isset($existing[$field]) || empty($existing[$field])) {
                    $existing[$field] = $new[$field];
                }
            }
        }

        return $existing;
    }

    private function sanitizeActifs(array $actifs): array
    {
        $filtered = [];
        $seen = [];
        foreach ($actifs as $actif) {
            $nature = $actif['nature'] ?? '';
            $natureKey = $this->normalizeKey($nature);
            if ($natureKey === '' || $this->isCryptoNature($natureKey)) {
                continue;
            }

            $etablissementKey = $this->normalizeKey($actif['etablissement'] ?? '');
            $valeurKey = isset($actif['valeur_actuelle']) ? number_format((float) $actif['valeur_actuelle'], 2, '.', '') : '';
            $key = $natureKey.'|'.$etablissementKey.'|'.$valeurKey;

            if (isset($seen[$key])) {
                $filtered[$seen[$key]] = $this->mergeActifData($filtered[$seen[$key]], $actif);

                continue;
            }

            $seen[$key] = count($filtered);
            $filtered[] = $actif;
        }

        return $filtered;
    }

    private function normalizeKey(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);

        return trim((string) $normalized, '_');
    }

    private function isCryptoNature(string $value): bool
    {
        return str_contains($value, 'crypto')
            || str_contains($value, 'bitcoin')
            || str_contains($value, 'btc')
            || str_contains($value, 'ethereum')
            || str_contains($value, 'eth')
            || str_contains($value, 'solana')
            || str_contains($value, 'xrp')
            || str_contains($value, 'token');
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction d'ACTIFS FINANCIERS clients.

[OBJECTIF]
Détecter et extraire tous les actifs financiers mentionnés par le client (assurance-vie, PEA, compte-titres, livrets, etc.).

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller
- Ne tiens compte QUE des phrases du client

[MOTS-CLÉS ACTIFS FINANCIERS]
Assurance-vie, PEA, PER, compte-titres, livret A, LDDS, LDD, LEP, livret jeune, CEL, PEL, SCPI, OPCVM, actions cotées en bourse, obligations, fonds euro, sicav, FCP, ETF

[À NE PAS INCLURE - géré par d'autres extracteurs]
- Cryptomonnaies (Bitcoin, Ethereum, etc.) = ClientAutresEpargnesExtractor
- Or, lingots, métaux précieux = ClientAutresEpargnesExtractor
- Biens immobiliers = ClientBiensImmobiliersExtractor
- Objets d'art, collections = ClientAutresEpargnesExtractor

[SI DÉTECTÉ - ACTIFS FINANCIERS]

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

[CHAMPS pour chaque actif]
- "nature" (string, requis) : assurance-vie, PEA, PER, compte-titres, livret-A, LDDS, PEL, CEL, SCPI, OPCVM, autre
- "etablissement" (string, optionnel) : Nom de la banque/assurance
- "detenteur" (string, optionnel) : client, conjoint, ou commun
- "date_ouverture_souscription" (date, optionnel) : Format YYYY-MM-DD
- "valeur_actuelle" (decimal, optionnel) : Valeur/montant actuel

[RÈGLES IMPORTANTES]
- Créer une entrée séparée pour chaque produit DIFFÉRENT
- Si même produit mentionné plusieurs fois, FUSIONNER en UNE SEULE entrée
- "contrat" ou "assurance vie" = nature "assurance-vie"
- Si année seulement mentionnée, utiliser YYYY-01-01

[RÈGLE DE FUSION]
- Si même type de produit mentionné plusieurs fois, REGROUPER en UNE SEULE entrée
- Ne PAS créer de doublons

[SI NON DÉTECTÉ]
Retourne un objet vide : {}

[EXEMPLES]

Input: "J'ai une assurance-vie chez AXA de 50000€ ouverte en 2020"
Output: {"client_actifs_financiers": [{"nature": "assurance-vie", "etablissement": "AXA", "valeur_actuelle": 50000, "date_ouverture_souscription": "2020-01-01"}]}

Input: "J'ai un PEA à la Société Générale avec 30000€"
Output: {"client_actifs_financiers": [{"nature": "PEA", "etablissement": "Société Générale", "valeur_actuelle": 30000}]}

Input: "J'ai un PEA de 20000€ et un livret A de 15000€"
Output: {"client_actifs_financiers": [{"nature": "PEA", "valeur_actuelle": 20000}, {"nature": "livret-A", "valeur_actuelle": 15000}]}

Input: "Je possède des parts de SCPI pour 80000€"
Output: {"client_actifs_financiers": [{"nature": "SCPI", "valeur_actuelle": 80000}]}

Input: "Mon épouse a une assurance-vie de 40000€ chez Generali"
Output: {"client_actifs_financiers": [{"nature": "assurance-vie", "etablissement": "Generali", "detenteur": "conjoint", "valeur_actuelle": 40000}]}

Input: "J'ai ouvert un PER en 2022 avec 10000€"
Output: {"client_actifs_financiers": [{"nature": "PER", "date_ouverture_souscription": "2022-01-01", "valeur_actuelle": 10000}]}

Input: "Je veux partir à la retraite à 62 ans"
Output: {}
PROMPT;
    }
}
