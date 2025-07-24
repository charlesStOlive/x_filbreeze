<?php


use App\Livewire\EmailTemplateTester;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

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

Route::get('/tt/email/{key}/{modelId}', EmailTemplateTester::class)
    ->name('template_test_email');

Route::middleware(['web', 'auth'])->get('/tt/pdf/{modelclass}/{templateKey}/{modelId}', \App\Livewire\PdfTemplateTester::class)
    ->name('template_test_pdf');