import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import worker from '../../cloudflare/content-worker/worker.js';

const KEY = 'test-signing-key';
const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';

const encode = (value) => Buffer.from(value).toString('base64url');

// A link signed the way App\Support\ContentWorker signs one
function link(claims, path = 'figure.svg') {
    const payload = encode(JSON.stringify(claims));
    const signature = createHmac('sha256', KEY).update(payload).digest('base64url');
    return `https://content.example.test/c/${payload}.${signature}/${path}`;
}

const env = {
    SIGNING_KEY: KEY,
    APP_ORIGIN: 'https://app.example.test',
    BUCKET: {
        async get(key) {
            return key === 'artifacts/figure.svg' ? { body: SVG, size: SVG.length, httpEtag: '"etag"' } : null;
        },
        async head() {
            return null;
        },
    },
};

const fetchLink = (claims) => worker.fetch(new Request(link(claims)), env);
const now = () => Math.floor(Date.now() / 1000);

test('embed figures are public, cross-origin, sandboxed images without a download name', async () => {
    const response = await fetchLink({ k: 'artifacts/figure.svg', t: 'image/svg+xml', c: 'embed', a: 'figure.svg', e: now() + 5400 });

    assert.equal(response.status, 200);
    assert.equal(await response.text(), SVG);
    assert.equal(response.headers.get('Content-Type'), 'image/svg+xml');
    assert.equal(response.headers.get('Access-Control-Allow-Origin'), '*');
    assert.equal(response.headers.get('Cross-Origin-Resource-Policy'), 'cross-origin');
    assert.equal(response.headers.get('X-Content-Type-Options'), 'nosniff');
    assert.equal(response.headers.get('Content-Security-Policy'), "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    assert.equal(response.headers.get('Referrer-Policy'), 'no-referrer');
    assert.equal(response.headers.get('Content-Disposition'), null);
    const [, maxAge] = response.headers.get('Cache-Control').match(/^public, max-age=(\d+)$/);
    assert.ok(Number(maxAge) <= 5400 && Number(maxAge) >= 5390);
});

test('other links keep their private caching and frame restriction', async () => {
    const response = await fetchLink({ k: 'artifacts/figure.svg', t: 'image/svg+xml', a: 'figure.svg', e: now() + 3600 });

    assert.equal(response.status, 200);
    assert.match(response.headers.get('Cache-Control'), /^private, max-age=\d+$/);
    assert.match(response.headers.get('Content-Security-Policy'), /frame-ancestors 'self' https:\/\/app\.example\.test$/);
    assert.equal(response.headers.get('Cross-Origin-Resource-Policy'), null);
    assert.match(response.headers.get('Content-Disposition'), /^attachment; filename="figure\.svg"/);
});

test('expired, forged, and missing embed links fail', async () => {
    assert.equal((await fetchLink({ k: 'artifacts/figure.svg', c: 'embed', e: now() - 1 })).status, 403);
    assert.equal((await fetchLink({ k: 'artifacts/gone.svg', c: 'embed', e: now() + 60 })).status, 404);
    const forged = link({ k: 'artifacts/figure.svg', c: 'embed', e: now() + 60 }).replace(/(\/c\/[A-Za-z0-9_-]+\.)[A-Za-z0-9_-]+/, '$1AAAA');
    assert.equal((await worker.fetch(new Request(forged), env)).status, 403);
});
