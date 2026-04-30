<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// DesTest — modèle de test pour le système de permissions API
Route::middleware('auth:api')->prefix('des-tests')->name('api.des-tests.')->group(function () {
    Route::get('/',                                         [\App\Http\Controllers\Api\DesTestController::class, 'index'])  ->name('index');
    Route::post('/',                                        [\App\Http\Controllers\Api\DesTestController::class, 'store'])  ->name('store');
    Route::delete('/{desTest}',                             [\App\Http\Controllers\Api\DesTestController::class, 'destroy'])->name('destroy');
    Route::post('/{desTest}/publish',                       [\App\Http\Controllers\Api\DesTestController::class, 'publish'])->name('publish');
});


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
