<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::get('register', fn () => Inertia::render('Auth/Register'))->name('register');

    // Passwordless: emailed single-use sign-in links
    Route::post('auth/magic-link', [MagicLinkController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('magic-link.send');

    // Passkey sign-in (GET passkeys/authentication-options + POST passkeys/authenticate)
    Route::passkeys();
});

// Consumable while logged in or out (clicking a second time shouldn't 403)
Route::get('auth/magic/{token}', [MagicLinkController::class, 'consume'])
    ->middleware('throttle:12,1')
    ->name('magic-link.consume');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', fn () => Inertia::render('Auth/VerifyEmail'))
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('verify-email/send', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::post('logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->name('logout');
