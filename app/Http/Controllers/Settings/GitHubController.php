<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\GitHub\Installations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GitHubController extends Controller
{
    public function show(Request $request): Response
    {
        $account = $request->user()->githubAccount;

        return Inertia::render('Settings/GitHub', [
            'available' => filled(config('services.github.client_id')),
            'account' => $account ? ['login' => $account->login, 'connected_at' => $account->created_at->toDateString()] : null,
            'installations' => fn () => $account ? Installations::for($account, fresh: true) : [],
            'install_url' => Installations::installUrl(),
        ]);
    }
}
