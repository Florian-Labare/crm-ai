<?php

namespace App\Services\Ai;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Service de routing pour détecter les sections concernées dans une transcription.
 *
 * Retourne un tableau de sections : ["client", "prevoyance", "retraite", "epargne"]
 */
class RouterService {
    use LlmClientTrait;

    /**
     * Détecte les sections concernées par la transcription.
     *
     * @param  string  $transcription  Transcription vocale
     * @return array Tableau de sections (ex: ["client", "prevoyance"])
     */
    public function detectSections(string $transcription): array {
        $prompt = $this->buildPrompt($transcription);

        try {
            $data = $this->callLlm(
                $this->getSystemPrompt(),
                $prompt,
                0.1,
                true
            );

            if (! is_array($data) || ! isset($data['sections'])) {
                Log::warning('[RouterService] Format de réponse invalide', ['content' => $data]);

                // Par défaut, considérer que c'est une transcription client
                return ['client'];
            }

            $sections = $data['sections'];

            // Validation : sections doit être un tableau
            if (! is_array($sections)) {
                return ['client'];
            }

            // Filtrer les sections invalides
            $validSections = ['client', 'conjoint', 'prevoyance', 'retraite', 'epargne', 'sante', 'emprunteur', 'revenus', 'passifs', 'actifs_financiers', 'biens_immobiliers', 'autres_epargnes'];
            $sections = array_filter($sections, fn ($s) => in_array($s, $validSections));

            // Si aucune section détectée, par défaut "client"
            if (empty($sections)) {
                return ['client'];
            }

            // 🔒 GARDE-FOU : Forcer la détection de "conjoint" si mots-clés présents
            $sections = $this->forceConjointDetection($transcription, $sections);

            Log::info('[RouterService] Sections détectées', ['sections' => $sections]);

            return $sections;

        } catch (\Throwable $e) {
            Log::error('[RouterService] Erreur lors de la détection', ['message' => $e->getMessage()]);

            // En cas d'erreur, par défaut "client"
            return ['client'];
        }
    }

    private function buildPrompt(string $transcription): string {
        return <<<PROMPT
Analyse cette transcription et détermine quelles sections sont concernées.

ATTENTION : Si le client mentionne "ma femme", "mon mari", "mon épouse", "mon conjoint", "elle/il" (parlant du conjoint), tu DOIS inclure la section "conjoint" !

Transcription :
---
$transcription
---

Réponds UNIQUEMENT avec un JSON valide au format :
{"sections": ["client", "conjoint", "prevoyance", ...]}
PROMPT;
    }

    /**
     * Force la détection de la section "conjoint" si des mots-clés sont présents.
     *
     * Garde-fou pour s'assurer que la section conjoint est détectée même si le LLM ne l'a pas fait.
     */
    private function forceConjointDetection(string $transcription, array $sections): array {
        // Normaliser la transcription en minuscules pour la détection
        $text = mb_strtolower($transcription, 'UTF-8');

        // Patterns de détection du conjoint (insensible à la casse)
        $conjointPatterns = [
            '/\bma femme\b/u',
            '/\bmon mari\b/u',
            '/\bmon épouse\b/u',
            '/\bma épouse\b/u',
            '/\bmon époux\b/u',
            '/\bmon conjoint\b/u',
            '/\bma conjointe\b/u',
            '/\bmon partenaire\b/u',
            '/\bma partenaire\b/u',
            '/\bmon compagnon\b/u',
            '/\bma compagne\b/u',
        ];

        // Vérifier si un des patterns est présent
        foreach ($conjointPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                // Ajouter "conjoint" si pas déjà présent
                if (! in_array('conjoint', $sections)) {
                    $sections[] = 'conjoint';
                    Log::info('🔒 [RouterService] Section "conjoint" forcée par détection de mots-clés', [
                        'pattern_matched' => $pattern,
                    ]);
                }
                break;
            }
        }

