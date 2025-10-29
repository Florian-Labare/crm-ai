<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalysisService
{
    public function extractClientData(string $transcription): array
    {
        $prompt = <<<PROMPT
            Analyse ce texte de conversation et renvoie un JSON **valide** avec exactement ces clés :
            {
            "nom": "Nom de famille",
            "prenom": "Prénom",
            "datedenaissance": "AAAA-MM-JJ",
            "lieudenaissance": "Ville ou pays de naissance",
            "situationmatrimoniale": "Célibataire, marié, divorcé, etc.",
            "profession": "Profession",
            "revenusannuels": "Montant en euros (nombre entier)",
            "nombreenfants": "Nombre d'enfants",
            "besoins": "Résumé des besoins exprimés (ex: assurance habitation, prêt, etc.)"
            }

            Le champ "nom" peut être énoncé sous forme épelée (ex: "L A B A R R E").
            Si c’est le cas, reconstitue correctement le mot sans espaces : "Labarre".

            Ne renvoie **rien d’autre** que ce JSON.
            Voici le texte à analyser :
            ---
            $transcription
            ---
        PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'OpenAI-Organization' => env('OPENAI_ORG_ID'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5', // ✅ ton modèle préféré
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<PROMPT
                        Tu es un assistant spécialisé en analyse de conversations pour un CRM.

                        Ta tâche :
                        - Extraire et structurer les informations concernant un client à partir d'une transcription vocale.
                        - Tu dois produire un JSON contenant uniquement les champs mentionnés ou inférés.
                        - Ne jamais inventer de données qui n’existent pas dans la transcription.
                        - Si un champ n’est **pas mentionné**, ne l’inclus pas dans le JSON.
                        - Si un champ est **mentionné mais épelé lettre par lettre** (ex: "L A B A R R E"), reconstruis-le correctement ("Labarre").

                        Format attendu (uniquement avec les champs trouvés) :
                        {
                        "nom": "string",
                        "prenom": "string",
                        "datedenaissance": "string (JJ/MM/AAAA ou AAAA-MM-JJ)",
                        "lieudenaissance": "string",
                        "situationmatrimoniale": "string",
                        "profession": "string",
                        "revenusannuels": "number ou string",
                        "nombreenfants": "number",
                        "besoins": "string"
                        }

                        Règles :
                        - Si tu ne trouves pas une valeur, n'inclus pas la clé correspondante.
                        - Si le nom de famille est épelé, recompose-le correctement.
                        - Si des nombres sont cités en mots (“trente-six mille cinq cents euros”), convertis-les en chiffres (“36500”).
                        - Ne réponds **que** avec un JSON valide, sans texte explicatif.
                        PROMPT
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
            ]);

            $json = $response->json();
            Log::info($response->json());
            $raw = $json['choices'][0]['message']['content'] ?? '';

            // 🧾 Log brut pour debug
            Log::info('Réponse brute OpenAI', ['raw' => $raw]);

            // ✅ On isole le JSON proprement
            $raw = trim($raw);
            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $raw = $matches[0];
            }

            $data = json_decode($raw, true);

            if (!is_array($data)) {
                Log::warning('Impossible de parser la réponse GPT', ['content' => $raw]);
                return [];
            }

            // 🧹 Normalisation
            foreach ([
                'nom', 'prenom', 'datedenaissance', 'lieudenaissance',
                'situationmatrimoniale', 'profession', 'revenusannuels',
                'nombreenfants', 'besoins'
            ] as $field) {
                $data[$field] = $data[$field] ?? null;
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Erreur dans AnalysisService', ['message' => $e->getMessage()]);
            return [];
        }
    }
}
