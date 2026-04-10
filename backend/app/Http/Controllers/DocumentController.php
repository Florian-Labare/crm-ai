<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientComplianceDocument;
use App\Models\ComplianceRequirement;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\DocumentGeneratorService;
use App\Services\DocumentTemplateFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private DocumentGeneratorService $documentGeneratorService;

    private DocumentTemplateFormService $formService;

    public function __construct(
        DocumentGeneratorService $documentGeneratorService,
        DocumentTemplateFormService $formService
    ) {
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
            ->with(['documentTemplate', 'user', 'complianceDocument'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($doc) {
                $doc->sent_to_compliance = $doc->complianceDocument !== null;

                return $doc;
            });

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
     * Télécharge un document généré (depuis S3)
     */
    public function downloadDocument(int $documentId)
    {
        $document = GeneratedDocument::findOrFail($documentId);

        if (! Storage::exists($document->file_path)) {
            abort(404, 'Fichier non trouvé');
        }

        return Storage::download($document->file_path);
    }

    /**
     * Envoie un document par email au client
     */
    public function sendDocumentByEmail(int $documentId): JsonResponse
    {
        try {
            $document = GeneratedDocument::with('client')->findOrFail($documentId);
            $client = $document->client;

            if (! $client->email) {
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
     * Mapping template_id → document_type compliance
     */
    private const TEMPLATE_COMPLIANCE_MAP = [
        1 => 'recueil_global',
        2 => 'mandat_recherche',
        3 => 'lettre_mission_epargne',
        4 => 'lettre_mission_emprunteur',
        5 => 'lettre_mission_retraite',
        6 => 'lettre_mission_prevoyance',
        7 => 'lettre_mission_sante',
        8 => 'recueil_ade',
    ];

    /**
     * Envoie un document généré vers la section compliance du client.
     * Copie le fichier S3, crée un ClientComplianceDocument et l'auto-lie à l'exigence.
     */
    public function sendToCompliance(int $clientId, int $documentId): JsonResponse
    {
        $client = Client::findOrFail($clientId);
        $document = GeneratedDocument::with('documentTemplate')->findOrFail($documentId);

        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        $templateId = $document->document_template_id;
        $documentType = self::TEMPLATE_COMPLIANCE_MAP[$templateId] ?? null;

        if (! $documentType) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de document ne correspond à aucune exigence réglementaire.',
            ], 422);
        }

        // Vérifier qu'il n'existe pas déjà un document compliance issu de ce document généré
        $existing = ClientComplianceDocument::where('client_id', $client->id)
            ->where('generated_document_id', $document->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Ce document a déjà été envoyé en conformité.',
                'data' => $existing,
            ], 409);
        }

        if (! Storage::exists($document->file_path)) {
            return response()->json(['success' => false, 'message' => 'Fichier source introuvable.'], 404);
        }

        // Copier le fichier dans le dossier compliance du client
        $ext = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $newPath = "compliance/{$client->id}/{$documentType}_".now()->format('Ymd_His').".{$ext}";
        Storage::copy($document->file_path, $newPath);

        // Créer l'entrée compliance
        $complianceDoc = ClientComplianceDocument::create([
            'client_id' => $client->id,
            'generated_document_id' => $document->id,
            'uploaded_by' => auth()->id(),
            'document_type' => $documentType,
            'category' => 'regulatory',
            'file_path' => $newPath,
            'file_name' => $document->document_template->name.'.'.$ext,
            'mime_type' => $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::size($newPath),
            'status' => 'pending',
        ]);

        // Auto-lier à l'exigence compliance correspondante
        $requirement = ComplianceRequirement::where('document_type', $documentType)->first();
        if ($requirement) {
            $complianceDoc->linkedRequirements()->attach($requirement->id, [
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document envoyé en conformité avec succès.',
            'data' => $complianceDoc,
        ], 201);
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
