<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientComplianceDocument;
use App\Models\ComplianceRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ClientComplianceController extends Controller
{
    /**
     * Retourne le statut de compliance d'un client avec les documents requis et fournis
     */
    public function status(Client $client): JsonResponse
    {
        // Récupérer les besoins du client
        $besoins = $this->normalizeBesoins($client->besoins ?? []);

        // Récupérer les documents requis pour ces besoins
        $requirements = ComplianceRequirement::getRequirementsForBesoins($besoins);

        // Récupérer les documents fournis par le client
        $documents = $client->complianceDocuments()
            ->orderBy('created_at', 'desc')
            ->get();

        // Construire le statut pour chaque exigence
        $checklistItems = [];
        $validCount = 0;
        $totalMandatory = 0;

        foreach ($requirements as $requirement) {
            // Trouver le document correspondant (le plus récent validé, sinon le plus récent)
            $matchingDoc = $documents
                ->where('document_type', $requirement->document_type)
                ->first();

            $status = 'missing';
            $isValid = false;

            if ($matchingDoc) {
                if ($matchingDoc->status === 'validated' && !$matchingDoc->isExpired()) {
                    $status = 'valid';
                    $isValid = true;
                } elseif ($matchingDoc->status === 'pending') {
                    $status = 'pending';
                } elseif ($matchingDoc->status === 'rejected') {
                    $status = 'rejected';
                } elseif ($matchingDoc->isExpired()) {
                    $status = 'expired';
                }
            }

            if ($requirement->is_mandatory) {
                $totalMandatory++;
                if ($isValid) {
                    $validCount++;
                }
            }

            $checklistItems[] = [
                'requirement_id' => $requirement->id,
                'document_type' => $requirement->document_type,
                'label' => $requirement->document_label,
                'category' => $requirement->category,
                'besoin' => $requirement->besoin,
                'besoin_label' => $requirement->besoin_label,
                'is_mandatory' => $requirement->is_mandatory,
                'status' => $status,
                'is_valid' => $isValid,
                'document' => $matchingDoc ? [
                    'id' => $matchingDoc->id,
                    'file_name' => $matchingDoc->file_name,
                    'status' => $matchingDoc->status,
                    'validated_at' => $matchingDoc->validated_at,
                    'expires_at' => $matchingDoc->expires_at,
                    'uploaded_at' => $matchingDoc->created_at,
                    'notes' => $matchingDoc->notes,
                    'rejection_reason' => $matchingDoc->rejection_reason,
                ] : null,
            ];
        }

        // Calculer le score global
        $complianceScore = $totalMandatory > 0 ? round(($validCount / $totalMandatory) * 100) : 0;
        $isFullyCompliant = $validCount === $totalMandatory && $totalMandatory > 0;

        // Grouper par catégorie pour l'affichage
        $groupedByCategory = collect($checklistItems)->groupBy('category')->map(function ($items, $category) {
            $labels = [
                'identity' => 'Documents d\'identité',
                'fiscal' => 'Documents fiscaux',
                'regulatory' => 'Documents réglementaires',
            ];
            return [
                'category' => $category,
                'label' => $labels[$category] ?? $category,
                'items' => $items->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'client_id' => $client->id,
                'besoins' => $besoins,
                'compliance_score' => $complianceScore,
                'is_fully_compliant' => $isFullyCompliant,
                'valid_count' => $validCount,
                'total_mandatory' => $totalMandatory,
                'checklist' => $checklistItems,
                'grouped_by_category' => $groupedByCategory,
            ],
        ]);
    }

    /**
     * Upload un document de compliance
     */
    public function upload(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
            'document_type' => 'required|string',
            'expires_at' => 'nullable|date',
            'document_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $file = $request->file('file');
            $documentType = $request->input('document_type');

            // Déterminer la catégorie
            $category = $this->getCategoryForDocumentType($documentType);

            // Stocker le fichier
            $path = $file->store("compliance/{$client->id}", 'public');

            // Créer l'enregistrement
            $document = ClientComplianceDocument::create([
                'client_id' => $client->id,
                'uploaded_by' => auth()->id(),
                'document_type' => $documentType,
                'category' => $category,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending',
                'expires_at' => $request->input('expires_at'),
                'document_date' => $request->input('document_date'),
                'notes' => $request->input('notes'),
            ]);

            Log::info("📄 [COMPLIANCE] Document uploadé pour client #{$client->id}", [
                'document_type' => $documentType,
                'file_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploadé avec succès',
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [COMPLIANCE] Erreur upload", ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document',
            ], 500);
        }
    }

    /**
     * Valide un document
     */
    public function validate(Request $request, Client $client, ClientComplianceDocument $document): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        $request->validate([
            'expires_at' => 'nullable|date',
        ]);

        $document->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => auth()->id(),
            'expires_at' => $request->input('expires_at', $document->expires_at),
            'rejection_reason' => null,
        ]);

        Log::info("✅ [COMPLIANCE] Document validé", [
            'document_id' => $document->id,
            'client_id' => $client->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document validé',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * Rejette un document
     */
    public function reject(Request $request, Client $client, ClientComplianceDocument $document): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason'),
        ]);

        Log::info("❌ [COMPLIANCE] Document rejeté", [
            'document_id' => $document->id,
            'client_id' => $client->id,
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document rejeté',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * Télécharge un document
     */
    public function download(Client $client, ClientComplianceDocument $document)
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Supprime un document
     */
    public function destroy(Client $client, ClientComplianceDocument $document): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        try {
            // Supprimer le fichier
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();

            Log::info("🗑️ [COMPLIANCE] Document supprimé", [
                'document_id' => $document->id,
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé',
            ]);
        } catch (\Exception $e) {
            Log::error("❌ [COMPLIANCE] Erreur suppression", ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Normalise les besoins du client pour le matching
     */
    private function normalizeBesoins(?array $besoins): array
    {
        if (empty($besoins)) {
            return [];
        }

        $normalized = [];
        $mapping = [
            'prévoyance' => 'prevoyance',
            'prevoyance' => 'prevoyance',
            'retraite' => 'retraite',
            'épargne' => 'epargne',
            'epargne' => 'epargne',
            'santé' => 'sante',
            'sante' => 'sante',
            'immobilier' => 'immobilier',
            'fiscalité' => 'fiscalite',
            'fiscalite' => 'fiscalite',
            'défiscalisation' => 'fiscalite',
            'placement' => 'epargne',
            'assurance vie' => 'epargne',
            'per' => 'retraite',
            'mutuelle' => 'sante',
            'complémentaire santé' => 'sante',
        ];

        foreach ($besoins as $besoin) {
            $key = mb_strtolower(trim($besoin));
            if (isset($mapping[$key])) {
                $normalized[] = $mapping[$key];
            }
        }

        return array_unique($normalized);
    }

    /**
     * Détermine la catégorie d'un type de document
     */
    private function getCategoryForDocumentType(string $documentType): string
    {
        $identityTypes = ['cni', 'passeport', 'titre_sejour'];
        $fiscalTypes = ['avis_imposition', 'avis_imposition_n1', 'avis_imposition_n2'];

        if (in_array($documentType, $identityTypes)) {
            return 'identity';
        }
        if (in_array($documentType, $fiscalTypes)) {
            return 'fiscal';
        }
        return 'regulatory';
    }
}
