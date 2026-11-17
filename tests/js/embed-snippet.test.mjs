import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EMBED_ASSETS, embedSnippet } from '../../resources/js/embed-snippet.js';

test('a framework.pub snippet loads the bundle and falls back to a link', () => {
    const html = embedSnippet({ src: 'https://framework.pub/api/embed/v1/artifacts/abc', href: 'https://framework.pub/d/abc', title: 'Model' });
    assert.equal(html, [
        `<link rel="stylesheet" href="${EMBED_ASSETS}/framework-embed.css">`,
        `<script src="${EMBED_ASSETS}/framework-embed.iife.js" defer></script>`,
        '<framework-artifact src="https://framework.pub/api/embed/v1/artifacts/abc">',
        '  <a href="https://framework.pub/d/abc">Model</a>',
        '</framework-artifact>',
    ].join('\n'));
});

test('another server names itself as an allowed host', () => {
    const html = embedSnippet({ src: 'http://localhost:8000/api/embed/v1/artifacts/abc/versions/2', href: 'http://localhost:8000/d/abc/versions/2', title: 'T' });
    assert.match(html, /^<script>window\.FrameworkEmbedConfig = \{ hosts: \["http:\/\/localhost:8000"\] \};<\/script>\n/);
});

test('titles are escaped and an empty title still gives the link text', () => {
    const html = embedSnippet({ src: 'https://framework.pub/api/embed/v1/artifacts/abc', href: 'https://framework.pub/d/abc?a=1&b="2"', title: '<b>A & B</b>' });
    assert.ok(html.includes('<a href="https://framework.pub/d/abc?a=1&amp;b=&quot;2&quot;">&lt;b&gt;A &amp; B&lt;/b&gt;</a>'));
    assert.ok(embedSnippet({ src: 'https://framework.pub/api/embed/v1/artifacts/abc', href: 'https://framework.pub/d/abc', title: '' }).includes('>View artifact</a>'));
});
