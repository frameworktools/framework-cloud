// The HTML that embeds an artifact on any page, and the vendored renderer the
// share dialog previews it with. See docs/guide/embedding.md.

// framework-embed's built bundle, served by GitHub Pages with CORS. Same files as public/vendor/framework-embed.
export const EMBED_ASSETS = 'https://frameworktools.github.io/framework-embed';

// The bundle trusts framework.pub alone; any other server must name itself.
const DEFAULT_ORIGIN = 'https://framework.pub';

const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** The stylesheet, script, and <framework-artifact> element, with a plain link as the fallback. */
export function embedSnippet({ src, href, title }) {
    const origin = new URL(src).origin;
    const lines = [];
    if (origin !== DEFAULT_ORIGIN) lines.push(`<script>window.FrameworkEmbedConfig = { hosts: [${JSON.stringify(origin).replace(/</g, '\\u003c')}] };</script>`);
    lines.push(
        `<link rel="stylesheet" href="${EMBED_ASSETS}/framework-embed.css">`,
        `<script src="${EMBED_ASSETS}/framework-embed.iife.js" defer></script>`,
        `<framework-artifact src="${escape(src)}">`,
        `  <a href="${escape(href)}">${escape(title || 'View artifact')}</a>`,
        '</framework-artifact>',
    );
    return lines.join('\n');
}

// The vendored files' version, and a hash of their content for their URLs (from vite.config.js), so a new
// copy is never served from a browser's or Cloudflare's cache
export const EMBED_VERSION = '0.8.0';
const EMBED_HASH = typeof __FRAMEWORK_EMBED_HASH__ === 'undefined' ? EMBED_VERSION : __FRAMEWORK_EMBED_HASH__;

let renderer = null;

/** window.FrameworkEmbed, after loading the vendored stylesheet and script once. */
export function loadEmbedRenderer(base = '/vendor/framework-embed') {
    if (window.FrameworkEmbed) return Promise.resolve(window.FrameworkEmbed);
    renderer ??= new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-framework-embed]')) {
            const link = Object.assign(document.createElement('link'), { rel: 'stylesheet', href: `${base}/framework-embed.css?v=${EMBED_HASH}` });
            link.dataset.frameworkEmbed = '';
            document.head.append(link);
        }
        const script = Object.assign(document.createElement('script'), { src: `${base}/framework-embed.iife.js?v=${EMBED_HASH}`, async: true });
        script.onload = () => (window.FrameworkEmbed ? resolve(window.FrameworkEmbed) : reject(new Error('framework-embed did not load')));
        script.onerror = () => { renderer = null; script.remove(); reject(new Error('framework-embed did not load')); };
        document.head.append(script);
    });
    return renderer;
}
