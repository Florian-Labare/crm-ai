<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAudioRequest;
use App\Http\Resources\AudioRecordResource;
use App\Models\AudioRecord;
use App\Models\Client;
use App\Scopes\TeamScope;
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
        // Récupérer l'enregistrement audio (sans eager load le client pour éviter les erreurs de scope)
        $audioRecord = AudioRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        // Charger le client séparément, sans le TeamScope pour éviter les erreurs
        // si le client a été créé/modifié par le job et n'est pas encore accessible via le scope
        if ($audioRecord->client_id) {
            $client = Client::withoutGlobalScope(TeamScope::class)
                ->with([
                    'conjoint',
                    'enfants',
                    'santeSouhait',
                    'baePrevoyance',
                    'baeRetraite',
                    'baeEpargne',
                ])
                ->find($audioRecord->client_id);

            // Associer le client chargé à l'audioRecord pour la resource
            if ($client) {
                $audioRecord->setRelation('client', $client);
            }
        }

        // Retourner la resource formatée
        return AudioRecordResource::make($audioRecord)
            ->response();
    }
}
