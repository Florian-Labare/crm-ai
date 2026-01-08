<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\DocumentGeneratorService;
use App\Services\DocumentTemplateFormService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    private DocumentGeneratorService $documentGeneratorService;
    private DocumentTemplateFormService $formService;

    public function __construct(
        DocumentGeneratorService $documentGeneratorService,
        DocumentTemplateFormService $formService
    )
    {
        $this->documentGeneratorService = $documentGeneratorService;
        $this->formService = $formService;
    }

    /**
     * Liste tous les templates de documents actifs
     */
    public function listTemplates(): JsonResponse
    {
        $templates = DocumentTemplate::active()->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Liste tous les documents générés pour un client
     */
    public function listClientDocuments(int $clientId): JsonResponse
    {
        $client = Client::findOrFail($clientId);

        $documents = GeneratedDocument::where('client_id', $clientId)
            ->with(['documentTemplate', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Génère un nouveau document pour un client
     */
    public function generateDocument(Request $request, int $clientId): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:document_templates,id',
            'format' => 'sometimes|in:pdf,docx',
        ]);

        try {
            $client = Client::findOrFail($clientId);
            $template = DocumentTemplate::findOrFail($request->template_id);
            $format = $request->format ?? 'docx';

            $overrides = $this->formService->getSavedValues($template, $client);

            // Générer le document
            $generatedDocument = $this->documentGeneratorService->generateDocument(
                $client,
                $template,
                auth()->id() ?? 1, // Utiliser l'utilisateur connecté
                $format,
                $overrides
            );

            // Charger les relations pour la réponse
            $generatedDocument->load(['documentTemplate', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Document généré avec succès',
                'data' => $generatedDocument,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retourne le formulaire associé à un template pour un client.
     */
    public function showForm(int $clientId, int $templateId): JsonResponse
    {
        $client = Client::findOrFail($clientId);
        $template = DocumentTemplate::findOrFail($templateId);

        try {
            $fields = $this->formService->getFields($template, $client);

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'file_path' => $template->file_path,
                    ],
                    'fields' => $fields,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du formulaire',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sauvegarde les valeurs du formulaire associé à un template.
     */
    public function saveForm(Request $request, int $clientId, int $templateId): JsonResponse
    {
        $request->validate([
            'values' => 'required|array',
        ]);

        $client = Client::findOrFail($clientId);
        $template = DocumentTemplate::findOrFail($templateId);

        try {
            $this->formService->saveValues($template, $client, $request->input('values', []));

            return response()->json([
                'success' => true,
                'message' => 'Formulaire enregistré',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du formulaire',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Télécharge un document généré
     */
    public function downloadDocument(int $documentId): BinaryFileResponse
    {
        $document = GeneratedDocument::findOrFail($documentId);

        $filePath = Storage::path($document->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'Fichier non trouvé');
        }

        return response()->download($filePath);
    }

    /**
     * Envoie un document par email au client
     */
    public function sendDocumentByEmail(int $documentId): JsonResponse
    {
        try {
            $document = GeneratedDocument::with('client')->findOrFail($documentId);
            $client = $document->client;

            if (!$client->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le client n\'a pas d\'adresse email',
                ], 400);
            }

            // TODO: Implémenter l'envoi d'email avec Laravel Mail
            // Mail::to($client->email)->send(new DocumentMail($document));

            // Pour l'instant, on marque simplement comme envoyé
            $document->markAsSent();

            return response()->json([
                'success' => true,
                'message' => 'Document envoyé par email avec succès',
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprime un document généré
     */
    public function deleteDocument(int $documentId): JsonResponse
    {
        try {
            $document = GeneratedDocument::findOrFail($documentId);

            // Supprimer le fichier physique
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Supprimer l'entrée en base de données
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
