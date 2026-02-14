<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Web routes — uses session-based authentication.
 *
 * Filament admin panel، static pages، و download routes اینجا قرار می‌گیرند.
 * API endpoints در routes/api.php هستند.
 */

Route::get('/', function () {
    return redirect('/admin');
});

// File download route (used across the app for File model)
Route::get('/files/{file}/download', \App\Http\Controllers\FileDownloadController::class)
    ->middleware(['auth'])
    ->name('files.download');
