<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The author details Framework writes into documents: the `author` block of
 * the synced settings, in the shape of Quarto's author schema. `affiliation`
 * stays a plain string (the first affiliation's name) for framework
 * versions that only read name, email, and affiliation.
 */
class AuthorController extends Controller
{
    public const MAX_AFFILIATIONS = 5;

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $author = Arr::get($user->globalSettings()->document, 'author', []) ?? [];

        $affiliations = collect($author['affiliations'] ?? [])
            ->map(fn ($a) => Arr::only((array) $a, ['name', 'department', 'city', 'country']) + ['name' => '', 'department' => '', 'city' => '', 'country' => ''])
            ->values()->all();

        // Older settings only have the affiliation string
        if (! $affiliations && filled($author['affiliation'] ?? null)) {
            $affiliations = [['name' => $author['affiliation'], 'department' => '', 'city' => '', 'country' => '']];
        }

        return Inertia::render('Settings/Profile', [
            'author' => [
                'name' => ($author['name'] ?? '') ?: $user->name,
                'email' => ($author['email'] ?? '') ?: $user->email,
                'orcid' => $author['orcid'] ?? '',
                'url' => $author['url'] ?? '',
                'affiliations' => $affiliations,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'orcid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'affiliations' => ['array', 'max:'.self::MAX_AFFILIATIONS],
            'affiliations.*.name' => ['required', 'string', 'max:190'],
            'affiliations.*.department' => ['nullable', 'string', 'max:190'],
            'affiliations.*.city' => ['nullable', 'string', 'max:120'],
            'affiliations.*.country' => ['nullable', 'string', 'max:120'],
        ], [
            'orcid.regex' => 'Write the ORCID iD as 0000-0002-1825-0097.',
            'affiliations.*.name.required' => 'Each affiliation needs a name.',
        ]);

        $affiliations = collect($validated['affiliations'] ?? [])
            ->map(fn ($a) => array_filter([
                'name' => $a['name'],
                'department' => $a['department'] ?? null,
                'city' => $a['city'] ?? null,
                'country' => $a['country'] ?? null,
            ], 'filled'))
            ->values()->all();

        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        Arr::set($doc, 'author', [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? '',
            'affiliation' => $affiliations[0]['name'] ?? '',
            'orcid' => $validated['orcid'] ?? '',
            'url' => $validated['url'] ?? '',
            'affiliations' => $affiliations,
        ]);

        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        return back()->with('status', 'author-updated');
    }
}
