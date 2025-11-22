<?php

namespace App\Http\Controllers;

use App\Models\QuestionnaireRisque;
use App\Services\ScoringService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionnaireRisqueController extends Controller
{
    public function __construct(
        private ScoringService $scoringService
    ) {}

    public function live(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'financier' => 'sometimes|array',
            'connaissances' => 'sometimes|array',
            'quiz' => 'sometimes|array',
        ]);

        $clientId = $request->input('client_id');

        $questionnaire = QuestionnaireRisque::firstOrCreate(
            ['client_id' => $clientId],
            [
                'score_global' => 0,
                'profil_calcule' => 'Prudent',
                'recommandation' => '',
            ]
        );
        $questionnaire->loadMissing(['financier', 'connaissances', 'quiz']);

        $mergedFinancier = $this->mergeSectionData(
            $questionnaire->financier,
            $request->input('financier', [])
        );

        $mergedConnaissances = $this->mergeSectionData(
            $questionnaire->connaissances,
            $request->input('connaissances', [])
        );

        $mergedQuiz = $this->mergeSectionData(
            $questionnaire->quiz,
            $request->input('quiz', [])
        );

        $data = [
            'financier' => $mergedFinancier,
            'connaissances' => $mergedConnaissances,
            'quiz' => $mergedQuiz,
        ];

        $questionnaire = $this->scoringService->scorerEtSauvegarder($questionnaire, $data);

        $questionnaire->load(['financier', 'connaissances', 'quiz']);

        return response()->json([
            'score' => $questionnaire->score_global,
            'profil' => $questionnaire->profil_calcule,
            'recommandation' => $questionnaire->recommandation,
            'questionnaire' => $questionnaire,
        ]);
    }

    public function show(int $clientId): JsonResponse
    {
        $questionnaire = QuestionnaireRisque::with(['financier', 'connaissances', 'quiz'])
            ->where('client_id', $clientId)
            ->first();

        if (!$questionnaire) {
            return response()->json([
                'questionnaire' => null,
                'score' => 0,
                'profil' => 'Prudent',
                'recommandation' => '',
            ]);
        }

        return response()->json([
            'questionnaire' => $questionnaire,
            'score' => $questionnaire->score_global,
            'profil' => $questionnaire->profil_calcule,
            'recommandation' => $questionnaire->recommandation,
        ]);
    }

    /**
     * Fusionne les réponses existantes avec les nouvelles valeurs saisies côté frontend.
     */
    private function mergeSectionData(?Model $section, array $incoming): array
    {
        $existing = $section
            ? collect($section->getAttributes())
                ->except(['id', 'questionnaire_risque_id', 'created_at', 'updated_at'])
                ->toArray()
            : [];

        foreach ($incoming as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === '') {
                    $incoming[$key] = null;
                    continue;
                }

                $lower = strtolower($trimmed);
                if (in_array($lower, ['true', 'false', '0', '1'], true)) {
                    $incoming[$key] = $lower === 'true' || $lower === '1';
                    continue;
                }

                $incoming[$key] = $trimmed;
                continue;
            }

            if (is_bool($value)) {
                $incoming[$key] = $value;
            }
        }

        return array_merge($existing, $incoming);
    }
}
