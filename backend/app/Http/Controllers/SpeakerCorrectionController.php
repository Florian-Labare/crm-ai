<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller pour la correction manuelle des speakers identifiés
 */
class SpeakerCorrectionController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }
    /**
     * Récupère les informations de diarisation d'un enregistrement
     */
    public function show(AudioRecord $audioRecord): JsonResponse
    {
        if (!$audioRecord->diarization_data) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune donnée de diarisation disponible pour cet enregistrement'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'audio_record_id' => $audioRecord->id,
            'diarization' => [
                'success' => $audioRecord->diarization_success,
                'total_speakers' => $audioRecord->diarization_data['total_speakers'] ?? 0,
                'single_speaker_mode' => $audioRecord->diarization_data['single_speaker_mode'] ?? false,
                'stats' => $audioRecord->diarization_data['stats'] ?? null,
            ],
            'speakers' => $audioRecord->getSpeakersWithRoles(),
            'corrections' => [
                'applied' => $audioRecord->speakers_corrected,
                'corrections' => $audioRecord->speaker_corrections ?? [],
                'corrected_at' => $audioRecord->corrected_at?->toISOString(),
                'corrected_by' => $audioRecord->corrector?->name
            ]
        ]);
    }

    /**
     * Applique une correction de speaker
     */
    public function correct(Request $request, AudioRecord $audioRecord): JsonResponse
    {
        $validated = $request->validate([
            'speaker_id' => 'required|string',
            'role' => 'required|in:broker,client'
        ]);

        if (!$audioRecord->diarization_data) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune donnée de diarisation disponible pour cet enregistrement'
            ], 404);
        }

        try {
            $audioRecord->applySpeakerCorrection(
                $validated['speaker_id'],
                $validated['role'],
                auth()->id()
            );

            Log::info('[SPEAKER CORRECTION] Correction appliquée', [
                'audio_record_id' => $audioRecord->id,
                'speaker_id' => $validated['speaker_id'],
                'new_role' => $validated['role'],
                'corrected_by' => auth()->id()
            ]);

            // Audit de la correction
            $this->auditService->logSpeakerCorrection($audioRecord, $audioRecord->speaker_corrections);

            return response()->json([
                'success' => true,
                'message' => 'Correction appliquée avec succès',
                'speakers' => $audioRecord->getSpeakersWithRoles(),
                'corrections' => $audioRecord->speaker_corrections
            ]);

        } catch (\Exception $e) {
            Log::error('[SPEAKER CORRECTION] Erreur lors de la correction', [
                'audio_record_id' => $audioRecord->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'application de la correction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Applique plusieurs corrections en une seule fois
     */
    public function correctBatch(Request $request, AudioRecord $audioRecord): JsonResponse
    {
        $validated = $request->validate([
            'corrections' => 'required|array',
            'corrections.*.speaker_id' => 'required|string',
            'corrections.*.role' => 'required|in:broker,client'
        ]);

        if (!$audioRecord->diarization_data) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune donnée de diarisation disponible pour cet enregistrement'
            ], 404);
        }

        try {
            foreach ($validated['corrections'] as $correction) {
                $audioRecord->applySpeakerCorrection(
                    $correction['speaker_id'],
                    $correction['role'],
                    auth()->id()
                );
            }

            Log::info('[SPEAKER CORRECTION] Corrections batch appliquées', [
                'audio_record_id' => $audioRecord->id,
                'corrections_count' => count($validated['corrections']),
                'corrected_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => count($validated['corrections']) . ' correction(s) appliquée(s)',
                'speakers' => $audioRecord->getSpeakersWithRoles(),
                'corrections' => $audioRecord->speaker_corrections
            ]);

        } catch (\Exception $e) {
            Log::error('[SPEAKER CORRECTION] Erreur lors des corrections batch', [
                'audio_record_id' => $audioRecord->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'application des corrections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialise les corrections d'un enregistrement
     */
    public function reset(AudioRecord $audioRecord): JsonResponse
    {
        if (!$audioRecord->speakers_corrected) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune correction à réinitialiser'
            ], 400);
        }

        try {
            $audioRecord->update([
                'speaker_corrections' => null,
                'speakers_corrected' => false,
                'corrected_at' => null,
                'corrected_by' => null
            ]);

            Log::info('[SPEAKER CORRECTION] Corrections réinitialisées', [
                'audio_record_id' => $audioRecord->id,
                'reset_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corrections réinitialisées',
                'speakers' => $audioRecord->getSpeakersWithRoles()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste les enregistrements qui pourraient nécessiter une révision
     */
    public function needsReview(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $records = AudioRecord::with(['client:id,nom,prenom', 'user:id,name'])
            ->where('diarization_success', true)
            ->where('speakers_corrected', false)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total()
            ]
        ]);
    }
}
