<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\AudioRecord;
use Illuminate\Http\Request;
use App\Services\AnalysisService;
use Illuminate\Http\JsonResponse;
use App\Services\ClientSyncService;
use App\Services\TranscriptionService;
use Illuminate\Support\Facades\Storage;

class AudioController extends Controller
{
    public function upload(
        Request $request,
        TranscriptionService $transcriptionService,
        AnalysisService $analysisService,
        ClientSyncService $clientSyncService
    ): JsonResponse {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,webm|max:10240',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);
    
        // 🔊 1. Enregistrement du fichier audio
        $path = $request->file('audio')->store('audio_uploads', 'public');
    
        // 🧠 2. Transcription du vocal
        $transcription = $transcriptionService->transcribe(storage_path("app/public/$path"));
    
        // 💬 3. Analyse du texte via GPT
        $data = $analysisService->extractClientData($transcription);
    
        // 🔍 4. Si un client_id est fourni → on met à jour CE client
        if ($request->filled('client_id')) {
            $client = Client::findOrFail($request->input('client_id'));
            $client->fill(array_filter($data)); // n’écrase que les champs renseignés
            if ($client->isDirty()) $client->save();
        } else {
            // 🆕 Sinon, création ou MAJ automatique selon les infos extraites
            $client = $clientSyncService->findOrCreateFromAnalysis($data);
        }
    
        // ✅ 5. Sauvegarde de l’audio dans la table audio_records
        AudioRecord::create([
            'path' => $path,
            'status' => 'done',
            'client_id' => $client->id,
        ]);
    
        return response()->json([
            'message' => 'Analyse terminée',
            'client' => $client,
            'transcription' => $transcription,
            'data' => $data,
        ]);
    }
    
}