<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\DefaultsPreview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /b/{key}: a blueprint's public page, for anyone with the link. Only
 * Framework's own, public, and unlisted blueprints have one; blueprints start
 * private. It shows what a project from it gets, without anyone's defaults.
 */
class PublicBlueprintController extends Controller
{
    public function __invoke(Request $request, string $key): View
    {
        $blueprint = Blueprint::where('public_key', $key)->with('owner', 'latestVersion.base.facet', 'latestVersion.facetVersions.facet')->firstOrFail();
        abort_unless($blueprint->isPublic() && $blueprint->latestVersion, 404);

        $languages = $blueprint->latestVersion->compatibility()['languages'] ?? Project::LANGUAGES;
        $language = in_array($request->query('language'), $languages, true) ? $request->query('language')
            : (in_array($blueprint->language, $languages, true) ? $blueprint->language : $languages[0]);

        // As a new account would get it: the blueprint alone, no personal defaults
        $preview = DefaultsPreview::forBlueprint(new User(['project_defaults' => []]), $blueprint, $language, null, []);

        // The preview lists paths flat; add their parent folders and sort, so it reads as a tree
        if ($preview['available'] ?? false) {
            $entries = collect($preview['tree'])->keyBy('path');
            foreach ($entries->keys() as $path) {
                for ($parent = dirname($path); $parent !== '.' && $parent !== ''; $parent = dirname($parent)) {
                    $entries->put($parent, $entries->get($parent) ?? ['path' => $parent, 'type' => 'dir', 'note' => null, 'when' => null, 'later' => $entries[$path]['later']]);
                }
            }
            // Folder by folder, so settings/ai.yml stays under settings/ and before settings.yml
            $preview['tree'] = $entries->sortBy(fn ($entry, $path) => str_replace('/', "\x01", strtolower($path)))->values()->all();
        }

        return view('blueprints.public', [
            'blueprint' => $blueprint,
            'language' => $language,
            'languages' => $languages,
            'preview' => $preview,
            'appUrl' => url("/blueprints/{$blueprint->id}"),
        ]);
    }
}
