<?php

namespace App\Http\Middleware;

use App\Models\User;
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
                'url' => $request->session()->get('url'),
            ],

            // Packages may append their own items via this shared prop.
            'nav' => array_values(array_filter([
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Projects', 'href' => '/projects'],
                ['label' => 'Blueprints', 'href' => '/blueprints'],
                ['label' => 'Facets', 'href' => '/facets'],
                ['label' => 'Skills', 'href' => '/skills'],
                ['label' => 'API tokens', 'href' => '/tokens'],
            ])),

            'appUrl' => rtrim(config('app.url'), '/'),
            'csrf' => $request->session()->token(),
        ];
    }
}
