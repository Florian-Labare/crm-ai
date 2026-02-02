<?php

namespace App\Services;

use App\Models\MeetingSummary;
use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Log;

class MeetingSummaryService
{
    use LlmClientTrait;

    public function generateSummary(string $transcription): array
    {
        $systemPrompt = <<<'SYSTEM'
Tu es un assistant spécialisé en production de comptes-rendus d'entretien client en français.

[OBJECTIF]
Générer un résumé structuré et détaillé d'un entretien client à partir d'une transcription vocale.

[RÈGLES]
1. Respecter l'ordre chronologique des échanges
2. Structurer de manière hiérarchique (sections > points > détails)
3. N'inclure QUE les informations mentionnées par le client
4. Ignorer les questions/phrases du conseiller non confirmées par le client
5. Répondre UNIQUEMENT avec du JSON valide

[FORMAT DE SORTIE]
Réponds UNIQUEMENT avec un JSON valide au format spécifié, sans texte avant ou après.
SYSTEM;

        $userPrompt = <<<PROMPT
Génère un résumé de rendez-vous détaillé à partir de cette transcription.

Réponds STRICTEMENT au format JSON suivant :
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

[EXEMPLES]

Input: "Je m'appelle Jean Dupont, j'ai 45 ans. Je veux préparer ma retraite et protéger ma famille."
Output: {"overview": "Entretien avec Jean Dupont, 45 ans, qui souhaite préparer sa retraite et mettre en place une protection familiale.", "chronology": [{"phase": "Présentation", "topics": [{"title": "Identité", "details": ["Jean Dupont", "45 ans"]}]}, {"phase": "Expression des besoins", "topics": [{"title": "Objectifs", "details": ["Préparation retraite", "Protection famille"]}]}], "key_points": [{"section": "Identité", "items": ["Jean Dupont, 45 ans"]}], "needs": ["Retraite", "Prévoyance"], "next_steps": []}

Transcription :
---
$transcription
---
PROMPT;

        try {
            $summaryJson = $this->callLlm(
                $systemPrompt,
                $userPrompt,
                0.2,
                true
            );

            if (!$summaryJson) {
                return [
                    'summary_text' => null,
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
