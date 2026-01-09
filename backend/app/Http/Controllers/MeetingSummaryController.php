<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use App\Models\Client;
use App\Services\MeetingSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MeetingSummaryController extends Controller
{
    public function showLatest(Client $client): JsonResponse
    {
        $summary = $client->meetingSummaries()
            ->latest()
            ->first();

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function regenerate(Request $request, Client $client, MeetingSummaryService $summaryService): JsonResponse
    {
        $audioRecordId = $request->input('audio_record_id');

        $audioRecordQuery = AudioRecord::where('client_id', $client->id);
        if ($audioRecordId) {
            $audioRecordQuery->where('id', $audioRecordId);
        }

        $audioRecord = $audioRecordQuery
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$audioRecord) {
            return response()->json(['message' => 'Aucun enregistrement audio trouvé.'], 404);
        }

        if (empty($audioRecord->transcription)) {
            return response()->json(['message' => 'Transcription indisponible pour cet enregistrement.'], 422);
        }

        $payload = $summaryService->generateSummary($audioRecord->transcription);
        if (empty($payload['summary_text']) && empty($payload['summary_json'])) {
            return response()->json(['message' => 'Résumé non généré.'], 500);
        }

        $summary = $summaryService->storeSummary(
            $client->id,
            $request->user()->id,
            $audioRecord->id,
            $payload
        );

        return response()->json(['data' => $summary]);
    }
}
