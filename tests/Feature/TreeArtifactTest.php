<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactEmbed;
use App\Support\ArtifactPublisher;
use App\Support\Trees\ArtifactTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

/** The tree kind: a project's folder structure, with notes and ignored paths as stubs. */
class TreeArtifactTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($user)->create(['slug' => 'mobility']);
    }

    /** The plan's shared fixture, as a client publishes it, with a note and a highlight. */
    private function tree(array $changes = []): array
    {
        return array_replace([
            'schema' => 'framework.artifact/v1',
            'kind' => 'tree',
            'source' => ['language' => 'r'],
            'root' => 'my-project',
            'base' => '',
            'method' => 'git',
            'nodes' => [
                ['path' => 'outputs/table.csv', 'type' => 'file'],
                ['path' => '.gitignore', 'type' => 'file'],
                ['path' => '', 'type' => 'ignored', 'ignored' => ['rule' => '.env', 'source' => '.gitignore', 'line' => 3], 'hidden' => 1],
                ['path' => 'README.md', 'type' => 'file', 'note' => 'Start here'],
                ['path' => 'R', 'type' => 'dir', 'note' => 'Reusable functions'],
                ['path' => 'R/clean.R', 'type' => 'file'],
                ['path' => 'data', 'type' => 'dir'],
                ['path' => 'data/raw', 'type' => 'dir', 'ignored' => ['rule' => 'data/raw/', 'source' => '.gitignore', 'line' => 1], 'hidden' => 2],
                ['path' => 'data/private', 'type' => 'dir', 'private' => true, 'hidden' => 1],
                ['path' => 'outputs', 'type' => 'dir'],
                ['path' => 'outputs', 'type' => 'ignored', 'ignored' => ['rule' => '*.rds', 'source' => '.gitignore', 'line' => 2], 'hidden' => 2],
            ],
            'highlight' => ['R/clean.R'],
        ], $changes);
    }

    private function publish(array $tree, array $input = []): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'slug' => 'structure',
            'file' => UploadedFile::fake()->createWithContent('structure.json', json_encode($tree)),
        ]);
    }

    public function test_paths_nest_and_sort_folders_then_files_then_ignored_groups(): void
    {
        $tree = ArtifactTree::normalize($this->tree())['tree'];

        $this->assertSame(['data', 'outputs', 'R', '.gitignore', 'README.md', '.env'], array_column($tree['children'], 'name'));
        $this->assertSame(['dirs' => 5, 'files' => 4, 'ignored' => 3, 'hidden' => 6], $tree['counts']);

        [$data, $outputs, $r] = $tree['children'];
        $this->assertSame(['private', 'raw'], array_column($data['children'], 'name'));
        $this->assertSame(['rule' => 'data/raw/', 'source' => '.gitignore', 'line' => 1], $data['children'][1]['ignored']);
        $this->assertFalse($data['children'][1]['open'], 'A stub never opens');
        $this->assertSame(['table.csv', '*.rds'], array_column($outputs['children'], 'name'));
        $this->assertSame('ignored', $outputs['children'][1]['type']);
        $this->assertSame('Reusable functions', $r['note']);
        $this->assertTrue($r['open'], 'Top-level folders start open');
        $this->assertTrue($r['children'][0]['highlight']);
    }

    public function test_missing_parent_folders_are_added_and_open_sets_what_starts_open(): void
    {
        $tree = ArtifactTree::normalize($this->tree([
            'nodes' => [['path' => 'notebooks/models/fit.qmd', 'type' => 'file'], ['path' => 'R/clean.R', 'type' => 'file']],
            'open' => ['notebooks/models'],
            'highlight' => [],
        ]))['tree'];

        [$notebooks, $r] = $tree['children'];
        $this->assertSame('notebooks', $notebooks['name']);
        $this->assertFalse($notebooks['open']);
        $this->assertTrue($notebooks['children'][0]['open']);
        $this->assertFalse($r['open']);
    }

    public function test_a_broken_tree_is_refused_with_a_reason(): void
    {
        $cases = [
            'nodes' => [[['path' => '../etc/passwd', 'type' => 'file']], 'nodes[0] has an invalid path: paths are relative, with / between names and no .. or backslashes.'],
            'backslash' => [[['path' => 'R\\clean.R', 'type' => 'file']], 'nodes[0] has an invalid path: paths are relative, with / between names and no .. or backslashes.'],
            'git' => [[['path' => '.git/config', 'type' => 'file']], 'nodes[0] is inside .git, which a project structure leaves out.'],
            'type' => [[['path' => 'R', 'type' => 'link']], 'nodes[0] needs type dir, file, or ignored.'],
            'stub' => [[['path' => 'data/raw', 'type' => 'dir', 'hidden' => 2], ['path' => 'data/raw/a.csv', 'type' => 'file']], '"data/raw/a.csv" is inside "data/raw", whose contents aren\'t listed.'],
            'both' => [[['path' => 'R', 'type' => 'file'], ['path' => 'R/clean.R', 'type' => 'file']], '"R" is both a file and a folder.'],
            'group' => [[['path' => 'outputs', 'type' => 'ignored', 'hidden' => 2]], 'nodes[0] needs ignored, the rule that hides its files.'],
            'source' => [[['path' => 'x', 'type' => 'dir', 'ignored' => ['rule' => 'x/', 'source' => '/Users/ada/.gitignore_global'], 'hidden' => 1]], 'nodes[0]\'s ignored source is a path relative to the root, global, or info/exclude.'],
        ];
        foreach ($cases as $case => [$nodes, $message]) {
            try {
                ArtifactTree::normalize($this->tree(['nodes' => $nodes, 'highlight' => []]));
                $this->fail("Accepted: {$case}");
            } catch (InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage(), $case);
            }
        }
        foreach ([['highlight' => ['nowhere.R']], ['open' => ['R/clean.R']], ['root' => ''], ['method' => 'svn']] as $changes) {
            try {
                ArtifactTree::normalize($this->tree($changes));
                $this->fail('Accepted: '.json_encode($changes));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(ValidationException::class);
        $this->publish($this->tree(['nodes' => [['path' => '/etc', 'type' => 'dir']]]));
    }

    public function test_file_counts_cap_at_ten_thousand(): void
    {
        $tree = ArtifactTree::normalize($this->tree(['nodes' => [['path' => 'renv', 'type' => 'dir', 'depth' => true, 'hidden' => 152000]], 'highlight' => []]))['tree'];

        $this->assertSame(10000, $tree['children'][0]['hidden']);
        $this->assertSame('10,000+ files', ArtifactTree::fileCount(10000));
        $this->assertSame('1 file', ArtifactTree::fileCount(1));
    }

    public function test_a_tree_publishes_and_reads_as_its_folders(): void
    {
        $version = $this->publish($this->tree(), ['visibility' => 'public']);

        $this->assertSame('tree', $version->document->kind);
        $this->get($version->document->publicUrl())->assertOk()
            ->assertSee('my-project/')
            ->assertSee('Reusable functions')
            ->assertSee('ignored · data/raw/ · 2 files')->assertSee('├─', false)
            ->assertSee('private · 1 file')
            ->assertSee('title=".gitignore line 2"', false)
            ->assertSee('tree-row--highlight', false)
            ->assertSee('Download JSON')
            ->assertSee('Folder tree of my-project: 5 folders and 4 files, 3 ignored.');
    }

    public function test_a_tree_sent_as_a_plain_file_is_still_a_tree_and_kind_tree_needs_json(): void
    {
        $this->assertSame('tree', $this->publish($this->tree(), ['kind' => 'file'])->document->kind);

        $this->expectException(ValidationException::class);
        app(ArtifactPublisher::class)->publish($this->project, [
            'slug' => 'text-tree', 'kind' => 'tree',
            'file' => UploadedFile::fake()->createWithContent('structure.txt', "R/\n  clean.R\n"),
        ]);
    }

    public function test_the_embed_shows_notes_and_ignored_paths_until_they_are_turned_off(): void
    {
        $version = $this->publish($this->tree(), ['visibility' => 'unlisted']);

        $embed = $this->getJson('/api/embed/v1/artifacts/'.$version->document->key)->assertOk();
        $embed->assertJsonPath('kind', 'tree')
            ->assertJsonPath('description', null)
            ->assertJsonPath('alt', 'Folder tree of my-project: 5 folders and 4 files, 3 ignored.')
            ->assertJsonPath('data.tree.root', 'my-project')
            ->assertJsonPath('data.tree.children.2.note', 'Reusable functions')
            ->assertJsonPath('data.tree.children.5.name', '.env');

        $version->document->update(['display' => ['ignored' => false, 'notes' => false]]);
        $data = $this->getJson('/api/embed/v1/artifacts/'.$version->document->key)->assertOk()->json('data.tree');
        $this->assertSame(['data', 'outputs', 'R', '.gitignore', 'README.md'], array_column($data['children'], 'name'));
        $this->assertSame(['private'], array_column($data['children'][0]['children'], 'name'), 'Private stubs stay');
        $this->assertSame(['table.csv'], array_column($data['children'][1]['children'], 'name'));
        $this->assertArrayNotHasKey('note', $data['children'][2]);
    }

    public function test_display_settings_offer_notes_and_ignored_paths_only_when_the_tree_has_them(): void
    {
        $plain = $this->publish($this->tree(['nodes' => [['path' => 'R/clean.R', 'type' => 'file']], 'highlight' => []]), ['slug' => 'plain']);
        $full = $this->publish($this->tree());

        $keys = fn ($version) => array_column(ArtifactEmbed::displayOptions($version->document->fresh()), 'key');
        $this->assertNotContains('notes', $keys($plain));
        $this->assertNotContains('ignored', $keys($plain));
        $this->assertContains('notes', $keys($full));
        $this->assertContains('ignored', $keys($full));
    }

    public function test_whole_tree_is_off_until_the_owner_turns_it_on(): void
    {
        $version = $this->publish($this->tree(), ['visibility' => 'unlisted']);
        $url = '/api/embed/v1/artifacts/'.$version->document->key;

        $this->assertNull($this->getJson($url)->assertOk()->json('data.display'));

        $version->document->update(['display' => ['full' => true]]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.display.full', true);
    }
}
