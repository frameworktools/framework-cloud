<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\Snippets\AnsiText;
use App\Support\Snippets\ArtifactSnippet;
use App\Support\Snippets\CodeHighlighter;
use App\Support\Snippets\SmclText;
use App\Support\Snippets\StyledText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

/** The code_snippet kind: code and printed output from a notebook run. */
class CodeSnippetTest extends TestCase
{
    use RefreshDatabase;

    private const COMMIT = '3f2c9e1d4b5a69788796a5b4c3d2e1f0a9b8c7d6';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($user)->create(['slug' => 'schools']);
    }

    private function snippet(array $changes = []): array
    {
        return array_replace_recursive([
            'schema' => 'framework.artifact/v1',
            'kind' => 'code_snippet',
            'source' => ['language' => 'r', 'file' => 'notebooks/02-partial-pooling.qmd', 'label' => 'fit-bayes', 'lines' => [41, 43]],
            'snippet' => ['width' => 80, 'blocks' => [
                ['type' => 'code', 'text' => "fit <- brm(score ~ 1 + (1 | school), data = schools)\n"],
                ['type' => 'message', 'text' => 'Compiling Stan program...'],
                ['type' => 'code', 'text' => 'summary(fit)'],
                ['type' => 'output', 'text' => implode("\n", array_map(fn ($i) => " line {$i}", range(1, 20)))],
                ['type' => 'warning', 'text' => "Warning: There were 3 divergent transitions after warmup.\n\n"],
            ]],
            'display' => ['links' => [['rel' => 'notebook', 'url' => 'https://example.academic.page/notebooks/partial-pooling']]],
        ], $changes);
    }

    private function publish(array|string $snippet, array $input = []): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'slug' => 'fit-bayes',
            'file' => UploadedFile::fake()->createWithContent('code_snippet.json', is_string($snippet) ? $snippet : json_encode($snippet)),
        ]);
    }

    public function test_a_snippet_publishes_as_its_own_kind_and_reads_as_code_and_output(): void
    {
        $version = $this->publish($this->snippet(), ['visibility' => 'public']);

        $this->assertSame('code_snippet', $version->document->kind);
        $page = $this->get($version->document->publicUrl())->assertOk();
        $page->assertSee('Copy code')->assertSee('Download JSON')
            ->assertSee('02-partial-pooling.qmd · fit-bayes')->assertSee('Lines 41–43')
            ->assertSee('<span class="t-fn">brm</span>', false)
            ->assertSee('Output, 20 lines')
            ->assertSee('Compiling Stan program...')
            ->assertSee('Full notebook');
        // Short output stays open; the 20-line summary folds
        $blocks = ArtifactSnippet::fromJson(json_encode($this->snippet()))['snippet']['blocks'];
        $this->assertSame([false, true, false], array_column(array_values(array_filter($blocks, fn ($b) => $b['type'] !== 'code')), 'folded'));
        $this->assertSame('Warning: There were 3 divergent transitions after warmup.', $blocks[4]['text']);
    }

    public function test_the_api_publishes_and_reports_an_unchanged_snippet_without_a_new_version(): void
    {
        $token = $this->project->issuePullToken();
        $send = fn (array $snippet) => $this->withToken($token)->post('/api/v1/documents', [
            'slug' => 'fit-bayes',
            'file' => UploadedFile::fake()->createWithContent('code_snippet.json', json_encode($snippet)),
        ], ['Accept' => 'application/json']);

        $send($this->snippet())->assertCreated()->assertJson(['kind' => 'code_snippet', 'version' => 1, 'unchanged' => false]);
        $this->app['auth']->forgetGuards();
        $send($this->snippet())->assertOk()->assertJson(['version' => 1, 'unchanged' => true]);
        $this->app['auth']->forgetGuards();
        $changed = $this->snippet();
        $changed['snippet']['blocks'][2]['text'] = 'print(fit)';
        $send($changed)->assertCreated()->assertJson(['version' => 2, 'unchanged' => false]);

        $document = Document::where('slug', 'fit-bayes')->firstOrFail();
        $this->assertSame(2, $document->versions()->count());
        // The unchanged publish left no stored files behind
        $this->assertCount(2, Storage::disk('local')->directories('artifacts/'.$this->project->id));
    }

    public function test_invalid_snippets_fail_at_publish_with_a_reason(): void
    {
        $cases = [
            'image block' => [$this->withBlock(['type' => 'image', 'data' => 'iVBORw0KGgo=']), 'Block 6 has type "image"'],
            'no language' => [array_replace($this->snippet(), ['source' => ['language' => 'julia']]), 'source.language'],
            'no code' => [array_replace($this->snippet(), ['snippet' => ['blocks' => [['type' => 'output', 'text' => 'x']]]]), 'at least one code block'],
            'too much code' => [array_replace($this->snippet(), ['snippet' => ['blocks' => [['type' => 'code', 'text' => str_repeat("x <- 1\n", 401)]]]]), '400 lines of code'],
            'not JSON' => ['{"kind": "code_snippet", ', 'not valid JSON'],
        ];
        foreach ($cases as $case => [$snippet, $message]) {
            try {
                $this->publish($snippet);
                $this->fail("{$case} published");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($message, $e->errors()['file'][0], $case);
            }
        }
        $this->assertSame(0, Document::count());
    }

    private function withBlock(array $block): array
    {
        $snippet = $this->snippet();
        $snippet['snippet']['blocks'][] = $block;

        return $snippet;
    }

    public function test_long_output_is_cut_with_a_count_and_escape_codes_become_styles(): void
    {
        $long = implode("\n", range(1, 620));
        $snippet = ArtifactSnippet::normalize($this->withBlock(['type' => 'output', 'text' => "\e[1m\e[31mError\e[39m in\e[22m fit\n".$long, 'folded' => false]));
        $block = end($snippet['snippet']['blocks']);

        $this->assertCount(500, $block['lines']);
        $this->assertSame(['lines' => 121], $block['truncated']);
        $this->assertTrue($snippet['truncated']);
        $this->assertFalse($block['folded']);
        $this->assertSame([['bold ansi-red', 'Error'], ['bold', ' in'], ['', ' fit']], $block['lines'][0]);
        $this->assertStringStartsWith("Error in fit\n1\n", $block['text']);
    }

    public function test_ansi_progress_redraws_and_hyperlinks_keep_their_text(): void
    {
        $lines = AnsiText::lines("loading 10%\rloading 100%\n\e]8;;https://example.org\e\\docs\e]8;;\e\\ \e[38;5;196mred\e[0m");

        $this->assertSame("loading 100%\ndocs red", StyledText::text($lines));
        // 256-color codes are dropped, so the text stays plain and merges
        $this->assertSame([['', 'docs red']], $lines[1]);
    }

    public function test_smcl_keeps_stata_layout_and_styles(): void
    {
        $smcl = "{txt}\n      Source {c |}       SS           df       MS      Number of obs   ={res}        74\n"
            ."{txt}{hline 13}{c +}{hline 11}\n{space 7}price{col 14}{c |}{ralign 12:{res:-238.8943}}\n"
            .'{txt}{help regress##results:Stored results} {bf:{it:nested}} {unknown thing}';
        $lines = SmclText::lines($smcl, 80);

        $this->assertSame(
            "\n      Source │       SS           df       MS      Number of obs   =        74\n"
            ."─────────────┼───────────\n       price │   -238.8943\n"
            .'Stored results nested {unknown thing}',
            StyledText::text($lines),
        );
        $this->assertSame(['res', '        74'], end($lines[1]));
        $this->assertSame(['res', '-238.8943'], end($lines[3]));
        $this->assertContains(['bold italic', 'nested'], $lines[4]);
        $this->assertTrue(SmclText::isError("{err}variable {bf:foo} not found\n{txt}r(111);"));
        $this->assertFalse(SmclText::isError("{txt}\n{res}  12"));
    }

    public function test_stata_output_sent_as_smcl_becomes_output_and_error_blocks(): void
    {
        $snippet = ArtifactSnippet::normalize([
            'schema' => 'framework.artifact/v1', 'kind' => 'code_snippet', 'source' => ['language' => 'stata'],
            'snippet' => ['width' => 80, 'blocks' => [
                ['type' => 'code', 'text' => 'regress score income'],
                ['type' => 'output', 'smcl' => "{txt}\n      Source {c |}  {res}12\n"],
                ['type' => 'code', 'text' => 'regress score foo'],
                ['type' => 'output', 'smcl' => "{err}variable {bf:foo} not found\n{txt}{search r(111), local:r(111);}"],
            ]],
        ]);

        $this->assertSame(['code', 'output', 'code', 'error'], array_column($snippet['snippet']['blocks'], 'type'));
        $this->assertSame('      Source │  12', $snippet['snippet']['blocks'][1]['text']);
        $this->assertSame("variable foo not found\nr(111);", $snippet['snippet']['blocks'][3]['text']);
    }

    public function test_stata_command_names_are_keywords_only_in_command_position(): void
    {
        $lines = CodeHighlighter::lines("by region: regress score income if `touse'\nquietly summarize score", 'stata');
        $keywords = fn (array $line) => array_column(array_filter($line, fn ($segment) => $segment[0] === 'kw'), 1);

        $this->assertSame(['by', 'regress', 'if'], $keywords($lines[0]));
        $this->assertSame(['quietly', 'summarize'], $keywords($lines[1]));
        $this->assertContains(['var', "`touse'"], $lines[0]);
    }

    public function test_python_and_r_code_is_colored(): void
    {
        $python = CodeHighlighter::lines("def f(x=1):\n    return x  # done", 'python');
        $r = CodeHighlighter::lines('fit <- lm(y ~ x, data = d) # fit', 'r');

        $this->assertSame([['kw', 'def'], ['', ' '], ['fn', 'f']], array_slice($python[0], 0, 3));
        $this->assertContains(['com', '# done'], $python[1]);
        $this->assertContains(['fn', 'lm'], $r[0]);
        $this->assertContains(['com', '# fit'], $r[0]);
        $this->assertSame('fit <- lm(y ~ x, data = d) # fit', StyledText::lineText($r[0]));
    }

    public function test_permalinks_are_built_only_from_github_with_a_full_commit(): void
    {
        $source = fn (array $changes) => ArtifactSnippet::normalize(array_replace_recursive($this->snippet(), ['source' => $changes]))['source'];

        $this->assertSame(
            'https://github.com/frameworktools/bayes/blob/'.self::COMMIT.'/notebooks/02-partial-pooling.qmd#L41-L43',
            $source(['repository' => 'https://github.com/frameworktools/bayes.git', 'commit' => self::COMMIT])['permalink'],
        );
        $this->assertArrayNotHasKey('permalink', $source(['repository' => 'https://gitlab.com/a/b', 'commit' => self::COMMIT]));
        $this->assertArrayNotHasKey('permalink', $source(['repository' => 'https://github.com/a/b', 'commit' => 'abc1234']));
        $this->assertArrayNotHasKey('permalink', $source(['repository' => 'https://github.com/a/b', 'commit' => self::COMMIT, 'file' => '../secret.R']));
        $this->assertArrayNotHasKey('repository', $source(['repository' => 'javascript:alert(1)']));
    }

    public function test_links_are_https_and_a_model_link_needs_a_model_as_open_as_the_snippet(): void
    {
        $model = app(ArtifactPublisher::class)->publish($this->project, ['slug' => 'partial-pooling-model', 'visibility' => 'private',
            'file' => UploadedFile::fake()->createWithContent('coefficients.csv', "term,estimate\nintercept,2.1")])->document;
        $snippet = $this->snippet(['display' => ['links' => [
            ['rel' => 'notebook', 'url' => 'https://example.academic.page/notebooks/partial-pooling'],
            ['rel' => 'notebook', 'url' => 'javascript:alert(1)'],
            ['rel' => 'model', 'artifact' => 'partial-pooling-model'],
        ]]]);
        $version = $this->publish($snippet, ['visibility' => 'public']);

        $links = fn (bool $embed = false) => array_column(ArtifactSnippet::resolvedLinks(ArtifactSnippet::fromJson(json_encode($snippet)), $version->document->fresh(), $embed), 'label');
        $this->assertSame(['Full notebook'], $links());

        $model->update(['visibility' => 'unlisted']);
        $this->assertSame(['Full notebook'], $links(), 'An unlisted model is less open than a public snippet');

        $model->update(['visibility' => 'public']);
        $this->assertSame(['Full notebook', 'Model'], $links());
        $this->assertSame(['Full notebook', 'Model'], $links(embed: true));

        $model->update(['password_hash' => bcrypt('a-password')]);
        $this->assertSame(['Full notebook'], $links(embed: true));
    }

    public function test_the_embed_document_carries_the_snippet_with_its_styled_lines(): void
    {
        $version = $this->publish($this->snippet(['source' => ['repository' => 'https://github.com/frameworktools/bayes', 'commit' => self::COMMIT]]), ['visibility' => 'public']);

        $response = $this->getJson('/api/embed/v1/artifacts/'.$version->document->key.'/versions/1')->assertOk();
        $response->assertJsonPath('kind', 'code_snippet')
            ->assertJsonPath('alt', 'R code from 02-partial-pooling.qmd, with its output.')
            ->assertJsonPath('data.source.permalink', 'https://github.com/frameworktools/bayes/blob/'.self::COMMIT.'/notebooks/02-partial-pooling.qmd#L41-L43')
            ->assertJsonPath('data.snippet.blocks.0.type', 'code')
            ->assertJsonPath('data.snippet.blocks.0.text', 'fit <- brm(score ~ 1 + (1 | school), data = schools)')
            ->assertJsonPath('data.display.links.0', ['rel' => 'notebook', 'url' => 'https://example.academic.page/notebooks/partial-pooling']);
        $this->assertContains(['op', '<-'], $response->json('data.snippet.blocks.0.lines.0'));
    }

    public function test_output_none_hides_the_output_behind_a_toggle_on_embeds_and_the_reader(): void
    {
        $version = $this->publish($this->snippet(), ['visibility' => 'public']);
        $key = $version->document->key;

        foreach (['/api/embed/v1/artifacts/'.$key.'?output=none', '/api/embed/v1/resolve?url='.urlencode(url('/d/'.$key.'?output=none'))] as $route) {
            $this->getJson($route)->assertOk()
                ->assertJsonPath('alt', 'R code from 02-partial-pooling.qmd.')
                ->assertJsonPath('data.display.output', false)
                ->assertJsonCount(5, 'data.snippet.blocks')
                ->assertJsonPath('embed_url', route('embed.version', ['key' => $key, 'number' => 1]).'?output=none')
                ->assertJsonPath('url', fn ($url) => str_ends_with($url, '?output=none'));
        }
        $this->getJson('/api/embed/v1/artifacts/'.$key.'?output=all')->assertJsonMissingPath('data.display.output');

        $this->get($version->document->publicUrl().'?output=none')->assertOk()
            ->assertSee('data-shown="false"', false)->assertSee('Show output')
            ->assertSee('data-output hidden', false);
        $this->get($version->document->publicUrl())->assertOk()
            ->assertSee('Hide output')->assertDontSee('data-output hidden', false);
    }

    public function test_a_snippet_kind_requires_a_snippet_file(): void
    {
        $this->expectException(ValidationException::class);
        app(ArtifactPublisher::class)->publish($this->project, ['kind' => 'code_snippet', 'file' => UploadedFile::fake()->createWithContent('fit.R', 'x <- 1')]);
    }

    public function test_from_json_rejects_documents_over_two_megabytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ArtifactSnippet::fromJson(str_repeat(' ', ArtifactSnippet::MAX_BYTES + 1));
    }
}
