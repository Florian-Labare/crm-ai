<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service de routing pour détecter les sections concernées dans une transcription.
 * 
 * Retourne un tableau de sections : ["client", "prevoyance", "retraite", "epargne"]
 */
class RouterService
{
    /**
     * Détecte les sections concernées par la transcription.
     *
     * @param string $transcription Transcription vocale
     * @return array Tableau de sections (ex: ["client", "prevoyance"])
     */
    public function detectSections(string $transcription): array
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
                        'temperature' => 0.1, // Comportement déterministe
                        'response_format' => ['type' => 'json_object'],
                    ]);

            $json = $response->json();
            $raw = $json['choices'][0]['message']['content'] ?? '';

            Log::info('[RouterService] Réponse OpenAI', ['raw' => $raw]);

            $data = json_decode($raw, true);

            if (!is_array($data) || !isset($data['sections'])) {
                Log::warning('[RouterService] Format de réponse invalide', ['content' => $raw]);
                // Par défaut, considérer que c'est une transcription client
                return ['client'];
            }

            $sections = $data['sections'];

            // Validation : sections doit être un tableau
            if (!is_array($sections)) {
                return ['client'];
            }

            // Filtrer les sections invalides
            $validSections = ['client', 'conjoint', 'prevoyance', 'retraite', 'epargne', 'sante', 'emprunteur', 'revenus', 'passifs', 'actifs_financiers', 'biens_immobiliers', 'autres_epargnes'];
            $sections = array_filter($sections, fn($s) => in_array($s, $validSections));

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

    private function buildPrompt(string $transcription): string
    {
        return <<<PROMPT
Analyse cette transcription et détermine quelles sections sont concernées.

⚠️ ATTENTION : Si le client mentionne "ma femme", "mon mari", "mon épouse", "mon conjoint", "elle/il" (parlant du conjoint), tu DOIS inclure la section "conjoint" !

Transcription :
---
$transcription
---

Réponds STRICTEMENT avec un JSON valide au format :
{"sections": ["client", "conjoint", "prevoyance", ...]}
PROMPT;
    }

    /**
     * Force la détection de la section "conjoint" si des mots-clés sont présents.
     *
     * Garde-fou pour s'assurer que la section conjoint est détectée même si GPT ne l'a pas fait.
     */
    private function forceConjointDetection(string $transcription, array $sections): array
    {
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
                if (!in_array('conjoint', $sections)) {
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

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé en routing de conversations pour un CRM d'assurance.

🎯 OBJECTIF :
Détecter quelles sections métier sont concernées par la transcription.

🚫 RÈGLE ABSOLUE :
- Ignore toutes les phrases du conseiller (questions, suggestions, transitions)
- Ne tiens compte QUE des phrases du client

📋 SECTIONS DISPONIBLES :

1. **"client"** : Informations personnelles
   - Identité (nom, prénom, date de naissance, etc.)
   - Coordonnées (adresse, téléphone, email)
   - Situation familiale (marié, enfants, etc.)
   - Situation professionnelle (métier, revenus, entreprise, etc.)

2. **"conjoint"** : Informations sur le conjoint/partenaire
   - Mots-clés : mon conjoint, ma femme, mon mari, mon épouse, mon époux, ma/mon partenaire, compagne, compagnon
   - Dès que le client mentionne "ma femme", "mon mari", "elle/il" (parlant du conjoint) → TOUJOURS inclure "conjoint"
   - Exemples de phrases : "Ma femme s'appelle...", "Mon mari est...", "Elle travaille comme...", "Il est né en..."
   - ⚠️ IMPORTANT : Même si le client ne donne que le prénom du conjoint, inclure "conjoint"

3. **"prevoyance"** : Besoins de prévoyance
   - Mots-clés : invalidité, ITT, arrêt de travail, décès, capital décès, rente conjoint/enfants, protection, accident

4. **"retraite"** : Besoins de retraite
   - Mots-clés : retraite, pension, PER, PERP, départ retraite, âge de départ, trimestres, TMI, revenus foyer

5. **"epargne"** : Besoins d'épargne / patrimoine
   - Mots-clés : épargne, patrimoine, investissement, assurance vie, PEA, immobilier, crédit, donation, capacité d'épargne

6. **"sante"** : Besoins de santé / mutuelle
   - Mots-clés : mutuelle, santé, hospitalisation, soins, dentaire, optique

7. **"emprunteur"** : Assurance emprunteur
   - Mots-clés : prêt immobilier, assurance emprunteur, crédit immobilier

8. **"revenus"** : Sources de revenus du client
   - Mots-clés : salaire, revenus, rémunération, pension, loyer, revenus locatifs, dividendes, BNC, BIC

9. **"passifs"** : Prêts, emprunts, dettes
   - Mots-clés : prêt, emprunt, crédit, dette, mensualité, remboursement, capital restant dû

10. **"actifs_financiers"** : Actifs financiers (hors immobilier)
   - Mots-clés : assurance-vie, PEA, PER, compte-titres, livret A, LDDS, PEL, SCPI, OPCVM

11. **"biens_immobiliers"** : Biens immobiliers
   - Mots-clés : maison, appartement, résidence principale, résidence secondaire, bien locatif, SCI

12. **"autres_epargnes"** : Autres formes d'épargne
   - Mots-clés : or, cryptomonnaies, Bitcoin, objets d'art, collection, bijoux, métaux précieux

✅ RÈGLES DE DÉTECTION :

1. **Toujours inclure "client"** si le client donne des informations personnelles (nom, adresse, etc.)
2. **Toujours inclure "conjoint"** si le client mentionne : "ma femme", "mon mari", "mon épouse", "mon époux", "mon conjoint", "ma/mon partenaire", "elle/il" (en parlant du conjoint)
3. Ajouter les autres sections SEULEMENT si le client en parle explicitement
4. Plusieurs sections peuvent être concernées simultanément
5. Ne pas inventer de sections

⚠️ ATTENTION SPÉCIALE - SECTION CONJOINT :
Si vous détectez l'une de ces phrases, vous DEVEZ inclure "conjoint" :
- "Ma femme..." / "Mon mari..."
- "Mon épouse..." / "Mon époux..."
- "Mon conjoint..." / "Ma conjointe..."
- "Elle s'appelle..." / "Il s'appelle..." (contexte du conjoint)
- "Elle est..." / "Il est..." (en parlant du conjoint, pas du client)

📌 EXEMPLES :

Exemple 1 :
"Je m'appelle Jean Dupont, né le 15 mai 1980, j'habite à Paris"
→ {"sections": ["client"]}

Exemple 2 :
"Je veux garantir 3000€ par mois en cas d'invalidité"
→ {"sections": ["prevoyance"]}

Exemple 3 :
"Mon nom est Marie, j'ai besoin d'une prévoyance et de préparer ma retraite"
→ {"sections": ["client", "prevoyance", "retraite"]}

Exemple 4 :
"Mon conjoint s'appelle Pierre, il est médecin. Je veux une prévoyance."
→ {"sections": ["conjoint", "prevoyance"]}

Exemple 5 :
"Je m'appelle Jean. Ma femme s'appelle Sophie."
→ {"sections": ["client", "conjoint"]}

Exemple 6 :
"Mon mari est architecte, il gagne 5000€ par mois."
→ {"sections": ["conjoint"]}

Exemple 7 :
"Elle est infirmière" (contexte : parle de l'épouse)
→ {"sections": ["conjoint"]}

Exemple 8 :
"Mon TMI est de 30%. Je peux épargner 500€ par mois."
→ {"sections": ["retraite", "epargne"]}

Exemple 9 :
"Je suis plombier, chef d'entreprise en SARL, et je veux me protéger en cas d'arrêt de travail"
→ {"sections": ["client", "prevoyance"]}

⚠️ IMPORTANT :
Réponds UNIQUEMENT avec du JSON strict au format :
{"sections": ["section1", "section2", ...]}
PROMPT;
    }
}
