<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private const RECENT_PROJECTS = 6;

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
            ],

            // Sidebar groups: main (no section), Framework, and Settings at
            // the bottom. Packages may append their own items here.
            'nav' => array_values(array_filter([
                ['label' => 'Published', 'href' => '/published'],
                ['label' => 'Blueprints', 'href' => '/blueprints', 'section' => 'Framework'],
                ['label' => 'Structures', 'href' => '/structures', 'section' => 'Framework'],
                ['label' => 'Package sets', 'href' => '/package-sets', 'section' => 'Framework'],
                ['label' => 'Skills', 'href' => '/skills', 'section' => 'Framework', 'match' => ['/skills', '/skill-sets']],
                ['label' => 'Your settings', 'href' => '/settings/profile', 'section' => 'Settings', 'match' => '/settings'],
                ['label' => 'API tokens', 'href' => '/tokens', 'section' => 'Settings'],
                $user?->is_admin ? ['label' => 'Server setup', 'href' => '/admin/setup', 'section' => 'Settings'] : null,
            ])),

            // The newest projects, for switching between them from the sidebar
            'recentProjects' => fn () => $user ? [
                'items' => $user->projects()->latest()->limit(self::RECENT_PROJECTS)->get(['id', 'name', 'slug']),
                'total' => $user->projects()->count(),
            ] : null,

            'githubSignIn' => filled(config('services.github.client_id')),
            'passwords' => (bool) config('framework.passwords'),
            // Signing out would sign straight back in, so the app hides it
            'singleUser' => SingleUserMode::appliesTo($request),
            'appUrl' => rtrim(config('app.url'), '/'),
            'csrf' => $request->session()->token(),
        ];
    }
}
