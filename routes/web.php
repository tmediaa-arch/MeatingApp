<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\MobileOtpAuthController;
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

// ورود با موبایل و کد یک‌بارمصرف (OTP) از طریق کاوه‌نگار
Route::middleware('guest')->group(function () {
    Route::get('/auth/mobile', [MobileOtpAuthController::class, 'showLogin'])->name('auth.mobile.show');
    Route::post('/auth/mobile', [MobileOtpAuthController::class, 'requestOtp'])->name('auth.mobile.request');
    Route::get('/auth/otp', [MobileOtpAuthController::class, 'showOtp'])->name('auth.otp.show');
    Route::post('/auth/otp', [MobileOtpAuthController::class, 'verifyOtp'])->name('auth.otp.verify');
    Route::post('/auth/otp/resend', [MobileOtpAuthController::class, 'resendOtp'])->name('auth.otp.resend');
});

// پذیرش لینک دعوت — ساخت/یافتن حساب و ارسال کد ورود
Route::get('/invite/{token}', [InviteController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('invite.accept');
