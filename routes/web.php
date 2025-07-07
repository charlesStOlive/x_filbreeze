<?php


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/download-export/{filename}', function (string $filename) {
    $path = storage_path('app/private/exports/' . $filename);

    abort_unless(file_exists($path), 404);

    return response()->download($path, request('display') ?? $filename)->deleteFileAfterSend(false);
})->middleware('signed')->name('exports.download');