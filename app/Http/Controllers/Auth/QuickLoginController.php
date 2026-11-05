<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Development only: signs in as a test user or the admin with one click.
 * Anywhere else it answers 404: it needs APP_ENV=local and FW_QUICK_LOGIN.
 */
class QuickLoginController extends Controller
{
    public const USER_EMAIL = 'user@example.com';

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless(app()->environment('local') && config('framework.quick_login'), 404);

        $user = $role === 'admin'
            ? User::where('is_admin', true)->orderBy('id')->firstOrFail()
            : User::firstOrCreate(['email' => self::USER_EMAIL], ['name' => 'Test User'])->forceFill(['email_verified_at' => now()]);
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }
}
