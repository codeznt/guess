<?php

// Remove references to controllers that don't exist
// use App\Http\Controllers\Auth\AuthenticatedSessionController;
// use App\Http\Controllers\Auth\ConfirmablePasswordController;
// use App\Http\Controllers\Auth\EmailVerificationNotificationController;
// use App\Http\Controllers\Auth\EmailVerificationPromptController;
// use App\Http\Controllers\Auth\NewPasswordController;
// use App\Http\Controllers\Auth\PasswordResetLinkController;
// use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TelegramAuthController;
// use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    // Telegram login page
    Route::get('login', function () {
        return Inertia::render('auth/TelegramLogin');
    })->name('login');
    
    // Telegram WebApp authentication
    Route::post('auth/telegram', [TelegramAuthController::class, 'authenticate'])
        ->name('auth.telegram');
});

Route::middleware('auth')->group(function () {
    // Use a simple route closure for logout instead of missing controller
    Route::post('logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});
