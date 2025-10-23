<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::post('/email-notifications', [\App\Http\Controllers\MsgEmailNotification::class, 'handleIncoming']);
Route::post('/email-draft-notifications', [\App\Http\Controllers\MsgEmailNotification::class, 'handleDraft']);

// Routes pour l'analyse des états
Route::prefix('states')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'index']);
    Route::get('/statistics', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'statistics']);
    Route::get('/{model}', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'show']);
    Route::get('/{model}/states', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'states']);
    Route::get('/{model}/transitions', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'transitions']);
    Route::get('/{model}/mermaid-json', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'mermaidJson']);
    Route::post('/{model}/generate-docs', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'generateDocs']);
});