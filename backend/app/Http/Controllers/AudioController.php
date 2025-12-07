<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAudioRequest;
use App\Http\Resources\AudioRecordResource;
use App\Models\AudioRecord;
use App\Services\AudioService;
use Illuminate\Http\JsonResponse;

/**
 * Audio Controller
 *
 * Gère les enregistrements audio selon les conventions Laravel Boost
 */
class AudioController extends Controller
{
    /**
     * Injecter le service Audio
     */
    public function __construct(
        private readonly AudioService $audioService
    ) {
    }

    /**
     * Upload d'un fichier audio et traitement asynchrone
     */
    public function upload(StoreAudioRequest $request): JsonResponse
    {
        // Déléguer la logique au service
        $audioRecord = $this->audioService->uploadAndProcess(
            $request->file('audio'),
            $request->input('client_id'),
            auth()->id(),
            auth()->user()->currentTeam()->id // Pass team_id
        );

        // Retourner la resource avec status 202 Accepted
        return AudioRecordResource::make($audioRecord)
            ->additional([
                'message' => 'Audio en cours de traitement',
            ])
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Vérifier le statut d'un enregistrement audio
     */
    public function status(int $id): JsonResponse
    {
        // Récupérer l'enregistrement avec relations
        $audioRecord = AudioRecord::with([
            'client.conjoint',
            'client.enfants',
            'client.santeSouhait',
            'client.baePrevoyance',
            'client.baeRetraite',
            'client.baeEpargne',
        ])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // Retourner la resource formatée
        return AudioRecordResource::make($audioRecord)
            ->response();
    }
}
