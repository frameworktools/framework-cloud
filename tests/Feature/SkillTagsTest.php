<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillTag;
use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkillTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_curated_tags_survive_reseeding_and_forks_inherit_assignments(): void
    {
        $this->seed(BlueprintSeeder::class);
        $source = Skill::whereNull('user_id')->where('key', 'framework-packages')->sole();
        $this->assertEqualsCanonicalizing(['framework', 'packages'], $source->tags->pluck('key')->all());
        $tag = SkillTag::where('key', 'packages')->sole();
        $tag->update(['name' => 'Package management']);
        $source->tags()->sync([$tag->id]);
        $this->seed(BlueprintSeeder::class);
        $this->assertSame('Package management', $tag->fresh()->name);
        $this->assertSame([$tag->id], $source->tags()->pluck('skill_tags.id')->all());
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post("/skills/{$source->id}/fork")->assertSessionHasNoErrors();
        $fork = Skill::where('user_id', $user->id)->sole();
        $this->assertSame([$tag->id], $fork->tags()->pluck('skill_tags.id')->all());
        // The model hook also covers the sync API's private forks.
        $child = Skill::create(['user_id' => $user->id, 'key' => 'second-fork', 'name' => 'Fork', 'visibility' => 'private', 'forked_from_id' => $fork->latestVersion->id]);
        $this->assertSame([$tag->id], $child->tags()->pluck('skill_tags.id')->all());
    }

    public function test_only_admins_can_extend_or_rename_the_taxonomy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tag = SkillTag::first();
        $this->actingAs($user)->get('/admin/skill-tags')->assertForbidden();
        $this->post('/admin/skill-tags', ['name' => 'New subject'])->assertForbidden();
        $this->put("/admin/skill-tags/{$tag->id}", ['name' => 'Renamed'])->assertForbidden();
        $user->forceFill(['is_admin' => true])->save();
        $this->post('/admin/skill-tags', ['name' => 'New subject'])->assertSessionHasNoErrors();
        $this->put("/admin/skill-tags/{$tag->id}", ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('skill_tags', ['key' => 'new-subject', 'name' => 'New subject']);
        $this->assertSame($tag->key, $tag->fresh()->key);
    }

    public function test_stata_skills_can_be_created_tagged_read_and_updated(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'stata']]);
        $tags = SkillTag::whereIn('key', ['framework', 'data-reading'])->pluck('id')->all();
        $data = ['content' => "---\nname: survey-import\ndescription: Read survey files.\n---\n\n# Survey import\n\nUse import delimited.\n", 'language' => 'stata', 'visibility' => 'private', 'tags' => $tags];
        $this->actingAs($user)->post('/skills', $data)->assertSessionHasNoErrors();
        $skill = Skill::where('user_id', $user->id)->sole();
        $this->assertSame(['stata'], $skill->language);
        $this->assertCount(2, $skill->tags);
        $this->get("/skills/{$skill->id}")->assertInertia(fn (Assert $page) => $page->where('language', 'stata')->where('variants', ['stata'])->has('skill.tags', 2));
        $this->put("/skills/{$skill->id}", array_replace($data, ['tags' => [$tags[0]], 'content' => $data['content']."\nUpdated.\n", 'expected_version' => 1]))->assertSessionHasNoErrors();
        $this->assertCount(1, $skill->fresh()->tags);
        $content = $data['content']."\nSynced from a Stata project.\n";
        $this->postJson('/api/v1/skills/sync', [
            'request_id' => (string) Str::uuid(),
            'skill_id' => $skill->sync_id, 'expected_version' => 2, 'variant' => 'stata',
            'bundle' => [['path' => 'SKILL.md', 'content' => base64_encode($content), 'executable' => false]],
        ])->assertOk()->assertJsonPath('version', 3);
        $this->assertCount(1, $skill->fresh()->tags);
        $this->assertStringContainsString('Synced from a Stata project.', $skill->fresh()->latestVersion->contentFor('stata'));
        $this->put("/skills/{$skill->id}", array_replace($data, ['tags' => [999999]]))->assertSessionHasErrors('tags.0');
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->put("/skills/{$skill->id}", $data)->assertForbidden();
    }

    public function test_skill_catalogs_honor_every_saved_environment(): void
    {
        foreach (['r', 'python', 'stata'] as $language) {
            $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => $language]]);
            $this->actingAs($user)->get('/skills?language=python')->assertInertia(fn (Assert $page) => $page->where('language', $language)->has('tagOptions', 12));
            $this->get('/skills/create')->assertInertia(fn (Assert $page) => $page->where('language', $language));
            $this->get('/skill-sets?language=r')->assertInertia(fn (Assert $page) => $page->where('language', $language));
        }
    }
}
