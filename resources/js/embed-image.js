// An artifact's embed as a PNG, for sites that take images but not embeds
// (Substack, email, slides). The embed is drawn again off screen, light, at a
// fixed width, then captured at twice its size. The figure's caption, with the
// Framework credit, is part of the image; the Model details disclosure is not,
// since an image can't open it.

const WIDTH = 680;
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

/** A PNG blob of the embed document, drawn with the framework-embed renderer. */
export async function embedImage(renderer, doc) {
    const [{ toBlob }] = await Promise.all([loadLibrary()]);
    const stage = document.createElement('div');
    stage.setAttribute('aria-hidden', 'true');
    stage.dataset.theme = 'light';
    stage.className = 'light';
    Object.assign(stage.style, {
        position: 'fixed', left: '-10000px', top: '0', boxSizing: 'border-box', width: `${WIDTH}px`, padding: '24px 28px',
        background: '#ffffff', color: '#1f2229', colorScheme: 'light',
        fontFamily: "Georgia, 'Times New Roman', serif", fontSize: '18px', lineHeight: '1.6',
    });
    document.body.append(stage);
    try {
        const holder = document.createElement('div');
        stage.append(holder);
        renderer.mount(holder, doc, { lazy: false });
        stage.querySelectorAll('.fw-embed__details').forEach((details) => details.remove());
        stage.querySelectorAll('.fw-embed__scroll').forEach((scroll) => { scroll.style.overflow = 'visible'; });
        await Promise.all([waitForCharts(stage), waitForImages(stage), document.fonts?.ready]);
        // A wide table widens the image rather than being cut off
        // (the stage is border-box: its padding comes out of the width)
        const padding = stage.offsetWidth - stage.clientWidth + 56;
        const needed = Math.max(0, ...[...stage.querySelectorAll('table')].map((table) => table.scrollWidth));
        if (needed > stage.clientWidth - 56) stage.style.width = `${needed + padding}px`;
        const blob = await toBlob(stage, { pixelRatio: 2, backgroundColor: '#ffffff', width: stage.offsetWidth, height: stage.offsetHeight, style: { left: '0', position: 'static' } });
        if (!blob) throw new Error('The image could not be made.');
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
