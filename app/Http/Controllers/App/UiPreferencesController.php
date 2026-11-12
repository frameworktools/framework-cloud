<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /preferences/ui: remember a small interface choice for the person,
 * such as a collapsed section. Only known keys, and only true or false.
 */
class UiPreferencesController extends Controller
{
    /** Sections that remember whether they're collapsed */
    public const KEYS = ['dashboard_quick_commands', 'dashboard_publish', 'dashboard_activity', 'dashboard_projects', 'project_publish'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', self::KEYS)],
            'collapsed' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $preferences = $user->ui_preferences ?? [];
        $preferences['collapsed'][$data['key']] = (bool) $data['collapsed'];
        $user->forceFill(['ui_preferences' => $preferences])->save();

        return response()->noContent();
    }
}
