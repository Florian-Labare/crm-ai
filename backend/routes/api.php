<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\AudioRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\QuestionnaireRisqueController;
use App\Http\Controllers\DocumentController;

// Routes publiques d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par authentification Sanctum
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // CRUD Client
    Route::get(   '/clients',        [ClientController::class, 'index']);
    Route::get(   '/clients/{id}',   [ClientController::class, 'show']);
    Route::post(  '/clients',        [ClientController::class, 'store']);
    Route::put(   '/clients/{id}',   [ClientController::class, 'update']);
    Route::delete('/clients/{id}',   [ClientController::class, 'destroy']);

    // Export Client
    Route::get('/clients/{id}/export/pdf',  [ExportController::class, 'exportPdf']);
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

    // Questionnaire de risque
    Route::post('/questionnaire-risque/live', [QuestionnaireRisqueController::class, 'live']);
    Route::get('/questionnaire-risque/client/{clientId}', [QuestionnaireRisqueController::class, 'show']);

    // Debug simple
    Route::get('/ping', fn() => response()->json(['pong' => true]));

    Route::get('/test-error', function() {
        try {
            $client = \App\Models\Client::first();
            return response()->json(['client' => $client]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
});
