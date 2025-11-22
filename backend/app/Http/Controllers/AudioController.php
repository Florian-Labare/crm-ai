<?php

namespace App\Http\Controllers;

use App\Models\AudioRecord;
use App\Jobs\ProcessAudioRecording;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AudioController extends Controller
{
    /**
     * Upload d'un fichier audio et traitement asynchrone
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,webm,m4a,mpeg|max:20480',
            'client_id' => 'nullable|integer',
        ]);

        // Vérifier que le client appartient bien à l'utilisateur connecté (si fourni)
        if ($request->has('client_id') && $request->input('client_id')) {
            $clientExists = \App\Models\Client::where('id', $request->input('client_id'))
                ->where('user_id', auth()->id())
                ->exists();

            if (!$clientExists) {
                return response()->json([
                    'message' => 'Client non trouvé ou accès non autorisé',
                    'errors' => ['client_id' => ['Le client spécifié n\'existe pas ou ne vous appartient pas']]
                ], 422);
            }
        }

        // 🔊 1. Enregistrement du fichier audio
        $path = $request->file('audio')->store('audio_uploads', 'public');

        // 📝 2. Créer l'enregistrement avec status "pending"
        $audioRecord = AudioRecord::create([
            'user_id' => auth()->id(),
            'path' => $path,
            'status' => 'pending',
            'client_id' => $request->input('client_id'), // null si nouveau client
        ]);

        // 🚀 3. Dispatcher le job asynchrone
        ProcessAudioRecording::dispatch($audioRecord, $request->input('client_id'));

        // ✅ 4. Retourner immédiatement la réponse
        return response()->json([
            'message' => 'Audio en cours de traitement',
            'audio_record_id' => $audioRecord->id,
            'status' => 'pending',
        ], 202); // 202 Accepted = traitement asynchrone accepté
    }

    /**
     * Vérifier le statut d'un enregistrement audio
     */
    public function status(int $id): JsonResponse
    {
        // Vérifier que l'enregistrement audio appartient bien à l'utilisateur connecté
        $audioRecord = AudioRecord::with('client')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $response = [
            'id' => $audioRecord->id,
            'status' => $audioRecord->status,
            'processed_at' => $audioRecord->processed_at,
        ];

        // Si le traitement est terminé, inclure les données
        if ($audioRecord->status === 'done' && $audioRecord->client) {
            $response['client'] = $audioRecord->client;
            $response['transcription'] = $audioRecord->transcription;
        }

        // Si échec, inclure l'erreur
        if ($audioRecord->status === 'failed') {
            $response['error'] = $audioRecord->transcription; // Le message d'erreur est stocké ici
        }

        return response()->json($response);
    }

}