// An artifact's embed as a PNG, for sites that take images but not embeds
// (Substack, email, slides). The result is drawn again off screen, light, and
// framed to stand on its own: the title and description above, the result,
// then a footer with the artifact's address, since an image can't link. The
// Model details disclosure is left out; an image can't open it.

const MIN_WIDTH = 560;
const CHART_WIDTH = 720;
const MAX_WIDTH = 1200;
const PAD = 40;
const CHART_WAIT_MS = 6000;

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

/** A PNG blob of the embed document, drawn with the framework-embed renderer. */
export async function embedImage(renderer, doc, link) {
    const { toBlob } = await loadLibrary();
    const font = getComputedStyle(document.body).fontFamily || 'ui-sans-serif, system-ui, sans-serif';
    const chart = doc.kind === 'vega-lite' || doc.kind === 'image';

    const stage = el('div', {
        position: 'fixed', left: '-10000px', top: '0', boxSizing: 'border-box',
        width: `${(chart ? CHART_WIDTH : MIN_WIDTH) + PAD * 2}px`, padding: `${PAD - 4}px ${PAD}px ${PAD - 12}px`,
        background: '#ffffff', color: '#1f2229', colorScheme: 'light', fontFamily: font, fontSize: '17px', lineHeight: '1.55',
    });
    stage.setAttribute('aria-hidden', 'true');
    stage.dataset.theme = 'light';
    stage.className = 'light';

    // Title and description
    const header = el('div', { marginBottom: '18px' });
    header.append(el('div', { fontSize: '22px', fontWeight: '600', lineHeight: '1.3', letterSpacing: '-0.01em' }, doc.title || 'Artifact'));
    if (doc.description) header.append(el('div', { marginTop: '6px', fontSize: '15px', color: '#5b6170', lineHeight: '1.5' }, doc.description));
    stage.append(header);

    // The result, without the renderer's caption (the header and footer replace it)
    const holder = el('div', {});
    stage.append(holder);
    document.body.append(stage);
    try {
        renderer.mount(holder, doc, { lazy: false });
        holder.querySelectorAll('.fw-embed__details, .fw-embed__caption').forEach((node) => node.remove());
        holder.querySelectorAll('.fw-embed').forEach((figure) => { figure.style.margin = '0'; figure.style.fontSize = '1em'; });
        holder.querySelectorAll('.fw-embed__scroll').forEach((scroll) => { scroll.style.overflow = 'visible'; });

        // Footer: where to find it
        const footer = el('div', {
            display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: '16px',
            marginTop: '22px', paddingTop: '12px', borderTop: '1px solid #e3e5ea', fontSize: '13px', color: '#6b7280',
        });
        footer.append(el('span', { overflowWrap: 'anywhere' }, displayAddress(link || doc.url || '')));
        footer.append(el('span', { fontWeight: '600', color: '#1f2229', whiteSpace: 'nowrap' }, 'Framework'));
        stage.append(footer);

        await Promise.all([waitForCharts(stage), waitForImages(stage), document.fonts?.ready]);

        // Fit the width to the widest table, between the minimum and maximum
        if (!chart) {
            const widest = Math.max(0, ...[...stage.querySelectorAll('table')].map((table) => table.scrollWidth));
            const content = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, widest));
            stage.style.width = `${content + PAD * 2}px`;
        }

        const blob = await toBlob(stage, { pixelRatio: 2, backgroundColor: '#ffffff', width: stage.offsetWidth, height: stage.offsetHeight, style: { left: '0', position: 'static' } });
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
