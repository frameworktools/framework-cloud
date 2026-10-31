<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private const PROJECT_LIST = 50;

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

            // Sidebar groups: main (no section), Framework, Agents, and the
            // utility items (You) at the bottom. Packages may append items here.
            'nav' => array_values(array_filter([
                ['label' => 'Project defaults', 'href' => '/settings/basics', 'match' => '/settings'],
                ['label' => 'Published documents', 'href' => '/published'],
                ['label' => 'Blueprints', 'href' => '/blueprints', 'section' => 'Framework'],
                ['label' => 'Project structures', 'href' => '/structures', 'section' => 'Framework'],
                ['label' => 'Package sets', 'href' => '/package-sets', 'section' => 'Framework'],
                ['label' => 'Skills', 'href' => '/skills', 'section' => 'Agents'],
                ['label' => 'Skill sets', 'href' => '/skill-sets', 'section' => 'Agents'],
                ['label' => 'Account', 'href' => '/account/profile', 'section' => 'You', 'match' => ['/account', '/tokens']],
                $user?->is_admin ? ['label' => 'Server administration', 'href' => '/admin/setup', 'section' => 'You'] : null,
                // The guide is a plain page outside the app, so it opens as a normal link
                ['label' => 'Help & documentation', 'href' => '/docs', 'section' => 'You', 'external' => true],
            ])),

            // The newest projects, for switching between them from the sidebar
            'projectList' => fn () => $user ? [
                'items' => $user->projects()->latest()->limit(self::PROJECT_LIST)->get(['id', 'name', 'slug']),
                'total' => $user->projects()->count(),
            ] : null,

            'githubSignIn' => filled(config('services.github.client_id')),
            'passwords' => (bool) config('framework.passwords'),
            'quickLogin' => app()->environment('local') && config('framework.quick_login'),
            // Signing out would sign straight back in, so the app hides it
            'singleUser' => SingleUserMode::appliesTo($request),
            'appUrl' => rtrim(config('app.url'), '/'),
            'csrf' => $request->session()->token(),
        ];
    }
}
