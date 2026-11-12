<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AuthorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal data: the author details Framework writes into projects and
 * documents, the `author` block of
 * the synced settings, in the shape of Quarto's author schema. `affiliation`
 * stays a plain string (the first affiliation's name) for framework
 * versions that only read name, email, and affiliation.
 */
class AuthorController extends Controller
{
    public const MAX_AFFILIATIONS = AuthorProfile::MAX_AFFILIATIONS;

    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/PersonalData', ['author' => AuthorProfile::for($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        AuthorProfile::prepare($request);
        $validated = $request->validate(AuthorProfile::rules(), AuthorProfile::messages());
        AuthorProfile::save($request->user(), $validated);

        return back()->with('status', 'author-updated');
    }
}
