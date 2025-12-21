<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller pour la correction des speakers de diarisation
 */
class SpeakerCorrectionController extends Controller
{
    /**
     * Affiche les informations de diarisation d'un enregistrement audio
     */
    public function show(AudioRecord $audioRecord): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $audioRecord->id,
                'diarization_data' => $audioRecord->diarization_data ?? [],
                'diarization_success' => $audioRecord->diarization_success ?? false,
                'speaker_corrections' => $audioRecord->speaker_corrections ?? [],
            ]
        ]);
    }

    /**
     * Corrige un speaker spécifique
     */
    public function correct(Request $request, AudioRecord $audioRecord): JsonResponse
    {
        $validated = $request->validate([
            'segment_index' => 'required|integer|min:0',
            'new_speaker' => 'required|string|in:courtier,client',
        ]);

        $corrections = $audioRecord->speaker_corrections ?? [];
        $corrections[$validated['segment_index']] = $validated['new_speaker'];

        $audioRecord->update([
            'speaker_corrections' => $corrections,
        ]);

        Log::info('[SPEAKER_CORRECTION] Correction appliquée', [
            'audio_record_id' => $audioRecord->id,
            'segment_index' => $validated['segment_index'],
            'new_speaker' => $validated['new_speaker'],
        ]);

        return response()->json([
            'message' => 'Correction appliquée',
            'data' => [
                'speaker_corrections' => $corrections,
            ]
        ]);
    }

    /**
     * Corrige plusieurs speakers en batch
     */
    public function correctBatch(Request $request, AudioRecord $audioRecord): JsonResponse
    {
        $validated = $request->validate([
            'corrections' => 'required|array',
            'corrections.*.segment_index' => 'required|integer|min:0',
            'corrections.*.new_speaker' => 'required|string|in:courtier,client',
        ]);

        $corrections = $audioRecord->speaker_corrections ?? [];

        foreach ($validated['corrections'] as $correction) {
            $corrections[$correction['segment_index']] = $correction['new_speaker'];
        }

        $audioRecord->update([
            'speaker_corrections' => $corrections,
        ]);

        Log::info('[SPEAKER_CORRECTION] Corrections batch appliquées', [
            'audio_record_id' => $audioRecord->id,
            'count' => count($validated['corrections']),
        ]);

        return response()->json([
            'message' => 'Corrections appliquées',
            'data' => [
                'speaker_corrections' => $corrections,
            ]
        ]);
    }

    /**
     * Réinitialise toutes les corrections de speaker
     */
    public function reset(AudioRecord $audioRecord): JsonResponse
    {
        $audioRecord->update([
            'speaker_corrections' => [],
        ]);

        Log::info('[SPEAKER_CORRECTION] Corrections réinitialisées', [
            'audio_record_id' => $audioRecord->id,
        ]);

        return response()->json([
            'message' => 'Corrections réinitialisées',
            'data' => [
                'speaker_corrections' => [],
            ]
        ]);
    }

    /**
     * Liste les enregistrements nécessitant une revue de diarisation
     */
    public function needsReview(): JsonResponse
    {
        $records = AudioRecord::where('diarization_success', true)
            ->whereNull('speaker_corrections')
            ->orWhere('speaker_corrections', '[]')
            ->latest()
            ->limit(20)
            ->get(['id', 'client_id', 'created_at', 'diarization_data']);

        return response()->json([
            'data' => $records,
        ]);
    }
}
