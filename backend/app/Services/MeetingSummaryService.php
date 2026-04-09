<?php

namespace App\Services;

use App\Models\MeetingSummary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeetingSummaryService
{
    public function generateSummary(string $transcription): array
    {
        $prompt = <<<PROMPT
            Génère un résumé de rendez-vous détaillé à partir de cette transcription.
            Le résumé doit :
            - Respecter l'ordre chronologique des échanges.
            - Être hiérarchique (sections > points > détails).
            - N'inclure que les informations réellement mentionnées par le client.
            - Ignorer les questions/phrases du conseiller si elles ne sont pas confirmées par le client.

            Réponds STRICTEMENT au format JSON suivant (sans texte autour) :
            {
              "overview": "Résumé court en 2-4 phrases",
              "chronology": [
                {
                  "phase": "Phase/étape chronologique",
                  "topics": [
                    {
                      "title": "Sujet évoqué",
                      "details": ["fait/élément 1", "fait/élément 2"]
                    }
                  ]
                }
              ],
              "key_points": [
                {
                  "section": "Données essentielles",
                  "items": ["point clé 1", "point clé 2"]
                }
              ],
              "needs": ["Besoins exprimés (ex: Retraite, Épargne)"],
              "next_steps": ["Actions ou suites évoquées, si présentes"]
            }

            Transcription :
            ---
            $transcription
            ---
        PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'OpenAI-Organization' => env('OPENAI_ORG_ID'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Tu es un assistant qui produit des comptes-rendus d'entretien client en français.",
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.2,
            ]);

            $raw = $response->json('choices.0.message.content', '');
            $summaryJson = $this->extractJson($raw);

            if (!$summaryJson) {
                return [
                    'summary_text' => trim($raw),
                    'summary_json' => null,
                ];
            }

            $summaryText = $this->formatSummaryText($summaryJson);

            return [
                'summary_text' => $summaryText,
                'summary_json' => $summaryJson,
            ];
        } catch (\Throwable $e) {
            Log::error('MeetingSummaryService error', ['error' => $e->getMessage()]);
            return [
                'summary_text' => null,
                'summary_json' => null,
            ];
        }
    }

    public function storeSummary(int $clientId, int $userId, ?int $audioRecordId, array $payload): MeetingSummary
    {
        $data = [
            'client_id' => $clientId,
            'created_by' => $userId,
            'summary_text' => $payload['summary_text'] ?? null,
            'summary_json' => $payload['summary_json'] ?? null,
        ];

        if ($audioRecordId) {
            $data['audio_record_id'] = $audioRecordId;
        }

        return MeetingSummary::updateOrCreate(
            ['audio_record_id' => $audioRecordId],
            $data
        );
    }

    private function extractJson(string $raw): ?array
    {
        $trimmed = trim($raw);
        $trimmed = preg_replace('/^```(?:json)?/i', '', $trimmed);
        $trimmed = preg_replace('/```$/', '', $trimmed);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\\{.*\\}/s', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function formatSummaryText(array $summaryJson): string
    {
        $parts = [];
        if (!empty($summaryJson['overview'])) {
            $parts[] = $summaryJson['overview'];
        }

        $chronology = $summaryJson['chronology'] ?? [];
        foreach ($chronology as $phase) {
            $phaseTitle = $phase['phase'] ?? null;
            if ($phaseTitle) {
                $parts[] = $phaseTitle . ':';
            }
            foreach ($phase['topics'] ?? [] as $topic) {
                $topicTitle = $topic['title'] ?? null;
                if ($topicTitle) {
                    $parts[] = '- ' . $topicTitle;
                }
                foreach ($topic['details'] ?? [] as $detail) {
                    $parts[] = '  • ' . $detail;
                }
            }
        }

        return implode("\n", $parts);
    }
}
