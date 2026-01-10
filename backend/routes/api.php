<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\AudioRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DerController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\QuestionnaireRisqueController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SpeakerCorrectionController;
use App\Http\Controllers\PendingChangesController;
use App\Http\Controllers\ImportMappingController;
use App\Http\Controllers\ImportSessionController;
use App\Http\Controllers\DatabaseConnectionController;

// Routes publiques d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Health check endpoints (publics pour monitoring externe) - avec rate limiting
Route::prefix('health')->middleware('throttle:health-check')->group(function () {
    Route::get('/audio', [HealthController::class, 'audioSystem']);
    Route::get('/pyannote', [HealthController::class, 'pyannote']);
});

// Routes protégées par authentification Sanctum
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // DER - Document d'Entrée en Relation
    Route::get('/der/create', [DerController::class, 'create']);
    Route::post('/der', [DerController::class, 'store']);

    // CRUD Client
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    // Gestion des relations client
    // Revenus
    Route::post('/clients/{client}/revenus', [ClientController::class, 'storeRevenu']);
    Route::put('/clients/{client}/revenus/{revenu}', [ClientController::class, 'updateRevenu']);
    Route::delete('/clients/{client}/revenus/{revenu}', [ClientController::class, 'deleteRevenu']);

    // Passifs
    Route::post('/clients/{client}/passifs', [ClientController::class, 'storePassif']);
    Route::put('/clients/{client}/passifs/{passif}', [ClientController::class, 'updatePassif']);
    Route::delete('/clients/{client}/passifs/{passif}', [ClientController::class, 'deletePassif']);

    // Actifs financiers
    Route::post('/clients/{client}/actifs-financiers', [ClientController::class, 'storeActifFinancier']);
    Route::put('/clients/{client}/actifs-financiers/{actifFinancier}', [ClientController::class, 'updateActifFinancier']);
    Route::delete('/clients/{client}/actifs-financiers/{actifFinancier}', [ClientController::class, 'deleteActifFinancier']);

    // Biens immobiliers
    Route::post('/clients/{client}/biens-immobiliers', [ClientController::class, 'storeBienImmobilier']);
    Route::put('/clients/{client}/biens-immobiliers/{bienImmobilier}', [ClientController::class, 'updateBienImmobilier']);
    Route::delete('/clients/{client}/biens-immobiliers/{bienImmobilier}', [ClientController::class, 'deleteBienImmobilier']);

    // Autres épargnes
    Route::post('/clients/{client}/autres-epargnes', [ClientController::class, 'storeAutreEpargne']);
    Route::put('/clients/{client}/autres-epargnes/{autreEpargne}', [ClientController::class, 'updateAutreEpargne']);
    Route::delete('/clients/{client}/autres-epargnes/{autreEpargne}', [ClientController::class, 'deleteAutreEpargne']);

    // Conjoint
    Route::post('/clients/{client}/conjoint', [ClientController::class, 'storeConjoint']);
    Route::put('/clients/{client}/conjoint', [ClientController::class, 'updateConjoint']);
    Route::delete('/clients/{client}/conjoint', [ClientController::class, 'deleteConjoint']);

    // Enfants
    Route::post('/clients/{client}/enfants', [ClientController::class, 'storeEnfant']);
    Route::put('/clients/{client}/enfants/{enfant}', [ClientController::class, 'updateEnfant']);
    Route::delete('/clients/{client}/enfants/{enfant}', [ClientController::class, 'deleteEnfant']);

    // Santé / Souhait
    Route::post('/clients/{client}/sante-souhait', [ClientController::class, 'storeSanteSouhait']);
    Route::put('/clients/{client}/sante-souhait', [ClientController::class, 'updateSanteSouhait']);
    Route::delete('/clients/{client}/sante-souhait', [ClientController::class, 'deleteSanteSouhait']);

    // BAE Prévoyance
    Route::post('/clients/{client}/bae-prevoyance', [ClientController::class, 'storeBaePrevoyance']);
    Route::put('/clients/{client}/bae-prevoyance', [ClientController::class, 'updateBaePrevoyance']);
    Route::delete('/clients/{client}/bae-prevoyance', [ClientController::class, 'deleteBaePrevoyance']);

    // BAE Retraite
    Route::post('/clients/{client}/bae-retraite', [ClientController::class, 'storeBaeRetraite']);
    Route::put('/clients/{client}/bae-retraite', [ClientController::class, 'updateBaeRetraite']);
    Route::delete('/clients/{client}/bae-retraite', [ClientController::class, 'deleteBaeRetraite']);

    // BAE Épargne
    Route::post('/clients/{client}/bae-epargne', [ClientController::class, 'storeBaeEpargne']);
    Route::put('/clients/{client}/bae-epargne', [ClientController::class, 'updateBaeEpargne']);
    Route::delete('/clients/{client}/bae-epargne', [ClientController::class, 'deleteBaeEpargne']);

    // Export Client
    Route::get('/clients/{id}/export/pdf', [ExportController::class, 'exportPdf']);
    Route::get('/clients/{id}/export/word', [ExportController::class, 'exportWord']);
    Route::get('/clients/{id}/questionnaires/export/pdf', [ExportController::class, 'exportQuestionnairePdf']);

    // ============================================
    // 🔒 PENDING CHANGES - Système de merge avec validation
    // ============================================
    Route::prefix('pending-changes')->group(function () {
        // Liste tous les pending changes de l'utilisateur
        Route::get('/', [PendingChangesController::class, 'index']);

        // Détail d'un pending change
        Route::get('/{pendingChange}', [PendingChangesController::class, 'show']);

        // Appliquer les changements sélectionnés
        Route::post('/{pendingChange}/apply', [PendingChangesController::class, 'apply']);

        // Accepter tous les changements
        Route::post('/{pendingChange}/accept-all', [PendingChangesController::class, 'acceptAll']);

        // Rejeter tous les changements
        Route::post('/{pendingChange}/reject-all', [PendingChangesController::class, 'rejectAll']);

        // Appliquer automatiquement les changements sans conflit
        Route::post('/{pendingChange}/auto-apply-safe', [PendingChangesController::class, 'autoApplySafe']);
    });

    // Pending changes par client
    Route::get('/clients/{client}/pending-changes', [PendingChangesController::class, 'forClient']);
    Route::get('/clients/{client}/pending-changes/count', [PendingChangesController::class, 'countForClient']);

    // Gestion des documents réglementaires
    Route::get('/document-templates', [DocumentController::class, 'listTemplates']);
    Route::get('/clients/{clientId}/documents', [DocumentController::class, 'listClientDocuments']);
    Route::post('/clients/{clientId}/documents/generate', [DocumentController::class, 'generateDocument']);
    Route::get('/clients/{clientId}/document-templates/{templateId}/form', [DocumentController::class, 'showForm']);
    Route::post('/clients/{clientId}/document-templates/{templateId}/form', [DocumentController::class, 'saveForm']);
    Route::get('/documents/{documentId}/download', [DocumentController::class, 'downloadDocument']);
    Route::post('/documents/{documentId}/send-email', [DocumentController::class, 'sendDocumentByEmail']);
    Route::delete('/documents/{documentId}', [DocumentController::class, 'deleteDocument']);

    // Envoi audio et traitement IA - avec rate limiting
    Route::post('/audio/upload', [AudioController::class, 'upload'])
        ->middleware('throttle:audio-upload');
    Route::get('/audio/status/{id}', [AudioController::class, 'status']);

    Route::get('/recordings', [AudioRecordController::class, 'index']);
    Route::get('/recordings/{id}', [AudioRecordController::class, 'show']);
    Route::delete('/recordings/{id}', [AudioRecordController::class, 'destroy']);

    // Enregistrements longs (jusqu'à 2h) avec chunks - avec rate limiting
    Route::post('/recordings/chunk', [RecordingController::class, 'storeChunk'])
        ->middleware('throttle:audio-chunk');
    Route::post('/recordings/{sessionId}/finalize', [RecordingController::class, 'finalize'])
        ->middleware('throttle:audio-finalize');

    // Correction des speakers (diarisation) - avec rate limiting
    Route::prefix('audio-records/{audioRecord}/speakers')
        ->middleware('throttle:speaker-correction')
        ->group(function () {
            Route::get('/', [SpeakerCorrectionController::class, 'show']);
            Route::post('/correct', [SpeakerCorrectionController::class, 'correct']);
            Route::post('/correct-batch', [SpeakerCorrectionController::class, 'correctBatch']);
            Route::post('/reset', [SpeakerCorrectionController::class, 'reset']);
        });
    Route::get('/audio-records/needs-review', [SpeakerCorrectionController::class, 'needsReview']);

    // Monitoring de la diarisation (admin/stats)
    Route::get('/diarization/stats', [HealthController::class, 'diarizationStats']);

    // Questionnaire de risque
    Route::post('/questionnaire-risque/live', [QuestionnaireRisqueController::class, 'live']);
    Route::get('/questionnaire-risque/client/{clientId}', [QuestionnaireRisqueController::class, 'show']);

    // Team Management
    Route::prefix('teams')->group(function () {
        Route::get('/', [\App\Http\Controllers\TeamController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\TeamController::class, 'store']);
        Route::get('/{team}', [\App\Http\Controllers\TeamController::class, 'show']);
        Route::put('/{team}', [\App\Http\Controllers\TeamController::class, 'update']);
        Route::delete('/{team}', [\App\Http\Controllers\TeamController::class, 'destroy']);

        // Team members management
        Route::get('/{team}/members', [\App\Http\Controllers\TeamController::class, 'members']);
        Route::post('/{team}/members', [\App\Http\Controllers\TeamController::class, 'inviteMember']);
        Route::put('/{team}/members/{user}', [\App\Http\Controllers\TeamController::class, 'updateMemberRole']);
        Route::delete('/{team}/members/{user}', [\App\Http\Controllers\TeamController::class, 'removeMember']);
    });

    // Debug simple
    Route::get('/ping', fn() => response()->json(['pong' => true]));

    Route::get('/test-error', function () {
        try {
            $client = \App\Models\Client::first();
            return response()->json(['client' => $client]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // ============================================
    // 📥 IMPORT - Import de données clients
    // ============================================
    Route::prefix('import')->group(function () {
        // Mappings de colonnes
        Route::get('/mappings', [ImportMappingController::class, 'index']);
        Route::post('/mappings', [ImportMappingController::class, 'store']);
        Route::get('/mappings/fields', [ImportMappingController::class, 'availableFields']);
        Route::get('/mappings/{mapping}', [ImportMappingController::class, 'show']);
        Route::put('/mappings/{mapping}', [ImportMappingController::class, 'update']);
        Route::delete('/mappings/{mapping}', [ImportMappingController::class, 'destroy']);

        // Sessions d'import
        Route::get('/sessions', [ImportSessionController::class, 'index']);
        Route::post('/upload', [ImportSessionController::class, 'upload']);
        Route::get('/sessions/{session}', [ImportSessionController::class, 'show']);
        Route::post('/sessions/{session}/mapping', [ImportSessionController::class, 'setMapping']);
        Route::get('/sessions/{session}/suggestions', [ImportSessionController::class, 'suggestMappings']);
        Route::post('/sessions/{session}/start', [ImportSessionController::class, 'start']);
        Route::get('/sessions/{session}/rows', [ImportSessionController::class, 'rows']);
        Route::post('/sessions/{session}/rows/{row}/resolve', [ImportSessionController::class, 'resolveRow']);
        Route::post('/sessions/{session}/import-valid', [ImportSessionController::class, 'importValid']);
        Route::delete('/sessions/{session}', [ImportSessionController::class, 'destroy']);

        // RGPD Compliance endpoints
        Route::get('/legal-bases', [ImportSessionController::class, 'legalBases']);
        Route::post('/sessions/{session}/consent', [ImportSessionController::class, 'recordConsent']);
        Route::get('/sessions/{session}/audit-trail', [ImportSessionController::class, 'auditTrail']);
        Route::get('/sessions/{session}/imported-clients', [ImportSessionController::class, 'importedClients']);

        // Connexions base de données externes
        Route::get('/database-connections/drivers', [DatabaseConnectionController::class, 'drivers']);
        Route::post('/database-connections/test', [DatabaseConnectionController::class, 'test']);
        Route::get('/database-connections', [DatabaseConnectionController::class, 'index']);
        Route::post('/database-connections', [DatabaseConnectionController::class, 'store']);
        Route::get('/database-connections/{databaseConnection}', [DatabaseConnectionController::class, 'show']);
        Route::put('/database-connections/{databaseConnection}', [DatabaseConnectionController::class, 'update']);
        Route::delete('/database-connections/{databaseConnection}', [DatabaseConnectionController::class, 'destroy']);
        Route::post('/database-connections/{databaseConnection}/test', [DatabaseConnectionController::class, 'test']);
        Route::get('/database-connections/{databaseConnection}/tables', [DatabaseConnectionController::class, 'tables']);
        Route::get('/database-connections/{databaseConnection}/tables/{table}/columns', [DatabaseConnectionController::class, 'columns']);
        Route::post('/database-connections/{databaseConnection}/preview', [DatabaseConnectionController::class, 'preview']);
        Route::post('/database-connections/{databaseConnection}/import', [DatabaseConnectionController::class, 'createImportSession']);
    });
});
