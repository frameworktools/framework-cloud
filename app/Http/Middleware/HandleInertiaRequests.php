<?php

namespace App\Http\Middleware;

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
        $user = $user instanceof \App\Models\User ? $user : null;

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
                ['label' => 'API tokens', 'href' => '/tokens'],
                $user?->is_admin
                    ? ['label' => 'Blueprints', 'href' => '/admin/blueprints', 'section' => 'Admin']
                    : null,
            ])),

            'appUrl' => rtrim(config('app.url'), '/'),
            'csrf' => $request->session()->token(),
        ];
    }
}
