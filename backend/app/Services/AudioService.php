<?php

namespace App\Services;

use App\Jobs\ProcessAudioRecording;
use App\Models\AudioRecord;
use App\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

/**
 * Audio Service
 *
 * Gère la logique métier des enregistrements audio
 */
class AudioService
{
    /**
     * Upload et traite un fichier audio
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function uploadAndProcess(
        UploadedFile $audioFile,
        ?int $clientId,
        int $userId,
        int $teamId // Added teamId
    ): AudioRecord {
        // Vérifier l'accès au client si fourni
        if ($clientId !== null) {
            $this->validateClientAccess($clientId, $userId);
        }

        // Sauvegarder le fichier audio
        $path = $audioFile->store('audio_uploads', 'public');

        // Créer l'enregistrement
        $audioRecord = AudioRecord::create([
            'team_id' => $teamId, // Added team_id
            'user_id' => $userId,
            'path' => $path,
            'status' => 'pending',
            'client_id' => $clientId,
        ]);

        // Dispatcher le job de traitement asynchrone
        ProcessAudioRecording::dispatch($audioRecord, $clientId);

        return $audioRecord;
    }

    /**
     * Valide que le client appartient à l'utilisateur
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function validateClientAccess(int $clientId, int $userId): void
    {
        $exists = Client::where('id', $clientId)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw new AuthorizationException(
                'Le client spécifié n\'existe pas ou ne vous appartient pas.'
            );
        }
    }
}
