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
                    ['role' => 'system', 'content' => 'Tu es un assistant qui structure des données clients.'],
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
