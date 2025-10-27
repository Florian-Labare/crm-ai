<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Services\TranscriptionService;
use App\Services\AnalysisService;
use App\Services\ClientSyncService;
use App\Models\AudioRecord;

class AudioController extends Controller
{
    public function upload(
        Request $request,
        TranscriptionService $transcription,
        AnalysisService $analysis,
        ClientSyncService $clientSync
    ): JsonResponse {

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:51200',
        ]);

        // 1️⃣ Stockage temporaire
        $path = $request->file('audio')->store('audio', 'public');

        // 2️⃣ Création de l’entrée audio
        $record = AudioRecord::create([
            'path' => $path,
            'status' => 'processing',
        ]);

        // 3️⃣ Transcription via Whisper
        $text = $transcription->transcribe(storage_path("app/public/$path"));

        if (!$text) {
            $record->update(['status' => 'failed']);
            return response()->json(['error' => 'Échec de la transcription'], 500);
        }

        // 4️⃣ Analyse GPT → données structurées
        $data = $analysis->extractClientData($text ?? '');

        // 5️⃣ Synchronisation : création ou mise à jour du client
        $client = $clientSync->findOrCreateFromAnalysis($data);

        // 6️⃣ Mise à jour de l’audio
        $record->update([
            'client_id' => $client->id,
            'status' => 'done',
            'transcription' => $text,
            'processed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Audio traité et client synchronisé avec succès.',
            'client' => $client,
            'analysis' => $data,
        ]);
    }
}