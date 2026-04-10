<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDerRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\DerService;
use App\Services\OAuthMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * DER Controller
 *
 * Gère la création de rendez-vous et l'envoi du Document d'Entrée en Relation
 */
class DerController extends Controller
{
    /**
     * Injecter le service DER
     */
    public function __construct(
        private readonly DerService $derService,
        private readonly OAuthMailService $oauthMailService,
    ) {}

    /**
     * Afficher le formulaire de création de rendez-vous DER
     */
    public function create(): JsonResponse
    {
        $currentTeam = auth()->user()->currentTeam();

        if (! $currentTeam) {
            return response()->json([
                'error' => 'No team found for current user',
                'mias' => [],
            ], 400);
        }

        // Récupérer les utilisateurs de l'équipe ayant le rôle "MIA"
        $mias = $currentTeam->users()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'MIA');
            })
            ->get(['users.id', 'users.name', 'users.email']);

        return response()->json([
            'mias' => $mias,
        ]);
    }

    /**
     * Créer un prospect, générer et envoyer le DER
     */
    public function store(StoreDerRequest $request): JsonResponse
    {
        try {
            Log::info("📋 Création d'un nouveau rendez-vous DER");

            // 1. Créer le client (prospect)
            $client = Client::create([
                'user_id' => auth()->id(),
                'team_id' => auth()->user()->currentTeam()->id,
                'der_charge_clientele_id' => $request->charge_clientele_id,
                'civilite' => $request->civilite,
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'der_lieu_rdv' => $request->lieu_rdv,
                'der_date_rdv' => $request->date_rdv,
                'der_heure_rdv' => $request->heure_rdv,
            ]);

            Log::info("✅ Client créé : #{$client->id}");

            // 2. Récupérer le chargé de clientèle
            $chargeClientele = User::findOrFail($request->charge_clientele_id);

            // 3. Générer le DER
            $derFilePath = $this->derService->generateDer($client, $chargeClientele);

            // 4. Envoyer le DER par email depuis le compte du user connecté (secrétaire / super admin)
            $sender = auth()->user();
            $subject = "Votre Document d'Entrée en Relation - {$client->prenom} ".strtoupper($client->nom);
            $htmlBody = view('emails.der', [
                'client' => $client,
                'chargeClientele' => $chargeClientele,
                'sender' => $sender,
            ])->render();
            $attachmentName = 'DER_'.strtoupper($client->nom).'_'.strtoupper($client->prenom).'.docx';

            $this->oauthMailService->sendEmail(
                $sender,
                $client->email,
                $subject,
                $htmlBody,
                $derFilePath,
                $attachmentName,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );

            Log::info("📧 DER envoyé par email à {$client->email}");

            // 5. Supprimer le fichier temporaire
            $this->derService->cleanupTempFile($derFilePath);

            return response()->json([
                'message' => 'Rendez-vous créé et DER envoyé avec succès',
                'client' => $client,
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erreur lors de la création du rendez-vous DER : '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Erreur lors de la création du rendez-vous',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
