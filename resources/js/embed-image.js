// An artifact's embed as a PNG, for sites that take images but not embeds
// (Substack, email, slides). The result is drawn again off screen, light, with
// the renderer's own layout and sizes, so it looks like the embed. Only the
// Model details disclosure is left out, since an image can't open it, and the
// artifact's address joins the caption, since an image can't link.

const MIN_WIDTH = 560;
const CHART_WIDTH = 720;
const MAX_WIDTH = 1200;
const PAD = 32;
const CHART_WAIT_MS = 6000;
// One type style for every image, whatever page makes it
export const IMAGE_FONT = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
export const IMAGE_TEXT = '#1f2229';

let library = null;
const loadLibrary = () => (library ??= import('html-to-image'));

function waitForCharts(root) {
    const charts = [...root.querySelectorAll('[data-fw-embed-chart]')];
    if (charts.length === 0) return Promise.resolve();
    const done = () => charts.every((chart) => ['ready', 'error'].includes(chart.dataset.fwEmbedChartState));
    return new Promise((resolve) => {
        const started = Date.now();
        const tick = () => (done() || Date.now() - started > CHART_WAIT_MS ? resolve() : setTimeout(tick, 50));
        tick();
    });
}

async function waitForImages(root) {
    await Promise.all([...root.querySelectorAll('img')].map((img) => (img.complete ? null : new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
    }))));
}

const el = (tag, styles, text) => {
    const node = document.createElement(tag);
    Object.assign(node.style, styles);
    if (text) node.textContent = text;
    return node;
};

/** The address readers can type, without the scheme: framework.pub/@ada/project/slug */
const displayAddress = (url) => {
    try {
        const { host, pathname } = new URL(url);
        return host + pathname.replace(/\/$/, '');
    } catch {
        return url;
    }
};

/**
 * html-to-image copies each element's computed style, with two distortions:
 * it rounds font sizes down (11.96px becomes 10.9px), and it pins a table to
 * its measured height, which counts the table's caption twice and stretches
 * the rows. These rules, added after its font CSS, restore the real sizes.
 */
async function exactCss(stage, getFontEmbedCSS) {
    const sizes = new Set();
    for (const node of [stage, ...stage.querySelectorAll('*')]) {
        const size = getComputedStyle(node).fontSize;
        node.setAttribute('data-fw-size', size);
        sizes.add(size);
    }
    const rules = [...sizes].map((size) => `[data-fw-size="${size}"]{font-size:${size}!important}`);
    rules.push('table,caption,thead,tbody,tfoot,tr,td,th{height:auto!important;block-size:auto!important}');
    return `${await getFontEmbedCSS(stage)}\n${rules.join('\n')}`;
}

/** A PNG blob of the embed document, drawn with the framework-embed renderer. */
export async function embedImage(renderer, doc, link) {
    const { toBlob, getFontEmbedCSS } = await loadLibrary();
    const chart = doc.kind === 'vega-lite' || doc.kind === 'image';

    const stage = el('div', {
        position: 'fixed', left: '-10000px', top: '0', boxSizing: 'border-box',
        width: `${(chart ? CHART_WIDTH : MIN_WIDTH) + PAD * 2}px`, padding: `${PAD}px`,
        background: '#ffffff', color: IMAGE_TEXT, colorScheme: 'light', fontFamily: IMAGE_FONT, fontSize: '16px', lineHeight: '1.5',
    });
    stage.setAttribute('aria-hidden', 'true');
    stage.dataset.theme = 'light';
    stage.className = 'light';
    document.body.append(stage);
    try {
        renderer.mount(stage, doc, { lazy: false });
        stage.querySelectorAll('.fw-embed__details').forEach((node) => node.remove());
        stage.querySelectorAll('.fw-embed').forEach((figure) => { figure.style.margin = '0'; });
        stage.querySelectorAll('.fw-embed__scroll').forEach((scroll) => { scroll.style.overflow = 'visible'; });

        // The address readers can type, on its own line at the end of the caption
        const address = displayAddress(link || doc.url || '');
        const caption = stage.querySelector('.fw-embed__caption');
        if (address && caption) {
            caption.append(el('span', { flexBasis: '100%', order: '2', color: 'var(--fw-embed-muted)', overflowWrap: 'anywhere' }, address));
        } else if (address) {
            stage.append(el('div', { marginTop: '12px', fontSize: '12px', color: '#6b7280', overflowWrap: 'anywhere' }, address));
        }

        await Promise.all([waitForCharts(stage), waitForImages(stage), document.fonts?.ready]);

        // Fit the width to the widest table, between the minimum and maximum
        if (!chart) {
            const widest = Math.max(0, ...[...stage.querySelectorAll('table')].map((table) => table.scrollWidth));
            const content = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, widest));
            stage.style.width = `${content + PAD * 2}px`;
        }

        const blob = await toBlob(stage, { pixelRatio: 2, backgroundColor: '#ffffff', width: stage.offsetWidth, height: stage.offsetHeight, style: { left: '0', position: 'static' }, fontEmbedCSS: await exactCss(stage, getFontEmbedCSS) });
        if (!blob) throw new Error('Could not create image.');
        return blob;
    } finally {
        stage.remove();
    }
}

/**
 * Copies the image. The clipboard item gets the blob's promise right away, so
 * Safari still counts the click as the user's gesture while the image is made.
 */
export async function copyImage(blobPromise) {
    if (typeof ClipboardItem === 'undefined' || !navigator.clipboard?.write) throw new Error('unsupported');
    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]);
}

export function downloadImage(blob, name) {
    const url = URL.createObjectURL(blob);
    const link = Object.assign(document.createElement('a'), { href: url, download: `${name || 'artifact'}.png` });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
