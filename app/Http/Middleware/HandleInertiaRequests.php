<?php

namespace App\Http\Middleware;

use App\Models\McpConnection;
use App\Models\User;
use App\Support\Agents;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // The resolved "user" can be a Project (Sanctum tokenable) when a
        // bearer token accompanies a web request — only real users share
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'handle' => $user->handle,
                    'initials' => $user->initials(),
                    'is_admin' => (bool) $user->is_admin,
                ] : null,
            ],

            'flash' => [
                'status' => $request->session()->get('status'),
                'token' => $request->session()->get('token'),
                'token_language' => $request->session()->get('token_language'),
                'url' => $request->session()->get('url'),
                'added' => $request->session()->get('added'),
            ],

            // Sidebar groups: main (no section), Framework, Agents, and the
            // utility items (You) at the bottom. Packages may append items here.
            // Resolved when the page renders, after the controller (the Agents page may end expired connections)
            'nav' => fn () => array_values(array_filter([
                ['label' => 'Default settings', 'href' => '/settings/language', 'match' => '/settings'],
                ['label' => 'Artifacts', 'href' => '/published', 'match' => ['/published', '/artifacts'], 'section' => 'Your research'],
                ['label' => 'Collections', 'href' => '/collections', 'section' => 'Your research'],
                ['label' => 'Reports', 'href' => '/reports', 'section' => 'Your research'],
                ['label' => 'Blueprints', 'href' => '/blueprints', 'section' => 'Framework'],
                ['label' => 'Project structures', 'href' => '/structures', 'section' => 'Framework'],
                ['label' => 'Package sets', 'href' => '/package-sets', 'section' => 'Framework'],
                ['label' => 'Skills', 'href' => '/skills', 'section' => 'Agents'],
                ['label' => 'Skill sets', 'href' => '/skill-sets', 'section' => 'Agents'],
                $user instanceof User && Agents::enabled() ? ['label' => McpConnection::active()->where('user_id', $user->id)->exists() ? 'Connected agents' : 'Connect your agent', 'href' => '/agents', 'section' => 'Agents'] : null,
                ['label' => 'Account', 'href' => '/account/profile', 'section' => 'You', 'match' => ['/account', '/tokens']],
                $user instanceof User ? ['label' => 'Trash', 'href' => '/trash', 'section' => 'You'] : null,
                $user?->is_admin ? ['label' => 'Server administration', 'href' => '/admin/setup', 'section' => 'You'] : null,
                // The guide is a plain page outside the app, so it opens as a normal link
                ['label' => 'Help & documentation', 'href' => '/docs', 'section' => 'You', 'external' => true],
            ])),

            'githubSignIn' => filled(config('services.github.client_id')),
            // Signing in and up with GitHub, separately from connecting it (FW_GITHUB_LOGIN)
            'githubLogin' => filled(config('services.github.client_id')) && config('framework.github_login'),
            'passwords' => (bool) config('framework.passwords'),
            'quickLogin' => app()->environment('local') && config('framework.quick_login'),
            // Signing out would sign straight back in, so the app hides it
            'singleUser' => SingleUserMode::appliesTo($request),
            'appUrl' => rtrim(config('app.url'), '/'),
            // What setup commands name: the configured client-facing address
            'clientUrl' => config('framework.client_url'),
            'csrf' => $request->session()->token(),
        ];
    }
}
