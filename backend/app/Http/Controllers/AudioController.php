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

            // 🎯 Gestion intelligente des besoins
            if (isset($data['besoins']) && isset($data['besoins_action'])) {
                $currentBesoins = is_array($client->besoins) ? $client->besoins : [];
                $newBesoins = is_array($data['besoins']) ? $data['besoins'] : [];

                switch ($data['besoins_action']) {
                    case 'add':
                        // Ajouter les nouveaux besoins sans doublon
                        $data['besoins'] = array_values(array_unique(array_merge($currentBesoins, $newBesoins)));
                        break;

                    case 'remove':
                        // Retirer les besoins mentionnés
                        $data['besoins'] = array_values(array_diff($currentBesoins, $newBesoins));
                        break;

                    case 'replace':
                    default:
                        // Remplacer complètement
                        $data['besoins'] = $newBesoins;
                        break;
                }

                // Retirer besoins_action des données à sauvegarder
                unset($data['besoins_action']);
            }

            // Filtrer les données pour ne garder que les champs réellement renseignés
            // On retire : null, chaînes vides, tableaux vides
            $filteredData = array_filter($data, function($value) {
                if ($value === null) return false;
                if ($value === '') return false;
                if (is_array($value) && empty($value)) return false;
                return true;
            });

            $client->fill($filteredData);
            if ($client->isDirty()) $client->save();
        } else {
            // 🆕 Sinon, création ou MAJ automatique selon les infos extraites
            unset($data['besoins_action']); // Pas besoin pour une création
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