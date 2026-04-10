<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientComplianceDocument;
use App\Models\ComplianceRequirement;
use App\Services\BesoinService;
use App\Services\ComplianceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientComplianceController extends Controller
{
    public function __construct(
        private readonly BesoinService $besoinService,
        private readonly ComplianceStatusService $complianceStatusService,
    ) {}

    /**
     * Retourne un badge simplifié (feu tricolore) pour le statut compliance.
     */
    public function badge(Client $client): JsonResponse
    {
        $badge = $this->complianceStatusService->computeBadge($client);

        return response()->json([
            'success' => true,
            'data' => $badge,
        ]);
    }

    /**
     * Retourne le statut de compliance d'un client avec les documents requis et fournis
     */
    public function status(Client $client): JsonResponse
    {
        // Récupérer les documents fournis par le client
        $documents = $client->complianceDocuments()
            ->orderBy('created_at', 'desc')
            ->get();

        // Récupérer les documents signés (taggés) avec leurs liaisons
        $signedDocs = $documents
            ->where('document_type', 'signed_document')
            ->whereNotNull('tags')
            ->load('linkedRequirements');

        // Extraire les tags uniques de tous les documents signés
        $tagsFromSignedDocs = $signedDocs
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        // Les exigences sont pilotées par les besoins effectifs du client (déclarés + BAE)
        $requirements = $this->complianceStatusService->getRequirements($client);

        // Construire le statut pour chaque exigence
        $checklistItems = [];
        $validCount = 0;
        $totalMandatory = 0;

        foreach ($requirements as $requirement) {
            // Trouver le document correspondant (le plus récent validé, sinon le plus récent)
            $matchingDoc = $documents
                ->where('document_type', $requirement->document_type)
                ->first();

            // Vérifier s'il y a un document signé lié et validé pour cette exigence
            $linkedSignedDoc = null;
            $linkedStatus = null;
            foreach ($signedDocs as $signedDoc) {
                $linkedReq = $signedDoc->linkedRequirements->firstWhere('id', $requirement->id);
                if ($linkedReq) {
                    $linkedSignedDoc = $signedDoc;
                    $linkedStatus = $linkedReq->pivot->status;
                    break;
                }
            }

            $status = 'missing';
            $isValid = false;

            // Priorité : document direct > document signé lié
            if ($matchingDoc) {
                if ($matchingDoc->status === 'validated' && ! $matchingDoc->isExpired()) {
                    $status = 'valid';
                    $isValid = true;
                } elseif ($matchingDoc->status === 'pending') {
                    $status = 'pending';
                } elseif ($matchingDoc->status === 'rejected') {
                    $status = 'rejected';
                } elseif ($matchingDoc->isExpired()) {
                    $status = 'expired';
                }
            } elseif ($linkedSignedDoc) {
                if ($linkedStatus === 'validated') {
                    $status = 'valid';
                    $isValid = true;
                } elseif ($linkedStatus === 'pending') {
                    $status = 'pending';
                } elseif ($linkedStatus === 'rejected') {
                    $status = 'rejected';
                }
            }

            if ($requirement->is_mandatory) {
                $totalMandatory++;
                if ($isValid) {
                    $validCount++;
                }
            }

            // Calculer les infos d'expiration
            $isExpiringSoon = $matchingDoc && $matchingDoc->isExpiringSoon(90);
            $daysUntilExpiration = $matchingDoc ? $matchingDoc->days_until_expiration : null;

            // Trouver les documents signés disponibles pour cette exigence (non encore liés)
            $availableSignedDocs = $signedDocs
                ->filter(function ($doc) use ($requirement) {
                    // Le document doit avoir le tag correspondant au besoin
                    $tags = $doc->tags ?? [];
                    if (! in_array($requirement->besoin, $tags)) {
                        return false;
                    }

                    // Le document ne doit pas déjà être lié à cette exigence
                    return ! $doc->linkedRequirements->contains('id', $requirement->id);
                })
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'custom_label' => $doc->custom_label,
                        'display_label' => $doc->display_label,
                        'tags' => $doc->tags,
                        'uploaded_at' => $doc->created_at,
                    ];
                })
                ->values();

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
                'is_expiring_soon' => $isExpiringSoon,
                'days_until_expiration' => $daysUntilExpiration,
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
                'linked_signed_doc' => $linkedSignedDoc ? [
                    'id' => $linkedSignedDoc->id,
                    'file_name' => $linkedSignedDoc->file_name,
                    'custom_label' => $linkedSignedDoc->custom_label,
                    'display_label' => $linkedSignedDoc->display_label,
                    'status' => $linkedStatus,
                ] : null,
                'available_signed_docs' => $availableSignedDocs,
            ];
        }

        // Calculer le score global
        $complianceScore = $totalMandatory > 0 ? round(($validCount / $totalMandatory) * 100) : 0;
        $isFullyCompliant = $validCount === $totalMandatory && $totalMandatory > 0;

        // Calculer les statistiques globales d'expiration
        $expiredCount = collect($checklistItems)->where('status', 'expired')->count();
        $expiringSoonCount = collect($checklistItems)->where('is_expiring_soon', true)->count();

        // Grouper par catégorie pour l'affichage
        $groupedByCategory = collect($checklistItems)->groupBy('category')->map(function ($items, $category) {
            $labels = [
                'identity' => 'Documents d\'identité',
                'banking' => 'Documents bancaires',
                'fiscal' => 'Documents fiscaux',
                'regulatory' => 'Documents réglementaires',
            ];

            return [
                'category' => $category,
                'label' => $labels[$category] ?? $category,
                'items' => $items->values(),
            ];
        })->values();

        // Préparer la liste des documents signés pour la section dédiée
        $signedDocuments = $signedDocs->map(function ($doc) {
            return [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'custom_label' => $doc->custom_label,
                'display_label' => $doc->display_label,
                'tags' => $doc->tags,
                'status' => $doc->status,
                'uploaded_at' => $doc->created_at,
                'expires_at' => $doc->expires_at,
                'linked_requirements' => $doc->linkedRequirements->map(function ($req) {
                    return [
                        'id' => $req->id,
                        'label' => $req->document_label,
                        'besoin' => $req->besoin,
                        'status' => $req->pivot->status,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'client_id' => $client->id,
                'tags_from_signed_docs' => $tagsFromSignedDocs,
                'compliance_score' => $complianceScore,
                'is_fully_compliant' => $isFullyCompliant,
                'valid_count' => $validCount,
                'total_mandatory' => $totalMandatory,
                'expired_count' => $expiredCount,
                'expiring_soon_count' => $expiringSoonCount,
                'checklist' => $checklistItems,
                'grouped_by_category' => $groupedByCategory,
                'signed_documents' => $signedDocuments,
                'available_tags' => ClientComplianceDocument::AVAILABLE_TAGS,
            ],
        ]);
    }

    /**
     * Retourne les alertes d'expiration pour un client
     */
    public function alerts(Client $client): JsonResponse
    {
        $alerts = [];

        // Documents expirés
        $expiredDocs = $client->complianceDocuments()
            ->expired()
            ->where('status', 'validated')
            ->get();

        foreach ($expiredDocs as $doc) {
            $alerts[] = [
                'type' => 'expired',
                'severity' => 'high',
                'document_id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_label' => $doc->document_label,
                'expires_at' => $doc->expires_at,
                'days_overdue' => abs($doc->days_until_expiration),
                'message' => "Le document \"{$doc->document_label}\" est expiré depuis ".abs($doc->days_until_expiration).' jours',
            ];
        }

        // Documents expirant bientôt
        $expiringSoonDocs = $client->complianceDocuments()
            ->expiringSoon(90)
            ->where('status', 'validated')
            ->get();

        foreach ($expiringSoonDocs as $doc) {
            $alerts[] = [
                'type' => 'expiring_soon',
                'severity' => $doc->days_until_expiration <= 30 ? 'medium' : 'low',
                'document_id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_label' => $doc->document_label,
                'expires_at' => $doc->expires_at,
                'days_until_expiration' => $doc->days_until_expiration,
                'message' => "Le document \"{$doc->document_label}\" expire dans {$doc->days_until_expiration} jours",
            ];
        }

        // Trier par sévérité (high en premier)
        usort($alerts, function ($a, $b) {
            $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

            return $severityOrder[$a['severity']] - $severityOrder[$b['severity']];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'client_id' => $client->id,
                'total_alerts' => count($alerts),
                'has_expired' => count($expiredDocs) > 0,
                'has_expiring_soon' => count($expiringSoonDocs) > 0,
                'alerts' => $alerts,
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
            'document_type' => ['required', 'string', Rule::in(array_keys(ClientComplianceDocument::DOCUMENT_LABELS))],
            'expires_at' => 'nullable|date',
            'document_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $file = $request->file('file');
            $documentType = $request->input('document_type');

            // Déterminer la catégorie
            $category = $this->getCategoryForDocumentType($documentType);

            // Stocker le fichier sur S3 (disk par défaut)
            $path = $file->store("compliance/{$client->id}");

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
            Log::error('❌ [COMPLIANCE] Erreur upload', ['message' => $e->getMessage()]);

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

        Log::info('✅ [COMPLIANCE] Document validé', [
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

        Log::info('❌ [COMPLIANCE] Document rejeté', [
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

        if (! Storage::exists($document->file_path)) {
            return response()->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
        }

        return Storage::download($document->file_path, $document->file_name);
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
            // Supprimer le fichier sur S3
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            $document->delete();

            Log::info('🗑️ [COMPLIANCE] Document supprimé', [
                'document_id' => $document->id,
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé',
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur suppression', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Upload un document signé avec tags
     */
    public function uploadSigned(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'tags' => 'required|array|min:1',
            'tags.*' => ['string', Rule::in(array_keys(ClientComplianceDocument::AVAILABLE_TAGS))],
            'custom_label' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        try {
            $file = $request->file('file');

            // Stocker le fichier sur S3
            $path = $file->store("compliance/{$client->id}");

            // Créer le document avec type "signed_document" et tags
            $document = ClientComplianceDocument::create([
                'client_id' => $client->id,
                'uploaded_by' => auth()->id(),
                'document_type' => 'signed_document',
                'category' => 'signed',
                'tags' => $request->tags,
                'custom_label' => $request->custom_label,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending',
                'expires_at' => $request->expires_at,
            ]);

            Log::info("📄 [COMPLIANCE] Document signé uploadé pour client #{$client->id}", [
                'document_id' => $document->id,
                'tags' => $request->tags,
                'custom_label' => $request->custom_label,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document signé uploadé avec succès',
                'data' => $document->load('linkedRequirements'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur upload document signé', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document signé',
            ], 500);
        }
    }

    /**
     * Lie un document signé à une ou plusieurs exigences
     */
    public function linkToRequirements(Client $client, ClientComplianceDocument $document, Request $request): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        if ($document->document_type !== 'signed_document') {
            return response()->json(['success' => false, 'message' => 'Seuls les documents signés peuvent être liés à des exigences'], 400);
        }

        $request->validate([
            'requirement_ids' => 'required|array|min:1',
            'requirement_ids.*' => 'exists:compliance_requirements,id',
        ]);

        try {
            // Attache les requirements avec status "pending" (sans détacher les existants)
            $syncData = collect($request->requirement_ids)->mapWithKeys(function ($id) {
                return [$id => ['status' => 'pending']];
            })->toArray();

            $document->linkedRequirements()->syncWithoutDetaching($syncData);

            Log::info("🔗 [COMPLIANCE] Document signé #{$document->id} lié à des exigences", [
                'client_id' => $client->id,
                'requirement_ids' => $request->requirement_ids,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document lié aux exigences avec succès',
                'data' => $document->fresh()->load('linkedRequirements'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur liaison document', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la liaison du document',
            ], 500);
        }
    }

    /**
     * Retire la liaison d'un document signé avec une exigence
     */
    public function unlinkFromRequirement(Client $client, ClientComplianceDocument $document, ComplianceRequirement $requirement): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        try {
            $document->linkedRequirements()->detach($requirement->id);

            Log::info("🔗 [COMPLIANCE] Liaison retirée document #{$document->id} - exigence #{$requirement->id}", [
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Liaison retirée avec succès',
                'data' => $document->fresh()->load('linkedRequirements'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur retrait liaison', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait de la liaison',
            ], 500);
        }
    }

    /**
     * Valide une liaison document-exigence
     */
    public function validateLink(Client $client, ClientComplianceDocument $document, ComplianceRequirement $requirement): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        try {
            $document->linkedRequirements()->updateExistingPivot($requirement->id, [
                'status' => 'validated',
                'validated_at' => now(),
                'validated_by' => auth()->id(),
            ]);

            Log::info("✅ [COMPLIANCE] Liaison validée document #{$document->id} - exigence #{$requirement->id}", [
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Liaison validée avec succès',
                'data' => $document->fresh()->load('linkedRequirements'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur validation liaison', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation de la liaison',
            ], 500);
        }
    }

    /**
     * Rejette une liaison document-exigence
     */
    public function rejectLink(Client $client, ClientComplianceDocument $document, ComplianceRequirement $requirement): JsonResponse
    {
        if ($document->client_id !== $client->id) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        try {
            $document->linkedRequirements()->updateExistingPivot($requirement->id, [
                'status' => 'rejected',
            ]);

            Log::info("❌ [COMPLIANCE] Liaison rejetée document #{$document->id} - exigence #{$requirement->id}", [
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Liaison rejetée',
                'data' => $document->fresh()->load('linkedRequirements'),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [COMPLIANCE] Erreur rejet liaison', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet de la liaison',
            ], 500);
        }
    }

    /**
     * Détermine la catégorie d'un type de document
     */
    private function getCategoryForDocumentType(string $documentType): string
    {
        $identityTypes = ['cni', 'passeport', 'titre_sejour'];
        $bankingTypes = ['rib'];
        $fiscalTypes = ['avis_imposition', 'avis_imposition_n1', 'avis_imposition_n2'];

        if (in_array($documentType, $identityTypes)) {
            return 'identity';
        }
        if (in_array($documentType, $bankingTypes)) {
            return 'banking';
        }
        if (in_array($documentType, $fiscalTypes)) {
            return 'fiscal';
        }

        return 'regulatory';
    }
}
