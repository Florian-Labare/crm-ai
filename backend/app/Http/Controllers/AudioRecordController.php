<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use App\Models\DiarizationLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioRecordController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }
    /**
     * Lister tous les enregistrements audio de la team (avec client associé)
     */
    public function index(): JsonResponse
    {
        // La team scope filtre automatiquement par team_id
        $records = AudioRecord::with('client:id,nom,prenom')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($records);
    }

    /**
     * Voir le détail d'un enregistrement audio
     */
    public function show(int $id): JsonResponse
    {
        $record = AudioRecord::with('client')->findOrFail($id);

        // Vérifier l'autorisation via la policy
        Gate::authorize('view', $record);

        return response()->json($record);
    }

    /**
     * Supprimer un enregistrement audio (et le fichier associé)
     * Inclut la suppression en cascade des logs de diarisation
     */
    public function destroy(int $id): JsonResponse
    {
        $record = AudioRecord::findOrFail($id);

        // Vérifier l'autorisation via la policy
        Gate::authorize('delete', $record);

        Log::info('[AUDIO RECORD] Suppression demandée', [
            'audio_record_id' => $record->id,
            'user_id' => auth()->id(),
            'team_id' => $record->team_id
        ]);

        // Supprimer les logs de diarisation associés
        DiarizationLog::where('audio_record_id', $record->id)->delete();

        // Supprimer le fichier audio du stockage
        if ($record->path && Storage::disk('public')->exists($record->path)) {
            Storage::disk('public')->delete($record->path);
        }

        // Supprimer les fichiers temporaires éventuels
        $this->cleanupTempFiles($record);

        $record->delete();

        Log::info('[AUDIO RECORD] Suppression effectuée', [
            'audio_record_id' => $id,
            'user_id' => auth()->id()
        ]);

        // Audit de la suppression
        $this->auditService->logAudioDelete($record);

        return response()->json(['message' => 'Enregistrement supprimé avec succès.']);
    }

    /**
     * Nettoie les fichiers temporaires associés à un enregistrement
     */
    private function cleanupTempFiles(AudioRecord $record): void
    {
        $tempDir = storage_path('app/temp');

        // Supprimer les fichiers de diarisation temporaires
        $patterns = [
            "diarization_{$record->id}_*.json",
            "client_audio_{$record->id}_*.wav",
        ];

        foreach ($patterns as $pattern) {
            $files = glob("{$tempDir}/{$pattern}");
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
