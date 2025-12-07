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

// Routes publiques d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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

    // Export Client
    Route::get('/clients/{id}/export/pdf', [ExportController::class, 'exportPdf']);
    Route::get('/clients/{id}/export/word', [ExportController::class, 'exportWord']);
    Route::get('/clients/{id}/questionnaires/export/pdf', [ExportController::class, 'exportQuestionnairePdf']);

    // Gestion des documents réglementaires
    Route::get('/document-templates', [DocumentController::class, 'listTemplates']);
    Route::get('/clients/{clientId}/documents', [DocumentController::class, 'listClientDocuments']);
    Route::post('/clients/{clientId}/documents/generate', [DocumentController::class, 'generateDocument']);
    Route::get('/documents/{documentId}/download', [DocumentController::class, 'downloadDocument']);
    Route::post('/documents/{documentId}/send-email', [DocumentController::class, 'sendDocumentByEmail']);
    Route::delete('/documents/{documentId}', [DocumentController::class, 'deleteDocument']);

    // Envoi audio et traitement IA
    Route::post('/audio/upload', [AudioController::class, 'upload']);
    Route::get('/audio/status/{id}', [AudioController::class, 'status']);

    Route::get('/recordings', [AudioRecordController::class, 'index']);
    Route::get('/recordings/{id}', [AudioRecordController::class, 'show']);
    Route::delete('/recordings/{id}', [AudioRecordController::class, 'destroy']);

    // Enregistrements longs (jusqu'à 2h) avec chunks
    Route::post('/recordings/chunk', [RecordingController::class, 'storeChunk']);
    Route::post('/recordings/{sessionId}/finalize', [RecordingController::class, 'finalize']);

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
});
