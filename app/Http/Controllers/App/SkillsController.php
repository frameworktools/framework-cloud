<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\User;
use App\Support\Docs\Markdown;
use App\Support\Library;
use App\Support\ProjectDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SkillsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Skills/Index', [
            'skills' => Skill::listedFor($user)->with(['owner', 'latestVersion'])
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('key')
                ->get()->map(fn ($s) => Library::skill($s, $user)),
            'language' => self::preferredLanguage($user),
        ]);
    }

    /** The R or Python tab a catalog starts on: the saved language, R for Stata. */
    public static function preferredLanguage(User $user): string
    {
        return (ProjectDefaults::for($user)['language'] ?? 'r') === 'python' ? 'python' : 'r';
    }

    /**
     * GET /skills/{skill}?language=r: the whole skill, rendered, exactly as
     * a project in that language gets it, with its source beside.
     */
    public function show(Request $request, Skill $skill): Response
    {
        $user = $request->user();
        abort_unless($skill->isVisibleTo($user), 404);
        $skill->load('owner', 'latestVersion');

        $languages = $skill->language ?: ['r', 'python'];
        $asked = $request->query('language');
        $language = in_array($asked, $languages, true) ? $asked : (in_array(self::preferredLanguage($user), $languages, true) ? self::preferredLanguage($user) : $languages[0]);
        $source = $skill->latestVersion->contentFor($language);
        // Agents read the frontmatter; people read what follows it
        $body = preg_replace('/\A---\R.*?\R---\R?/s', '', $source);

        return Inertia::render('Skills/Show', [
            'skill' => Library::skill($skill, $user),
            'language' => $language,
            // Languages with their own text, where the reader can switch
            'variants' => $skill->language ? array_values(array_intersect($skill->language, ['r', 'python'])) : [],
            'html' => Markdown::render($body)['html'],
            'source' => $source,
            'from' => in_array($request->query('from'), ['skills', 'skill-sets'], true) ? $request->query('from') : 'skills',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Skills/Edit', ['skill' => null, 'content' => self::STARTER]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, null);

        if (Skill::where('user_id', $user->id)->where('key', $data['key'])->exists()) {
            throw ValidationException::withMessages(['content' => "You already have a skill named {$data['key']}."]);
        }

        $skill = Skill::create([
            'user_id' => $user->id,
            'key' => $data['key'],
            'name' => $data['title'],
            'description' => $data['description'],
            'language' => $data['language'],
            'visibility' => $data['visibility'],
        ]);
        $skill->publishVersion(['content' => $data['content'], 'files' => [], 'notes' => $data['notes']]);

        return to_route('skills.edit', $skill)->with('status', 'skill-saved');
    }

    /** Owners get the editor; anyone else who can see a skill reads it. */
    public function edit(Request $request, Skill $skill): Response|RedirectResponse
    {
        abort_unless($skill->isVisibleTo($request->user()), 404);
        if (! $skill->isEditableBy($request->user())) {
            return to_route('skills.show', $skill);
        }

        return Inertia::render('Skills/Edit', [
            'skill' => Library::skill($skill->load('owner'), $request->user()) + [
                'versions' => $skill->versions()->get(['id', 'version', 'notes', 'created_at']),
            ],
            'content' => $skill->latestVersion->content,
        ]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        abort_unless($skill->isEditableBy($request->user()), 403);

        $data = $this->validated($request, $skill);

        DB::transaction(function () use ($skill, $data) {
            // A skill with text per language keeps its languages and variants
            $variants = $skill->latestVersion->variants;
            $skill->update(['name' => $data['title'], 'description' => $data['description'], 'visibility' => $data['visibility']]
                + ($variants ? [] : ['language' => $data['language']]));
            $skill->publishVersion(['content' => $data['content'], 'variants' => $variants, 'files' => $skill->latestVersion->files ?? [], 'notes' => $data['notes']]);
        });

        return back()->with('status', 'skill-saved');
    }

    public function fork(Request $request, Skill $skill): RedirectResponse
    {
        $user = $request->user();
        abort_unless($skill->isVisibleTo($user), 404);

        if (Skill::where('user_id', $user->id)->where('key', $skill->key)->exists()) {
            return back()->withErrors(['skill' => "You already have a skill named {$skill->key}."]);
        }

        $source = $skill->latestVersion;
        $copy = Skill::create([
            'user_id' => $user->id,
            'key' => $skill->key,
            'name' => $skill->name,
            'description' => $skill->description,
            'language' => $skill->language,
            'visibility' => 'private',
            'forked_from_id' => $source->id,
        ]);
        $copy->publishVersion([
            'content' => $source->content,
            'variants' => $source->variants,
            'files' => $source->files ?? [],
            'notes' => "Forked from {$skill->handle} v{$source->version}",
        ]);

        return to_route('skills.edit', $copy)->with('status', 'skill-forked');
    }

    public function destroy(Request $request, Skill $skill): RedirectResponse
    {
        abort_unless($skill->isEditableBy($request->user()) && ! $skill->isSystem(), 403);

        $skill->delete();

        return to_route('skills.index');
    }

    /**
     * The skill's name and description come from its SKILL.md frontmatter,
     * which is what assistants read. A saved skill keeps its name.
     *
     * @return array{key: string, description: string, content: string, visibility: string, notes: ?string}
     */
    private function validated(Request $request, ?Skill $skill): array
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:65536'],
            'visibility' => ['required', Rule::in(Skill::VISIBILITIES)],
            'language' => ['nullable', Rule::in(['r', 'python'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        ['key' => $key, 'description' => $description, 'title' => $title] = Skill::frontmatter($data['content'], 'content');

        if ($skill && $key !== $skill->key) {
            throw ValidationException::withMessages(['content' => "This skill is named {$skill->key}. Fork it to make one with a new name."]);
        }

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'content' => $data['content'],
            'visibility' => $data['visibility'],
            'language' => filled($data['language'] ?? null) ? [$data['language']] : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private const STARTER = <<<'MD'
        ---
        name: my-skill
        description: Use when ... (say when an assistant should load this skill)
        metadata:
          title: My skill
        ---

        # My skill

        Explain the task, the rules, and the reasons behind them.

        MD;
}
