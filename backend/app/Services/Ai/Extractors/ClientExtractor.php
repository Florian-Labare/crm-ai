<?php

namespace App\Services\Ai\Extractors;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

/**
 * Extracteur spécialisé pour les informations CLIENT.
 *
 * Responsabilité :
 * - Identité (civilite, nom, prenom, date_naissance, etc.)
 * - Situation matrimoniale / familiale
 * - Enfants (tableau d'objets)
 * - Coordonnées (adresse, téléphone, email)
 * - Situation professionnelle
 *
 * N'extrait PAS les BAE (gérés par d'autres extractors).
 */
class ClientExtractor {
    use LlmClientTrait;

    /**
     * Extrait les données client depuis la transcription.
     *
     * @param  string  $transcription  Transcription vocale
     * @param  array  $currentData  Données client existantes (optionnel)
     * @return array Données extraites
     */
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
                Log::warning('[ClientExtractor] Impossible de parser la réponse LLM');

                return [];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[ClientExtractor] Erreur lors de l\'extraction', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Construit le prompt utilisateur.
     */
    private function buildPrompt(string $transcription): string {
        return <<<PROMPT
Analyse cette transcription et extrais UNIQUEMENT les informations personnelles du CLIENT PRINCIPAL (celui qui parle, qui dit "je").

IMPORTANT : IGNORE complètement les informations sur le conjoint/époux/épouse ("ma femme", "mon mari", etc.).

Transcription :
---
$transcription
---

Réponds UNIQUEMENT avec un JSON valide concernant le client principal, sans aucun texte avant ou après.
PROMPT;
    }

    /**
     * Retourne le prompt système pour l'extraction client.
     */
    private function getSystemPrompt(): string {
        return <<<'PROMPT'
Tu es un assistant spécialisé en extraction de données client pour un CRM d'assurance.

[OBJECTIF]
Extraire UNIQUEMENT les informations personnelles du client depuis la transcription vocale.

[ÉPELLATION / DICTÉE]
- Si une valeur est épelée lettre par lettre (ex: "D U P O N T" ou "D comme David"), reconstruis le mot complet.
- Pour email : "arobase" = "@", "point" = ".", "tiret" = "-", "underscore" = "_"
- Pour téléphone : concatène tous les chiffres en une seule chaîne.

[RÈGLES ABSOLUES]
1. Ignore le CONSEILLER : Ignore TOUTES les phrases du conseiller (questions, propositions)
2. N'extrais QUE le CLIENT PRINCIPAL : phrases avec "je", "moi", "mon", "ma", "mes"
3. IGNORE TOTALEMENT le CONJOINT : "mon conjoint", "ma femme", "mon mari", "mon épouse" = NE PAS extraire
4. En cas de doute sur qui parle : N'extrais PAS l'information

[CHAMPS À EXTRAIRE]

Identité :
- "civilite" (string) : "M.", "Mme", "Mlle"
- "nom" (string) : nom de famille
- "nom_jeune_fille" (string) : nom de jeune fille si applicable
- "prenom" (string) : prénom
- "date_naissance" (string) : format "YYYY-MM-DD"
- "lieu_naissance" (string) : ville COMPLÈTE
- "nationalite" (string) : nationalité

Situation familiale :
- "situation_matrimoniale" (string) : "Marié(e)", "Célibataire", "Divorcé(e)", "Veuf(ve)", "Pacsé(e)", "Concubinage"
- "date_situation_matrimoniale" (string) : date du mariage/pacs/divorce
- "enfants" (array) : tableau d'objets enfants

Coordonnées :
- "adresse" (string) : numéro et nom de rue
- "code_postal" (string) : 5 chiffres
- "ville" (string) : nom COMPLET de la ville
- "telephone" (string) : numéro de téléphone
- "email" (string) : adresse email
- "residence_fiscale" (string) : pays de résidence fiscale

Situation professionnelle :
- "situation_actuelle" (string) : "Salarié(e)", "Retraité(e)", "Étudiant(e)", "Demandeur d'emploi"
- "profession" (string) : métier exact (ex: "plombier", "médecin")
- "risques_professionnels" (boolean) : true/false
- "details_risques_professionnels" (string) : détails sur les risques
- "date_evenement_professionnel" (string) : date d'un événement pro

Informations entreprise :
- "chef_entreprise" (boolean) : true si chef d'entreprise
- "travailleur_independant" (boolean) : true si freelance/indépendant
- "mandataire_social" (boolean) : true si mandataire social
- "statut" (string) : "SARL", "SAS", "SASU", "EURL", "SCI", "Auto-entrepreneur"

NOTE : NE JAMAIS mettre "chef d'entreprise" ou "travailleur indépendant" dans "profession", utiliser les champs booléens.

Santé et loisirs :
- "fumeur" (boolean) : true/false
- "activites_sportives" (boolean) : true/false
- "details_activites_sportives" (string) : détails
- "niveau_activites_sportives" (string) : niveau de pratique

Consentement :
- "consentement_audio" (boolean) : true si accepte l'enregistrement, false sinon

[STRUCTURE ENFANTS]
Tableau d'objets avec :
- "nom" (string)
- "prenom" (string)
- "date_naissance" (string) : format "YYYY-MM-DD"
- "fiscalement_a_charge" (boolean)
- "garde_alternee" (boolean)

IMPORTANT : Capturer TOUS les enfants mentionnés !

[NE PAS EXTRAIRE]
- Informations du CONJOINT (gérées par ConjointExtractor)
- Revenus (gérés par ClientRevenusExtractor)
- Besoins (prévoyance, retraite, épargne)

[RÈGLES IMPORTANTES]
1. UNIQUEMENT LE CLIENT PRINCIPAL (celui qui dit "je", "moi")
2. JAMAIS LE CONJOINT
3. Ne jamais inventer de données
4. Garder les noms de villes COMPLETS
5. Respecter la négation (ex: "je ne suis PAS fumeur" = fumeur: false)

[EXEMPLES]

Input: "J'ai deux enfants, Emma et Louis"
Output: {"enfants": [{"prenom": "Emma"}, {"prenom": "Louis"}]}

Input: "Je m'appelle Jean Dupont. Ma femme s'appelle Sophie Martin, elle est médecin."
Output: {"nom": "Dupont", "prenom": "Jean"}
Note: Les infos sur Sophie (conjoint) sont ignorées.

Input: "Je fais de la musculation"
Output: {"activites_sportives": true, "details_activites_sportives": "musculation"}

Input: "Je ne fume pas"
Output: {"fumeur": false}

[FORMAT DE SORTIE]
Réponds UNIQUEMENT avec du JSON valide, sans texte explicatif.
PROMPT;
    }
}
