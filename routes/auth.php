<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\QuickLoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', [PasswordController::class, 'loginPage'])->name('login');
    Route::get('register', fn () => Inertia::render('Auth/Register'))->name('register');

    // Passwordless: emailed single-use sign-in links
    Route::get('auth/magic-link/sent', [MagicLinkController::class, 'sent'])->name('magic-link.sent');
    Route::post('auth/magic-code', [MagicLinkController::class, 'code'])
        ->middleware('throttle:10,1')
        ->name('magic-link.code');
    Route::post('auth/magic-link', [MagicLinkController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('magic-link.send');

    // Development only: one-click sign-in as a test user or the admin. The
    // controller answers 404 unless quick login is on.
    Route::post('login/quick/{role}', QuickLoginController::class)->whereIn('role', ['user', 'admin'])->name('login.quick');

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
    Route::put('account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::delete('account/password', [PasswordController::class, 'destroy'])
        ->middleware('throttle:6,1')
        ->name('password.destroy');
    Route::put('account/sign-in-links', [PasswordController::class, 'links'])
        ->middleware('throttle:12,1')
        ->name('sign-in-links.update');
});

// Connecting GitHub (signed in) goes through the GitHub App
Route::get('auth/github/redirect', [GitHubController::class, 'redirect'])->middleware(['auth', 'throttle:12,1'])->name('github.redirect');
Route::get('auth/github/callback', [GitHubController::class, 'callback'])->middleware(['auth', 'throttle:12,1'])->name('github.callback');
// Signing in and up with GitHub goes through the login-only OAuth app
Route::get('auth/github/login', [GitHubController::class, 'loginRedirect'])->middleware(['guest', 'throttle:12,1'])->name('github.login');
Route::get('auth/github/login/callback', [GitHubController::class, 'loginCallback'])->middleware(['guest', 'throttle:12,1'])->name('github.login.callback');

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
    // This browser only: logout() would change the remember token and sign out every other device
    Auth::guard('web')->logoutCurrentDevice();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // The home page isn't an Inertia page, so the browser loads it in full
    // rather than showing it in Inertia's error modal
    return Inertia::location(url('/'));
})->name('logout');
