<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::post('/email-notifications', [\App\Http\Controllers\MsgEmailNotification::class, 'handleIncoming']);
Route::post('/email-draft-notifications', [\App\Http\Controllers\MsgEmailNotification::class, 'handleDraft']);