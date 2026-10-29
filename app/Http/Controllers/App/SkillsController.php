<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Support\Library;
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
            'name' => $data['key'],
            'description' => $data['description'],
            'visibility' => $data['visibility'],
        ]);
        $skill->publishVersion(['content' => $data['content'], 'files' => [], 'notes' => $data['notes']]);

        return to_route('skills.edit', $skill)->with('status', 'skill-saved');
    }

    /** Anyone who can see a skill can read it; owners also get the editor. */
    public function edit(Request $request, Skill $skill): Response
    {
        abort_unless($skill->isVisibleTo($request->user()), 404);

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
            $skill->update(['description' => $data['description'], 'visibility' => $data['visibility']]);
            $skill->publishVersion(['content' => $data['content'], 'files' => $skill->latestVersion->files ?? [], 'notes' => $data['notes']]);
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
            'visibility' => 'private',
            'forked_from_id' => $source->id,
        ]);
        $copy->publishVersion([
            'content' => $source->content,
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
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        ['key' => $key, 'description' => $description] = Skill::frontmatter($data['content'], 'content');

        if ($skill && $key !== $skill->key) {
            throw ValidationException::withMessages(['content' => "This skill is named {$skill->key}. Fork it to make one with a new name."]);
        }

        return [
            'key' => $key,
            'description' => $description,
            'content' => $data['content'],
            'visibility' => $data['visibility'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private const STARTER = <<<'MD'
        ---
        name: my-skill
        description: Use when ... (say when an assistant should load this skill)
        ---

        # My skill

        Explain the task, the rules, and the reasons behind them.

        MD;
}
