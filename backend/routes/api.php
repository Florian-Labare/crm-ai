<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\AudioRecordController;

Route::middleware(['api'])->group(function () {
    // CRUD Client
    Route::get(   '/clients',        [ClientController::class, 'index']);
    Route::get(   '/clients/{id}',   [ClientController::class, 'show']);
    Route::post(  '/clients',        [ClientController::class, 'store']);
    Route::put(   '/clients/{id}',   [ClientController::class, 'update']);
    Route::delete('/clients/{id}',   [ClientController::class, 'destroy']);

    // Envoi audio et traitement IA
    Route::post('/audio/upload', [AudioController::class, 'upload']);

    Route::get('/recordings', [AudioRecordController::class, 'index']);
    Route::get('/recordings/{id}', [AudioRecordController::class, 'show']);
    Route::delete('/recordings/{id}', [AudioRecordController::class, 'destroy']);

    // Debug simple
    Route::get('/ping', fn() => response()->json(['pong' => true]));
});
