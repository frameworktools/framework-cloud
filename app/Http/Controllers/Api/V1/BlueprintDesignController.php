<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\User;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\Library;
use App\Support\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Designing blueprints over the API, as the web builder does: the parts there are to choose
 * from, one blueprint's choices, a preview of the files a set of choices makes, and saving.
 * Only the account's own blueprints change; a built-in one is copied by creating a new blueprint
 * from its choices.
 */
class BlueprintDesignController extends Controller
{
    /** GET /blueprints/options: structures, tools, package sets, skill sets, and skills to choose from. */
    public function options(Request $request): JsonResponse
    {
        $catalog = Catalog::for($this->user($request));

        return response()->json(collect($catalog)->map(fn ($items) => collect($items)->map(fn ($item) => collect($item)->except(['id'])->all())->values())->all());
    }

    public function show(Request $request, string $blueprint): JsonResponse
    {
        $user = $this->user($request);
        $blueprint = $this->find($user, $blueprint);

        return response()->json($this->detail($blueprint, $user));
    }

    /** POST /blueprints/preview: the files and folders the choices would make, for one language and workflow. Saves nothing. */
    public function preview(Request $request): JsonResponse
    {
        $this->user($request);

        return BlueprintInput::preview($request);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        // New blueprints start private
        $request->mergeIfMissing(['visibility' => 'private']);
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = BlueprintInput::validated($request);

        $blueprint = DB::transaction(function () use ($user, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint = Blueprint::create($meta + [
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id),
                'is_system' => false,
            ]);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);

            return $blueprint;
        });

        return response()->json($this->detail($blueprint->refresh(), $user), 201);
    }

    /** PUT /blueprints/{blueprint}: a new version of one of the account's own blueprints, from the full choices. */
    public function update(Request $request, string $blueprint): JsonResponse
    {
        $user = $this->user($request);
        $blueprint = $this->find($user, $blueprint);
        abort_unless($blueprint->user_id === $user->id, 403, 'Only your own blueprints change here. Create a new blueprint from this one\'s choices to make your own.');
        $request->mergeIfMissing(['visibility' => $blueprint->visibility]);
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = BlueprintInput::validated($request);

        DB::transaction(function () use ($blueprint, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint->update($meta);
            $compatibility += $blueprint->latestVersion?->only(Blueprint::COMPATIBILITY) ?? [];
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);
        });

        return response()->json($this->detail($blueprint->refresh(), $user));
    }

    /** @return array<string, mixed> */
    private function detail(Blueprint $blueprint, User $user): array
    {
        $blueprint->load(Library::BLUEPRINT_WITH);

        return [
            'key' => $blueprint->handle,
            'name' => $blueprint->name,
            'description' => $blueprint->description,
            'visibility' => $blueprint->visibility,
            'version' => $blueprint->latestVersion?->version,
            'editable' => $blueprint->user_id === $user->id,
            'url' => route('blueprints.show', $blueprint),
            // Anyone can open it; null while the blueprint is private
            'public_url' => $blueprint->publicUrl(),
            // Short link and QR code (SVG, PNG) for slides; null while private
            'share' => Share::for($blueprint),
            'choices' => $blueprint->latestVersion ? Catalog::choices($blueprint->latestVersion, $user) : null,
        ];
    }

    /** A blueprint the account can see, by the key blueprints_list shows: name, framework/name, or @owner/name. */
    private function find(User $user, string $handle): Blueprint
    {
        $handle = ltrim($handle, '/');
        $matches = Blueprint::visibleTo($user)->with('owner')->where('key', basename($handle))->get()
            ->filter(fn (Blueprint $b) => $b->handle === $handle || ($b->user_id === $user->id && $b->key === $handle));

        return $matches->sortByDesc(fn (Blueprint $b) => $b->user_id === $user->id)->first() ?? abort(404, 'No blueprint has that key.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'This needs a user token.');

        return $user;
    }
}