        return $sections;
    }

    private function getSystemPrompt(): string {
        return <<<'PROMPT'
Tu es un assistant spécialisé en routing de conversations pour un CRM d'assurance.

[OBJECTIF]
Détecter quelles sections métier sont concernées par la transcription.

[RÈGLE ABSOLUE]
- Ignore toutes les phrases du conseiller (questions, suggestions, transitions)
- Ne tiens compte QUE des phrases du client

[SECTIONS DISPONIBLES]

1. "client" : Informations personnelles
   - Identité (nom, prénom, date de naissance, etc.)
   - Coordonnées (adresse, téléphone, email)
   - Situation familiale (marié, enfants, etc.)
   - Situation professionnelle (métier, revenus, entreprise, etc.)

2. "conjoint" : Informations sur le conjoint/partenaire
   - Mots-clés : mon conjoint, ma femme, mon mari, mon épouse, mon époux, ma/mon partenaire, compagne, compagnon
   - Dès que le client mentionne "ma femme", "mon mari", "elle/il" (parlant du conjoint) : TOUJOURS inclure "conjoint"
   - Exemples de phrases : "Ma femme s'appelle...", "Mon mari est...", "Elle travaille comme...", "Il est né en..."
   - IMPORTANT : Même si le client ne donne que le prénom du conjoint, inclure "conjoint"

3. "prevoyance" : Besoins de prévoyance
   - Mots-clés : invalidité, ITT, arrêt de travail, décès, capital décès, rente conjoint/enfants, protection, accident

4. "retraite" : Besoins de retraite
   - Mots-clés : retraite, pension, PER, PERP, départ retraite, âge de départ, trimestres, TMI, revenus foyer

5. "epargne" : Besoins d'épargne / patrimoine
   - Mots-clés : épargne, patrimoine, investissement, assurance vie, PEA, immobilier, crédit, donation, capacité d'épargne

6. "sante" : Besoins de santé / mutuelle
   - Mots-clés : mutuelle, santé, hospitalisation, soins, dentaire, optique

7. "emprunteur" : Assurance emprunteur
   - Mots-clés : prêt immobilier, assurance emprunteur, crédit immobilier

8. "revenus" : Sources de revenus du client
   - Mots-clés : salaire, revenus, rémunération, pension, loyer, revenus locatifs, dividendes, BNC, BIC

9. "passifs" : Prêts, emprunts, dettes
   - Mots-clés : prêt, emprunt, crédit, dette, mensualité, remboursement, capital restant dû

10. "actifs_financiers" : Actifs financiers (hors immobilier)
    - Mots-clés : assurance-vie, PEA, PER, compte-titres, livret A, LDDS, PEL, SCPI, OPCVM

11. "biens_immobiliers" : Biens immobiliers
    - Mots-clés : maison, appartement, résidence principale, résidence secondaire, bien locatif, SCI

12. "autres_epargnes" : Autres formes d'épargne
    - Mots-clés : or, cryptomonnaies, Bitcoin, objets d'art, collection, bijoux, métaux précieux

[RÈGLES DE DÉTECTION]

1. Toujours inclure "client" si le client donne des informations personnelles (nom, adresse, etc.)
2. Toujours inclure "conjoint" si le client mentionne : "ma femme", "mon mari", "mon épouse", "mon époux", "mon conjoint", "ma/mon partenaire", "elle/il" (en parlant du conjoint)
3. Ajouter les autres sections SEULEMENT si le client en parle explicitement
4. Plusieurs sections peuvent être concernées simultanément
5. Ne pas inventer de sections

[ATTENTION - SECTION CONJOINT]
Si vous détectez l'une de ces phrases, vous DEVEZ inclure "conjoint" :
- "Ma femme..." / "Mon mari..."
- "Mon épouse..." / "Mon époux..."
- "Mon conjoint..." / "Ma conjointe..."
- "Elle s'appelle..." / "Il s'appelle..." (contexte du conjoint)
- "Elle est..." / "Il est..." (en parlant du conjoint, pas du client)

[EXEMPLES]

Input: "Je m'appelle Jean Dupont, né le 15 mai 1980, j'habite à Paris"
Output: {"sections": ["client"]}

Input: "Je veux garantir 3000€ par mois en cas d'invalidité"
Output: {"sections": ["prevoyance"]}

Input: "Mon nom est Marie, j'ai besoin d'une prévoyance et de préparer ma retraite"
Output: {"sections": ["client", "prevoyance", "retraite"]}

Input: "Mon conjoint s'appelle Pierre, il est médecin. Je veux une prévoyance."
Output: {"sections": ["conjoint", "prevoyance"]}

Input: "Je m'appelle Jean. Ma femme s'appelle Sophie."
Output: {"sections": ["client", "conjoint"]}

Input: "Mon mari est architecte, il gagne 5000€ par mois."
Output: {"sections": ["conjoint"]}

Input: "Elle est infirmière" (contexte : parle de l'épouse)
Output: {"sections": ["conjoint"]}

Input: "Mon TMI est de 30%. Je peux épargner 500€ par mois."
Output: {"sections": ["retraite", "epargne"]}

Input: "Je suis plombier, chef d'entreprise en SARL, et je veux me protéger en cas d'arrêt de travail"
Output: {"sections": ["client", "prevoyance"]}

[FORMAT DE SORTIE]
Réponds UNIQUEMENT avec du JSON strict au format :
{"sections": ["section1", "section2", ...]}
PROMPT;
    }
}
