<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Skills as SKILL.md text, for scripts and agents. GET /skills lists them (PiecesController);
 * this reads one, creates one, and saves a new version. Only the account's own skills change
 * here: a built-in skill (framework/...) or someone else's is copied by creating a new skill
 * from its text. Companion files and bundles go through skill sync (SkillSyncController).
 */
class SkillsController extends Controller
{
    public function show(Request $request, string $skill): JsonResponse
    {
        $skill = $this->find($request, $skill);
        $language = $this->language($request) ?? ($skill->language[0] ?? 'r');

        return response()->json($this->detail($skill, $language, $this->user($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $this->validated($request);
        $meta = Skill::frontmatter($data['content'], 'content');

        $existing = Skill::withTrashed()->where('user_id', $user->id)->where('key', $meta['key'])->first();
        if ($existing) {
            throw ValidationException::withMessages(['content' => $existing->trashed()
                ? "A deleted skill is named {$meta['key']}. The user can restore it from the Trash, or choose another name."
                : "You already have a skill named {$meta['key']}. Update it instead."]);
        }

        $skill = Skill::create([
            'user_id' => $user->id, 'key' => $meta['key'], 'name' => $meta['title'],
            'description' => $meta['description'], 'summary' => $meta['summary'],
            'language' => $data['language'] ? [$data['language']] : null,
            'visibility' => $data['visibility'] ?? 'private',
        ]);
        $skill->publishVersion(['content' => $data['content'], 'files' => [], 'notes' => $data['notes'] ?? null]);

        return response()->json($this->detail($skill->refresh(), $data['language'] ?? 'r', $user), 201);
    }

    /** A new version of one of the account's own skills. For a language variant, pass language. */
    public function update(Request $request, string $skill): JsonResponse
    {
        $user = $this->user($request);
        $skill = $this->find($request, $skill);
        abort_unless($skill->user_id === $user->id, 403, 'Only your own skills change here. Create a new skill from this one\'s text to make your own copy.');
        $data = $this->validated($request) + $request->validate(['expected_version' => ['sometimes', 'integer', 'min:1']]);
        $variant = $data['language'] ?? ($skill->language[0] ?? 'r');
        $meta = Skill::frontmatter($data['content'], 'content');
        abort_unless($meta['key'] === $skill->key, 422, "This skill is named {$skill->key}. Keep name: {$skill->key} in the frontmatter, or create a new skill.");

        DB::transaction(function () use ($skill, $data, $meta, $variant) {
            $skill = Skill::whereKey($skill->id)->lockForUpdate()->firstOrFail();
            $latest = $skill->latestVersion;
            if (isset($data['expected_version']) && $latest->version !== $data['expected_version']) {
                throw ValidationException::withMessages(['expected_version' => "The skill is at version {$latest->version}. Read it again before saving."]);
            }
            // As the web editor saves: R is the shared text, and other languages are variants of it
            $variants = $latest->variants;
            $content = $data['content'];
            if ($variant !== 'r') {
                $variants = $variants ?? [];
                $variants[$variant] = $content;
                $content = $latest->content;
            }
            $skill->update(['name' => $meta['title'], 'description' => $meta['description'], 'summary' => $meta['summary']]
                + (isset($data['visibility']) ? ['visibility' => $data['visibility']] : []));
            $skill->publishVersion(['content' => $content, 'variants' => $variants, 'files' => $latest->files ?? [],
                'bundle_files' => $latest->bundle_files, 'base_version_id' => $latest->id, 'notes' => $data['notes'] ?? null]);
        });

        return response()->json($this->detail($skill->refresh(), $variant, $user));
    }

    /** Move one of the account's own skills to the Trash, where the user can restore it. */
    public function destroy(Request $request, string $skill): JsonResponse
    {
        $user = $this->user($request);
        $skill = $this->find($request, $skill);
        abort_unless($skill->user_id === $user->id, 403, 'Only your own skills can be deleted.');
        $skill->delete();

        return response()->json(['deleted' => $skill->handle, 'restore' => route('trash')]);
    }

    /** @return array<string, mixed> */
    private function detail(Skill $skill, string $language, User $user): array
    {
        $skill->load('latestVersion', 'owner');

        return [
            'key' => $skill->handle,
            'name' => $skill->name,
            'description' => $skill->description,
            'languages' => $skill->language ?? [],
            'visibility' => $skill->visibility,
            'version' => $skill->latestVersion?->version,
            'editable' => $skill->user_id === $user->id,
            'language' => $language,
            'content' => $skill->latestVersion?->contentFor($language),
            'url' => route('skills.edit', $skill),
        ];
    }

    /** @return array{content: string, language: ?string, visibility?: string, notes?: ?string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:65536'],
            'language' => ['sometimes', 'nullable', Rule::in(['r', 'python', 'stata'])],
            'visibility' => ['sometimes', Rule::in(Skill::VISIBILITIES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $data['language'] ??= null;

        return $data;
    }

    private function language(Request $request): ?string
    {
        return $request->validate(['language' => ['sometimes', 'nullable', Rule::in(['r', 'python', 'stata'])]])['language'] ?? null;
    }

    /** A skill the account can see, by the key skills_list shows: name, framework/name, or @owner/name. */
    private function find(Request $request, string $handle): Skill
    {
        $user = $this->user($request);
        $handle = ltrim($handle, '/');
        $matches = Skill::visibleTo($user)->with('owner')->where('key', basename($handle))->get()
            ->filter(fn (Skill $s) => $s->handle === $handle || ($s->user_id === $user->id && $s->key === $handle));

        return $matches->sortByDesc(fn (Skill $s) => $s->user_id === $user->id)->first() ?? abort(404, 'No skill has that key.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'This needs a user token.');

        return $user;
    }
}
