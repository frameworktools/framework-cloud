// framework.pub content Worker: serves published files from the R2 bucket at
// content.framework.pub/c/{token}/{path}, so file bytes never pass through
// the app server. The app checks who may read a file and signs a short-lived
// token (App\Support\ContentWorker); this checks the signature and expiry and
// streams the object, with the same sandbox headers the app used to send.
//
// Bindings: BUCKET (R2, framework-pub-artifacts), SIGNING_KEY (secret, the
// app's FW_CONTENT_SIGNING_KEY), APP_ORIGIN (e.g. https://framework.pub).
// Deploy with scripts/deploy-content-worker.

const TYPES = {
    html: 'text/html; charset=utf-8', htm: 'text/html; charset=utf-8',
    js: 'text/javascript', mjs: 'text/javascript', css: 'text/css',
    json: 'application/json', map: 'application/json',
    png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif',
    svg: 'image/svg+xml', webp: 'image/webp', ico: 'image/x-icon',
    woff: 'font/woff', woff2: 'font/woff2', ttf: 'font/ttf', otf: 'font/otf',
    csv: 'text/csv; charset=utf-8', tsv: 'text/tab-separated-values; charset=utf-8',
    txt: 'text/plain; charset=utf-8', pdf: 'application/pdf',
};

// A Vega-Lite chart page (App\\Support\\ChartPage::CSP): the pinned chart libraries, no network
const CHART_CSP = "sandbox allow-scripts; default-src 'none'; script-src https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'unsafe-inline'; img-src data: blob:; font-src data:";

const typeOf = (path) => TYPES[(path.split('.').pop() || '').toLowerCase()] || 'application/octet-stream';

const fromBase64Url = (s) => {
    const b = atob(s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (s.length % 4)) % 4));
    return Uint8Array.from(b, (c) => c.charCodeAt(0));
};

let keyPromise;
const hmacKey = (secret) => (keyPromise ??= crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['verify']));

const fail = (status, message) => new Response(message, { status, headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' } });

// "attachment; filename=..." safe for any name
const disposition = (name) => {
    const ascii = name.replace(/[^\x20-\x7e]|["\\]/g, '_');
    return `attachment; filename="${ascii}"; filename*=UTF-8''${encodeURIComponent(name)}`;
};

export default {
    async fetch(request, env) {
        if (request.method !== 'GET' && request.method !== 'HEAD') return fail(405, 'Method not allowed');
        const url = new URL(request.url);
        const match = url.pathname.match(/^\/c\/([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)(?:\/(.*))?$/);
        if (!match) return fail(404, 'Not found');
        const [, payload, signature, rawPath = ''] = match;

        let claims;
        try {
            const ok = await crypto.subtle.verify('HMAC', await hmacKey(env.SIGNING_KEY), fromBase64Url(signature), new TextEncoder().encode(payload));
            if (!ok) return fail(403, 'This link is not valid.');
            claims = JSON.parse(new TextDecoder().decode(fromBase64Url(payload)));
        } catch {
            return fail(403, 'This link is not valid.');
        }
        if (!claims.k || !(claims.e > Date.now() / 1000)) return fail(403, 'This link has expired. Reload the page to get a new one.');

        let key = claims.k;
        let type = claims.t;
        let path = '';
        if (claims.b) {
            // A file inside a bundle folder, by its relative path
            try { path = decodeURIComponent(rawPath); } catch { return fail(404, 'Not found'); }
            if (path.split('/').some((s) => s === '..' || s === '.') || path.includes('\\') || path.includes('\0') || path.startsWith('/')) return fail(404, 'Not found');
            if (path === '' || path.endsWith('/')) path += 'index.html';
            key = `${claims.k}/${path}`;
            type = typeOf(path);
        }

        let object = await env.BUCKET.get(key, { range: request.headers, onlyIf: request.headers });
        // A folder: its index page
        if (object === null && claims.b && !path.endsWith('.html')) {
            if (await env.BUCKET.head(`${key}/index.html`)) {
                const to = new URL(url);
                to.pathname = `${url.pathname.replace(/\/$/, '')}/index.html`;
                return Response.redirect(to.toString(), 302);
            }
        }
        if (object === null) return fail(404, 'Not found');

        const app = env.APP_ORIGIN || 'https://framework.pub';
        const headers = new Headers({
            'Content-Type': type || 'application/octet-stream',
            'X-Content-Type-Options': 'nosniff',
            'Content-Security-Policy': `${claims.c === 'chart' ? CHART_CSP : 'sandbox allow-scripts allow-downloads'}; frame-ancestors 'self' ${app}`,
            'Referrer-Policy': 'no-referrer',
            'Access-Control-Allow-Origin': '*',
            // Each link is signed and short-lived; browsers may keep it until it expires
            'Cache-Control': `private, max-age=${Math.max(0, Math.floor(claims.e - Date.now() / 1000))}`,
            'Accept-Ranges': 'bytes',
            ETag: object.httpEtag,
        });
        if (claims.a) headers.set('Content-Disposition', disposition(String(claims.a)));

        // Conditional request answered from the cache, or a range
        if (!('body' in object)) return new Response(null, { status: 304, headers });
        if (object.range && request.headers.has('range')) {
            const { offset = 0, length = object.size - offset } = object.range;
            headers.set('Content-Range', `bytes ${offset}-${offset + length - 1}/${object.size}`);
            headers.set('Content-Length', String(length));
            return new Response(request.method === 'HEAD' ? null : object.body, { status: 206, headers });
        }
        headers.set('Content-Length', String(object.size));
        return new Response(request.method === 'HEAD' ? null : object.body, { status: 200, headers });
    },
};
