// Run after npm run build with PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs.
// Serves fixture responses through browser request routing. No server is started.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const root = new URL('../..', import.meta.url);
const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
const appFile = manifest['resources/js/app.js'].file;
const fixture = { component: 'Reports/Edit', url: '/reports/7/edit', version: 'fixture', clearHistory: false, encryptHistory: false,
    props: { auth: { user: { name: 'Researcher', initials: 'R' } }, nav: [], flash: {}, singleUser: true,
        project: { id: 3, slug: 'pilot', name: 'Pilot study' }, canPublish: true,
        report: { id: 7, title: 'Pilot findings', description: '', slug: 'pilot-findings', visibility: 'private', password_protected: true, draft_blocks: [], versions: [] },
        artifacts: [{ id: 11, title: 'Estimated effects', slug: 'effects', kind: 'table', visibility: 'unlisted', password_protected: false, latest_version: 2, versions: [{ number: 2 }, { number: 1 }] }],
    },
};
let saved;
const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
    const page = await browser.newPage();
    page.setDefaultTimeout(5000);
    const errors = [];
    page.on('pageerror', error => { errors.push(error.message); console.error(error.message); });
    await page.route('https://report.test/**', async route => {
        const path = new URL(route.request().url()).pathname;
        if (path.startsWith('/build/')) {
            return route.fulfill({ body: await readFile(new URL(`public${path}`, root)), contentType: path.endsWith('.js') ? 'text/javascript' : 'text/css' });
        }
        if (route.request().method() === 'PATCH') {
            saved = route.request().postDataJSON();
            fixture.props.report = { ...fixture.props.report, ...saved, draft_blocks: saved.blocks };
            return route.fulfill({ json: fixture, headers: { 'X-Inertia': 'true' } });
        }
        if (path.endsWith('.png')) return route.fulfill({ status: 204 });
        const escapedPage = JSON.stringify(fixture).replaceAll('<', '\\u003c');
        return route.fulfill({ contentType: 'text/html', body: `<html><head><meta name="csrf-token" content="fixture"></head><body><script data-page="app" type="application/json">${escapedPage}</script><div id="app"></div><script type="module" src="/build/${appFile}"></script></body></html>` });
    });
    await page.goto('https://report.test/reports/7/edit');
    const sections = page.locator('article');
    const status = () => page.locator('[role=status]').innerText();
    const saveDraft = async () => {
        await page.getByRole('button', { name: 'Save draft', exact: false }).click();
        await page.waitForFunction(() => document.querySelector('[role=status]')?.textContent.trim() === 'Saved');
    };

    // A new text section starts empty and focused
    await page.getByRole('button', { name: 'Add text', exact: true }).click();
    const text = page.getByRole('textbox', { name: 'Section 1 text' });
    await page.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === 'Section 1 text');
    assert.equal((await text.innerText()).trim(), '', 'new text blocks contain no publishable boilerplate');

    // The shared toolbar formats the focused section
    await page.keyboard.type('Our findings');
    await page.keyboard.press(process.platform === 'darwin' ? 'Meta+a' : 'Control+a');
    await page.getByRole('button', { name: 'Bold', exact: true }).click();
    assert.match(await text.innerHTML(), /<strong>Our findings<\/strong>/);
    await page.getByRole('button', { name: 'Heading', exact: true }).click();
    assert.match(await text.innerHTML(), /<h2>/);
    await page.getByRole('button', { name: 'Bulleted list', exact: true }).click();
    assert.match(await text.innerHTML(), /<ul>/);
    const richHtml = await text.innerHTML();

    // Artifacts come from the picker
    await page.getByRole('button', { name: 'Add artifact', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: /Estimated effects/ }).click();
    assert.equal(await sections.count(), 2);
    await sections.nth(1).getByRole('combobox', { name: /Version/ }).selectOption('1');
    await sections.nth(1).getByRole('checkbox').check();
    await sections.nth(1).getByRole('button', { name: 'Move section 2 up' }).click();
    assert.match(await sections.first().getAttribute('aria-label'), /artifact/);
    await page.getByRole('button', { name: 'Add text', exact: true }).click();
    await sections.nth(2).getByRole('button', { name: 'Remove section 3' }).click();
    assert.equal(await sections.count(), 2);

    await saveDraft();
    assert.equal(saved.blocks[0].type, 'artifact');
    assert.equal(saved.blocks[0].version_number, 1);
    assert.equal(saved.blocks[0].share_unlisted, true);
    assert.equal(saved.blocks[1].text, richHtml);
    assert.equal(saved.blocks[1].format, 'html');
    assert.equal(Object.hasOwn(saved, 'password'), false, 'blank replacement must preserve protection');
    assert.equal(Object.hasOwn(saved, 'remove_password'), false);

    // Password changes wait for an explicit save
    await page.getByRole('checkbox', { name: 'Remove the password' }).check();
    await page.waitForTimeout(2000);
    assert.equal(await status(), 'Save to apply the password');
    assert.equal(Object.hasOwn(saved, 'remove_password'), false, 'autosave never changes the password');
    await saveDraft();
    assert.equal(saved.remove_password, true);
    assert.deepEqual(errors, []);
    console.log('Builder browser checks passed: empty focused text, shared toolbar formatting, artifact picker, reorder/remove, version pinning, sharing consent, and password-safe saves.');
} finally { await browser.close(); }
