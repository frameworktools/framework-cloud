<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\User;
use App\Support\ProjectSetup;
use App\Support\SkillBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class SkillSyncController extends Controller
{
    private function user(Request $r): User
    {
        abort_unless($r->user() instanceof User, 403, 'A user token is required.');

        return $r->user();
    }

    private function skill(Request $r, string $id): Skill
    {
        return Skill::visibleTo($this->user($r))->where('sync_id', $id)->firstOrFail();
    }

    public function show(Request $r, string $id)
    {
        $skill = $this->skill($r, $id);
        $variant = $this->variant($r);
        $versions = $skill->versions()->reorder()->orderByDesc('version');
        $version = $r->query('version') ? $versions->where('version', $r->integer('version'))->firstOrFail() : $versions->firstOrFail();
        if ($hash = $r->query('hash')) {
            $version = $skill->versions->first(fn ($v) => SkillBundle::hash(SkillBundle::files($v, $variant)) === $hash);
            abort_unless($version, 409, 'The installed source version is unavailable.');
        }

        return response()->json($this->snapshot($version, $variant));
    }

    public function history(Request $r, string $id)
    {
        $skill = $this->skill($r, $id);
        $variant = $this->variant($r);

        return response()->json(['versions' => $skill->versions->map(fn ($v) => [
            'version' => $v->version, 'hash' => SkillBundle::hash(SkillBundle::files($v, $variant)),
            'base_version_id' => $v->base_version_id, 'notes' => $v->notes, 'created_at' => $v->created_at,
        ])]);
    }

    private function variant(Request $r): string
    {
        return $r->validate(['variant' => ['sometimes', Rule::in(['r', 'python', 'stata'])]])['variant'] ?? 'r';
    }

    public function store(Request $r)
    {
        $user = $this->user($r);
        $d = $r->validate([
            'request_id' => ['required', 'uuid'], 'skill_id' => ['nullable', 'string', 'max:100'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'variant' => ['required', Rule::in(['r', 'python', 'stata'])],
            'bundle' => ['required', 'array', 'max:256'], 'bundle.*.executable' => ['sometimes', 'boolean'],
            'bundle.*.path' => ['required', 'string', 'max:240'],
            'bundle.*.content' => ['present', 'nullable', 'string', 'max:5592408'],
            'notes' => ['nullable', 'string', 'max:500'], 'client' => ['sometimes', 'array:app,version,device'],
            'client.*' => ['string', 'max:120'],
        ]);
        $files = SkillBundle::normalize($d['bundle']);
        $content = base64_decode(collect($files)->firstWhere('path', 'SKILL.md')['content']);
        abort_unless(mb_check_encoding($content, 'UTF-8') && strlen($content) <= 65536, 422, 'SKILL.md must be UTF-8 and at most 64 KB.');
        $meta = Skill::frontmatter($content, 'bundle');
        $fingerprint = hash('sha256', json_encode([$d['skill_id'] ?? null, $d['expected_version'], $d['variant'], SkillBundle::hash($files), $d['notes'] ?? null]));

        return DB::transaction(function () use ($r, $user, $d, $files, $content, $meta, $fingerprint) {
            // Serialize even first imports and retries, before a skill row exists.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('skill_sync_receipts')->where('user_id', $user->id)->where('request_id', $d['request_id'])->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->fingerprint, $fingerprint), 409, 'This request ID belongs to another change.');
                $version = SkillVersion::findOrFail($receipt->version_id);
                abort_unless(! $version->skill()->withTrashed()->first()->trashed(), 409, 'The skill was deleted.');
                $source = $version->skill->forked_from_id ? SkillVersion::find($version->skill->forked_from_id)?->skill : null;

                return response()->json($this->snapshot($version, $receipt->variant) + $this->useInProject($r, $user, $version->skill, $source));
            }
            $skill = ! empty($d['skill_id']) ? $this->skill($r, $d['skill_id']) : null;
            // The skill a copy is made from: a built-in, or someone else's
            $source = $skill && $skill->user_id !== $user->id ? $skill : null;
            $base = null;
            if ($skill) {
                $skill = Skill::whereKey($skill->id)->lockForUpdate()->firstOrFail();
                $base = $skill->versions()->where('version', $d['expected_version'])->first();
                abort_unless($base, 409, 'The base version is unavailable.');
                $head = $skill->latestVersion;
                if ($skill->user_id === $user->id && $head->version !== $d['expected_version']) {
                    // An identical retry can advance without creating another version.
                    if (SkillBundle::hash(SkillBundle::files($head, $d['variant'])) === SkillBundle::hash($files)) {
                        return response()->json($this->snapshot($head, $d['variant']) + $this->useInProject($r, $user, $skill, null));
                    }
                    if (SkillBundle::hash(SkillBundle::files($head, $d['variant'])) !== SkillBundle::hash(SkillBundle::files($base, $d['variant']))) {
                        return response()->json(['message' => 'The skill changed. Reconcile before syncing.', 'head' => $this->snapshot($head, $d['variant'])], 409);
                    }
                }
                if ($skill->user_id !== $user->id && SkillBundle::hash(SkillBundle::files($base, $d['variant'])) === SkillBundle::hash($files)) {
                    return response()->json($this->snapshot($base, $d['variant']));
                }
            } else {
                abort_unless($d['expected_version'] === 0, 409, 'New skills require base version 0.');
            }

            if (! $skill || $skill->user_id !== $user->id) {
                // Names never infer ancestry. Only an explicit tracked source creates an edge.
                $key = $meta['key'];
                if (Skill::withTrashed()->where('user_id', $user->id)->where('key', $key)->exists()) {
                    $key = substr($key, 0, 49).'-'.substr(str_replace('-', '', $d['request_id']), 0, 12);
                }
                $skill = Skill::create(['user_id' => $user->id, 'key' => $key, 'name' => $meta['title'],
                    'description' => $meta['description'], 'summary' => $meta['summary'], 'visibility' => 'private',
                    'language' => array_values(array_unique([...($skill?->language ?? []), $d['variant']])), 'forked_from_id' => $base?->id]);
            }
            $previous = $skill->latestVersion ?? $base;
            $variants = $previous?->variants ?? [];
            $bundleFiles = $previous?->bundle_files ?? [];
            // Preserve all language payloads. R is the historical shared/default text.
            if ($d['variant'] !== 'r') {
                $variants[$d['variant']] = $content;
            }
            $bundleFiles[$d['variant']] = array_values(array_filter($files, fn ($f) => $f['path'] !== 'SKILL.md'));
            $version = $skill->publishVersion([
                'content' => $d['variant'] === 'r' ? $content : ($previous?->content ?? $content),
                'variants' => $variants, 'files' => $previous?->files ?? [], 'bundle_files' => $bundleFiles,
                'base_version_id' => $base?->id, 'client' => $d['client'] ?? null, 'notes' => $d['notes'] ?? null,
            ]);
            $skill->update(['name' => $meta['title'], 'description' => $meta['description'], 'language' => array_values(array_unique([...($skill->language ?? []), $d['variant']]))]);
            DB::table('skill_sync_receipts')->insert(['user_id' => $user->id, 'request_id' => $d['request_id'], 'fingerprint' => $fingerprint, 'version_id' => $version->id, 'variant' => $d['variant']]);

            return response()->json($this->snapshot($version, $d['variant']) + $this->useInProject($r, $user, $skill, $source));
        });
    }

    /**
     * With the project's token as project_token, the project the push came
     * from uses the user's copy in place of the built-in skill its setup
     * listed, so the next pull keeps the copy. A problem never fails the push.
     *
     * @return array{}|array{project: array{slug: string, updated: bool, message?: string}}
     */
    private function useInProject(Request $r, User $user, Skill $mine, ?Skill $source): array
    {
        $plain = $r->input('project_token');
        if (! is_string($plain) || $plain === '' || $mine->user_id !== $user->id) {
            return [];
        }
        $token = PersonalAccessToken::findToken($plain);
        $project = $token?->tokenable;
        if (! $project instanceof Project || $project->user_id !== $user->id || ! $token->can(Project::PULL_ABILITY) || $token->expires_at?->isPast()) {
            return [];
        }

        try {
            return ['project' => ['slug' => $project->slug, 'updated' => ProjectSetup::useSkill($project, $mine, $source)]];
        } catch (ValidationException $e) {
            return ['project' => ['slug' => $project->slug, 'updated' => false, 'message' => collect($e->errors())->flatten()->first()]];
        }
    }

    public function snapshot(SkillVersion $version, string $variant): array
    {
        $skill = $version->skill;
        $files = SkillBundle::files($version, $variant);
        $source = $skill->forked_from_id ? SkillVersion::find($skill->forked_from_id) : null;
        $parent = $source?->skill()->withTrashed()->first();
        $visible = $parent && ! $parent->trashed() && $parent->isVisibleTo(auth()->user() instanceof User ? auth()->user() : null);

        return ['schema' => 1, 'skill_id' => $skill->sync_id, 'version' => $version->version,
            'variant' => $variant, 'hash' => SkillBundle::hash($files), 'bundle' => $files,
            'system' => $skill->isSystem(), 'name' => $skill->name,
            'parent' => $visible ? ['skill_id' => $parent->sync_id, 'version' => $source->version] : null];
    }
}
