<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordController;
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

// Password sign-in and reset, when FW_PASSWORDS is on
Route::middleware(['guest', 'passwords'])->group(function () {
    Route::post('login/password', [PasswordController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('password.login');
    Route::get('forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware(['auth', 'passwords'])->group(function () {
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::delete('settings/password', [PasswordController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('password.destroy');
});

// GitHub sign-in (guests) and connect (signed in) share one OAuth callback
Route::get('auth/github/redirect', [GitHubController::class, 'redirect'])->middleware('throttle:12,1')->name('github.redirect');
Route::get('auth/github/callback', [GitHubController::class, 'callback'])->middleware('throttle:12,1')->name('github.callback');

// Usable while logged in or out (clicking a second time shouldn't 403).
// GET only shows a confirm page, so mail scanners can't use the link up.
Route::get('auth/magic/{token}', [MagicLinkController::class, 'show'])
    ->middleware('throttle:12,1')
    ->name('magic-link.consume');
Route::post('auth/magic/{token}', [MagicLinkController::class, 'consume'])
    ->middleware('throttle:12,1')
    ->name('magic-link.confirm');

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
