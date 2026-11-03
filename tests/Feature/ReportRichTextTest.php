<?php

use App\Models\Project;
use App\Support\ReportPublisher;
use App\Support\ReportText;
use Inertia\Testing\AssertableInertia as Assert;

it('stores and publishes safe rich HTML while preserving published snapshots', function () {
    $project = Project::factory()->create();
    $publisher = app(ReportPublisher::class);
    $unsafe = '<h2>Findings</h2><p>A <strong>clear</strong> <em>result</em> with <u>context</u>.</p><ol><li>First</li></ol><a href="https://example.org" onclick="alert(1)">Source</a><a href="javascript:alert(1)">Bad link</a><img src=x onerror=alert(1)><script>window.richXss=true</script><iframe src="https://example.org"></iframe><p style="position:fixed" class="overlay">Conclusion</p>';
    $report = $publisher->create($project, ['title' => 'Rich report', 'visibility' => 'unlisted',
        'blocks' => [['id' => 'text', 'type' => 'text', 'format' => 'html', 'text' => $unsafe]]]);
    $saved = $report->draft_blocks[0]['text'];
    expect($saved)->toContain('<strong>clear</strong>', '<em>result</em>', '<u>context</u>', '<ol>', 'href="https://example.org"')
        ->not->toContain('onclick', 'javascript:', '<img', '<script', '<iframe', 'style=', 'class=');
    $version = $publisher->publish($report);
    $this->get($report->shareUrl())->assertOk()->assertSee('<h2>Findings</h2>', false)->assertSee('<strong>clear</strong>', false)
        ->assertDontSee('window.richXss')->assertDontSee('javascript:')->assertSee('href="#section-1"', false);
    $publisher->saveDraft($report, ['blocks' => [['id' => 'text', 'type' => 'text', 'format' => 'html', 'text' => '<p>New draft</p>']]]);
    expect($version->fresh()->blocks[0]['text'])->toBe($saved);
    $this->actingAs($project->user)->get('/reports/'.$report->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->where('report.draft_blocks.0.format', 'html')->where('report.draft_blocks.0.text', '<p>New draft</p>'));
});

it('opens older text as formatted HTML without changing its published snapshot', function () {
    $project = Project::factory()->create();
    $publisher = app(ReportPublisher::class);
    $report = $publisher->create($project, ['title' => 'Older report', 'blocks' => [['id' => 'old', 'type' => 'text', 'text' => "## Existing heading\n\nA **bold** finding."]]]);
    $version = $publisher->publish($report);
    $original = $version->blocks;
    $this->actingAs($project->user)->get('/reports/'.$report->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->where('report.draft_blocks.0.format', 'html')->where('report.draft_blocks.0.text', fn ($html) => str_contains($html, '<h2>Existing heading</h2>') && str_contains($html, '<strong>bold</strong>')));
    expect($version->fresh()->blocks)->toBe($original);
});

it('strips dangerous pasted HTML including SVG and malformed links', function () {
    $safe = ReportText::sanitize('<svg><a href="javascript:alert(1)">x</a></svg><p onmouseover="alert(1)">Safe</p><a href="data:text/html,bad">Data</a><a href="//example.org">Relative</a>');
    expect($safe)->toContain('<p>Safe</p>')->not->toContain('javascript:', '<svg', 'onmouseover', 'data:');
});

it('keeps the formatting the editor offers, and nothing it does not', function () {
    $html = '<h2>Results</h2><p><strong>b</strong> <em>i</em> <u>u</u> <s>s</s> <code>c</code> R<sup>2</sup> H<sub>2</sub>O '
        .'<a href="https://example.org" target="_blank">link</a></p><ul><li><p>one</p></li></ul><ol start="3"><li><p>three</p></li></ol>'
        .'<blockquote><p>quote</p></blockquote><pre><code>fit &lt;- lm(y ~ x)</code></pre><hr>'
        .'<h1>Too big</h1><img src="x.png"><table><tr><td>cell</td></tr></table><span style="color:red">styled</span>';
    $safe = ReportText::sanitize($html);

    foreach (['<h2>Results</h2>', '<strong>b</strong>', '<em>i</em>', '<u>u</u>', '<s>s</s>', '<code>c</code>', 'R<sup>2</sup>', 'H<sub>2</sub>O',
        '<ol start="3">', '<blockquote>', '<pre><code>fit &lt;- lm(y ~ x)</code></pre>', '<hr />'] as $kept) {
        expect($safe)->toContain($kept);
    }
    expect($safe)->toContain('rel="noopener noreferrer"')->not->toContain('target=', '<h1', '<img', '<table', 'style=');
});
