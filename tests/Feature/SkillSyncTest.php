<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillTag;
use App\Models\User;
use App\Support\SkillBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SkillSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_and_fork_ancestry_are_immutable(): void
    {
        $parent = Skill::create(['key' => 'parent', 'name' => 'Parent']);
        $source = $parent->publishVersion(['content' => 'Parent content']);
        $child = Skill::create(['key' => 'child', 'name' => 'Child', 'forked_from_id' => $source->id]);
        $identity = $child->sync_id;
        foreach (['sync_id' => 'replacement', 'forked_from_id' => null] as $field => $value) {
            try {
                $child->update([$field => $value]);
                $this->fail('Identity or ancestry changed.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('skill', $error->errors());
            }
            $child->refresh();
            $this->assertSame($identity, $child->sync_id);
            $this->assertSame($source->id, $child->forked_from_id);
        }
    }

    private function bundle(string $text = 'First', array $extra = []): array
    {
        return [['path' => 'SKILL.md', 'content' => base64_encode("---\nname: example\ndescription: Example tasks\n---\n{$text}\n"), 'executable' => false], ...$extra];
    }

    private function push(array $files, ?string $id = null, int $base = 0, string $variant = 'r', ?string $request = null)
    {
        return $this->postJson('/api/v1/skills/sync', ['request_id' => $request ?? (string) Str::uuid(), 'bundle' => $files, 'expected_version' => $base, 'variant' => $variant, 'skill_id' => $id]);
    }

    public function test_private_import_retry_updates_conflicts_and_binary_snapshots(): void
    {
        $this->actingAs(User::factory()->create());
        $binary = ['path' => 'scripts/run.bin', 'content' => base64_encode("\x00\xff\n"), 'executable' => true];
        $files = $this->bundle(extra: [$binary]);
        $request = (string) Str::uuid();
        $first = $this->push($files, request: $request)->assertOk()->assertJsonPath('version', 1)->json();
        $this->assertSame(SkillBundle::hash($files), $first['hash']);
        $this->push($files, request: $request)->assertOk()->assertJsonPath('skill_id', $first['skill_id']);
        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseCount('skill_versions', 1);
        $this->push($this->bundle('Changed'), request: $request)->assertStatus(409);
        $this->push($this->bundle('Next'), $first['skill_id'], 1)->assertOk()->assertJsonPath('version', 2);
        $this->push($this->bundle('Stale'), $first['skill_id'], 1)->assertStatus(409)->assertJsonPath('head.version', 2);
        $this->getJson('/api/v1/skills/sync/'.$first['skill_id'].'?version=1')->assertOk()->assertJsonPath('hash', $first['hash']);
        $this->assertSame('private', Skill::first()->visibility);
        $this->getJson('/api/v1/skills/sync/'.$first['skill_id'].'/history')->assertOk()->assertJsonCount(2, 'versions');
    }

    public function test_canonical_changes_fork_the_exact_source_and_preserve_variants_and_lineage(): void
    {
        $system = Skill::create(['key' => 'example', 'name' => 'Example', 'visibility' => 'public']);
        $system->tags()->attach(SkillTag::where('key', 'framework')->sole());
        $source = $system->publishVersion(['content' => base64_decode($this->bundle()[0]['content']), 'variants' => ['python' => 'Python source'], 'files' => ['references/readme.txt' => 'reference']]);
        $this->actingAs($user = User::factory()->create());
        $this->push(SkillBundle::files($source, 'r'), $system->sync_id, 1)->assertOk()->assertJsonPath('skill_id', $system->sync_id);
        $this->assertDatabaseCount('skills', 1);
        $fork = $this->push($this->bundle('Custom'), $system->sync_id, 1)->assertOk()->json();
        $child = Skill::where('sync_id', $fork['skill_id'])->firstOrFail();
        $this->assertEqualsCanonicalizing(['framework'], $child->tags->pluck('key')->all());
        $this->assertSame($source->id, $child->forked_from_id);
        $this->assertSame('Python source', $child->latestVersion->variants['python']);
        $this->push($this->bundle('Python edit'), $child->sync_id, 1, 'python')->assertOk();
        $child->refresh();
        $this->assertStringContainsString('Custom', $child->latestVersion->content);
        $this->assertSame($source->id, $child->forked_from_id);
        $child->update(['visibility' => 'public']);
        $this->actingAs(User::factory()->create());
        $next = $this->push($this->bundle('Child fork'), $child->sync_id, 2)->assertOk()->assertJsonPath('parent.skill_id', $child->sync_id)->json();
        $this->assertSame($child->latestVersion->id, Skill::where('sync_id', $next['skill_id'])->first()->forked_from_id);
        $system->delete();
        $this->actingAs($user);
        $this->getJson('/api/v1/skills/sync/'.$child->sync_id)->assertOk()->assertJsonPath('parent', null);
        $this->assertDatabaseHas('skill_versions', ['id' => $source->id]);
    }

    public function test_text_files_hash_with_lf_and_a_crlf_copy_of_a_built_in_is_not_a_change(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/skill-bundle-crlf.json')), true);
        $this->assertSame($fixture['hash'], SkillBundle::hash($fixture['lf']));
        $this->assertSame($fixture['hash'], SkillBundle::hash($fixture['crlf']));
        $this->assertSame($fixture['binary_lf_hash'], SkillBundle::hash($fixture['binary_lf']));

        $system = Skill::create(['key' => 'example', 'name' => 'Example', 'visibility' => 'public']);
        $source = $system->publishVersion(['content' => base64_decode($this->bundle()[0]['content'])]);
        $crlf = [['path' => 'SKILL.md', 'content' => base64_encode(str_replace("\n", "\r\n", base64_decode($this->bundle()[0]['content']))), 'executable' => false]];
        $hash = SkillBundle::hash(SkillBundle::files($source, 'r'));
        $this->assertSame($hash, SkillBundle::hash($crlf));
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/skills/sync/'.$system->sync_id.'?hash='.$hash)->assertOk()->assertJsonPath('version', 1);
        $this->push($crlf, $system->sync_id, 1)->assertOk()->assertJsonPath('skill_id', $system->sync_id)->assertJsonPath('hash', $hash);
        $this->assertDatabaseCount('skills', 1);
    }

    public function test_private_skills_and_project_tokens_are_inaccessible(): void
    {
        $this->actingAs($user = User::factory()->create());
        $id = $this->push($this->bundle())->assertOk()->json('skill_id');
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/skills/sync/'.$id)->assertNotFound();
        $this->push($this->bundle('Other'), $id, 1)->assertNotFound();
        auth()->forgetGuards();
        $this->withToken(Project::factory()->create()->issuePullToken());
        $this->getJson('/api/v1/skills/sync/'.$id)->assertForbidden();
        $this->push($this->bundle())->assertForbidden();
    }

    public function test_unsafe_paths_and_size_limits_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['../escape', '/absolute', 'x/../escape', '.env', 'x/.env.local', 'secret.key', 'scripts/a/child'] as $path) {
            $extra = [['path' => $path, 'content' => base64_encode('secret'), 'executable' => false]];
            if ($path === 'scripts/a/child') {
                $extra[] = ['path' => 'scripts/a', 'content' => '', 'executable' => false];
            }
            $this->push($this->bundle(extra: $extra))->assertUnprocessable();
        }
    }

    public function test_shared_hash_empty_files_web_variant_edits_and_restore(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/skill-bundle-v1.json')), true);
        $this->assertSame($fixture['hash'], SkillBundle::hash($fixture['bundle']));
        $this->actingAs($user = User::factory()->create());
        $created = $this->push($fixture['bundle'], variant: 'python')->assertOk()->assertJsonPath('hash', $fixture['hash'])->json();
        $skill = Skill::where('sync_id', $created['skill_id'])->firstOrFail();
        $content = base64_decode(collect($fixture['bundle'])->firstWhere('path', 'SKILL.md')['content']);
        $this->put('/skills/'.$skill->id, ['content' => $content."Web edit\n", 'variant' => 'python', 'visibility' => 'private', 'expected_version' => 1])->assertSessionHasNoErrors();
        $skill->refresh();
        $this->assertStringContainsString('Web edit', $skill->latestVersion->contentFor('python'));
        $this->assertSame($content, $skill->latestVersion->content);
        $this->put('/skills/'.$skill->id, ['content' => $content."Stale\n", 'variant' => 'python', 'visibility' => 'private', 'expected_version' => 1])->assertSessionHasErrors('content');
        $this->post('/skills/'.$skill->id.'/restore', ['version' => 1, 'expected_version' => 2])->assertSessionHasNoErrors();
        $skill->refresh();
        $this->assertSame(3, $skill->latestVersion->version);
        $this->assertSame($fixture['hash'], SkillBundle::hash(SkillBundle::files($skill->latestVersion, 'python')));
        $this->assertSame(1, $skill->versions()->where('version', 1)->first()->version);
    }

    public function test_concurrent_edits_to_different_variants_preserve_both(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->push($this->bundle('R initial'))->assertOk()->json();
        $this->push($this->bundle('Python edit'), $first['skill_id'], 1, 'python')->assertOk()->assertJsonPath('version', 2);
        $this->push($this->bundle('R edit'), $first['skill_id'], 1, 'r')->assertOk()->assertJsonPath('version', 3);
        $head = Skill::where('sync_id', $first['skill_id'])->first()->latestVersion;
        $this->assertStringContainsString('R edit', $head->contentFor('r'));
        $this->assertStringContainsString('Python edit', $head->contentFor('python'));
    }
}
