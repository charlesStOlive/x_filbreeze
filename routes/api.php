<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


// Microsoft Graph routes are now handled by the MsGraphFilament plugin

// Routes pour l'analyse des états
Route::prefix('states')->name('api.states.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'index'])->name('index');
    Route::get('/statistics', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'statistics'])->name('statistics');
    Route::get('/{model}', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'show'])->name('show');
    Route::get('/{model}/states', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'states'])->name('states');
    Route::get('/{model}/transitions', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'transitions'])->name('transitions');
    Route::get('/{model}/mermaid-json', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'mermaidJson'])->name('mermaid-json');
    Route::get('/{model}/{id}/mermaid-json-from-trait', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'mermaidJsonFromTrait'])->name('mermaid-json-from-trait');
    Route::post('/{model}/generate-docs', [\App\Http\Controllers\Api\StatesAnalysisController::class, 'generateDocs'])->name('generate-docs');
});
