// The ⌘K docs search (resources/views/components/docs-search.blade.php). It loads
// one index of every docs site and the Cloud guide (App\Support\Docs\SearchIndex)
// the first time it opens, and ranks it in the browser with MiniSearch.
import MiniSearch from 'minisearch';

const dialog = document.querySelector('[data-docs-search]');
if (dialog) setUp(dialog);

function setUp(dialog) {
    const input = dialog.querySelector('[data-docs-search-input]');
    const list = dialog.querySelector('[data-docs-search-results]');
    const status = dialog.querySelector('[data-docs-search-status]');
    const filters = dialog.querySelector('[data-docs-search-filters]');
    const current = dialog.dataset.product || null;
    const intro = status.textContent;
    const mac = /Mac|iPhone|iPad/.test(navigator.platform);

    let data = null;
    let index = null;
    let loading = null;
    let scope = 'all';
    let results = [];
    let selected = 0;

    if (!mac) document.querySelectorAll('[data-docs-search-shortcut]').forEach((kbd) => { kbd.textContent = 'Ctrl K'; });

    const load = () => loading ??= fetch(dialog.dataset.index, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error(response.statusText))))
        .then((json) => {
            data = json;
            index = new MiniSearch({
                fields: ['title', 'keywords', 'text', 'group', 'body'],
                searchOptions: {
                    boost: { title: 6, keywords: 3, text: 2, group: 1, body: 1 },
                    prefix: true,
                    // One typo in a short word, two in a long one
                    fuzzy: (term) => (term.length < 3 ? false : term.length < 7 ? 1 : 2),
                    // This site's pages first, and pages before the sections inside them
                    boostDocument: (id) => {
                        const item = data.items[id];
                        return (item.product === current ? 1.5 : 1) * (item.type === 'heading' ? 0.85 : 1);
                    },
                    filter: (result) => scope === 'all' || data.items[result.id].product === scope,
                },
            });
            index.addAll(data.items.map((item, id) => ({ id, ...item })));
            renderFilters();
        })
        .catch(() => {
            loading = null;
            status.textContent = 'Search could not load. Try again.';
        });

    const search = (query) => {
        const q = query.trim();
        if (!q || !index) return [];
        let found = index.search(q, { combineWith: 'AND' });
        if (!found.length) found = index.search(q, { combineWith: 'OR' });
        // An exact name, such as data_read or fw_use, comes first
        const exact = q.toLowerCase().replace(/\(\)$/, '');
        return found
            .map((result) => ({ ...result, score: result.score * (data.items[result.id].title.toLowerCase().replace(/\(\)$/, '') === exact ? 4 : 1) }))
            .sort((a, b) => b.score - a.score)
            .slice(0, 40);
    };

    const renderFilters = () => {
        const products = Object.keys(data.labels);
        filters.hidden = products.length < 2;
        filters.replaceChildren(...[['all', 'All'], ...products.map((p) => [p, data.labels[p]])].map(([value, label]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.scope = value;
            button.textContent = label;
            button.setAttribute('aria-pressed', String(value === scope));
            button.className = 'shrink-0 rounded-md px-2.5 py-1 text-xs font-medium text-slate-500 hover:text-slate-900 aria-pressed:bg-sky-50 aria-pressed:text-sky-800 dark:text-slate-400 dark:hover:text-white dark:aria-pressed:bg-white/10 dark:aria-pressed:text-white';
            button.addEventListener('click', () => {
                scope = value;
                filters.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.scope === scope)));
                update();
                input.focus();
            });
            return button;
        }));
    };

    // Text with the matched words marked
    const marked = (text, terms) => {
        const fragment = document.createDocumentFragment();
        const words = [...new Set(terms)].filter((t) => t.length > 1).sort((a, b) => b.length - a.length);
        if (!words.length) {
            fragment.append(text);
            return fragment;
        }
        const pattern = new RegExp(`(${words.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'gi');
        text.split(pattern).forEach((part, i) => {
            if (i % 2) {
                const mark = document.createElement('mark');
                mark.className = 'bg-transparent font-semibold text-sky-700 dark:text-sky-300';
                mark.textContent = part;
                fragment.append(mark);
            } else if (part) {
                fragment.append(part);
            }
        });
        return fragment;
    };

    // About two lines of the section text around the first match
    const snippet = (body, terms) => {
        const lower = body.toLowerCase();
        const at = Math.min(...terms.map((t) => lower.indexOf(t)).filter((i) => i >= 0));
        if (!Number.isFinite(at)) return '';
        const start = Math.max(0, body.lastIndexOf(' ', Math.max(0, at - 60)) + 1);
        const end = Math.min(body.length, start + 200);
        return (start > 0 ? '…' : '') + body.slice(start, end).trim() + (end < body.length ? '…' : '');
    };

    const href = (item) => {
        const origin = data.origins[item.product] ?? '';
        return origin === window.location.origin ? item.url : origin + item.url;
    };

    const render = () => {
        const query = input.value.trim();
        list.replaceChildren();
        status.hidden = results.length > 0;
        status.textContent = query && index ? `Nothing matches “${query}”.` : intro;
        input.setAttribute('aria-expanded', String(results.length > 0));

        results.forEach((result, i) => {
            const item = data.items[result.id];
            const terms = result.terms;
            const a = document.createElement('a');
            a.href = href(item);
            a.id = `docs-search-result-${i}`;
            a.setAttribute('role', 'option');
            a.className = 'block px-5 py-2.5 aria-selected:bg-sky-50 dark:aria-selected:bg-sky-900/30';

            const top = document.createElement('div');
            top.className = 'flex items-baseline justify-between gap-3';
            const title = document.createElement('p');
            title.className = `${item.type === 'entry' ? 'font-mono ' : ''}min-w-0 truncate text-sm font-medium text-slate-900 dark:text-white`;
            title.append(marked(item.title, terms));
            const label = document.createElement('span');
            label.className = 'shrink-0 text-xs text-slate-400 dark:text-slate-500';
            label.textContent = data.labels[item.product] ?? '';
            top.append(title, label);

            const where = document.createElement('p');
            where.className = 'mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400';
            where.textContent = [item.type === 'heading' ? item.text : null, item.group].filter(Boolean).join(' · ')
                || item.text;

            a.append(top, where);

            // Section text where the words matched there; otherwise the page's description or summary
            const inBody = Object.values(result.match).some((fields) => fields.includes('body'));
            const extra = inBody ? snippet(item.body, terms) : (item.type !== 'heading' ? item.text : '');
            if (extra && extra !== where.textContent) {
                const p = document.createElement('p');
                p.className = 'mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400';
                p.append(marked(extra, terms));
                a.append(p);
            }

            a.addEventListener('mousemove', () => { if (selected !== i) { selected = i; highlight(); } });
            a.addEventListener('click', () => dialog.close());
            list.append(a);
        });
        highlight();
    };

    const highlight = () => {
        list.querySelectorAll('[role=option]').forEach((a, i) => {
            const on = i === selected;
            a.setAttribute('aria-selected', String(on));
            if (on) {
                input.setAttribute('aria-activedescendant', a.id);
                a.scrollIntoView({ block: 'nearest' });
            }
        });
        if (!results.length) input.removeAttribute('aria-activedescendant');
    };

    const update = () => {
        results = search(input.value);
        selected = 0;
        render();
    };

    const open = () => {
        if (dialog.open) return;
        dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
        input.select();
        load().then(update);
    };

    dialog.addEventListener('close', () => { document.documentElement.style.overflow = ''; });
    // A click on the backdrop lands on the dialog itself
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
    dialog.querySelector('[data-docs-search-close]').addEventListener('click', () => dialog.close());

    input.addEventListener('input', () => (index ? update() : load().then(update)));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!results.length) return;
            selected = (selected + (e.key === 'ArrowDown' ? 1 : results.length - 1)) % results.length;
            highlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const a = list.querySelectorAll('[role=option]')[selected];
            if (!a) return;
            if (e.metaKey || e.ctrlKey) {
                window.open(a.href, '_blank', 'noopener');
            } else {
                dialog.close();
                window.location.href = a.href;
            }
        }
    });

    document.querySelectorAll('[data-docs-search-open]').forEach((button) => {
        button.addEventListener('click', open);
        // Start loading the index when a pointer heads for the button
        button.addEventListener('pointerenter', () => load(), { once: true });
        button.addEventListener('focus', () => load(), { once: true });
    });

    window.addEventListener('keydown', (e) => {
        const typing = e.target.closest?.('input, textarea, select, [contenteditable]');
        if ((e.key.toLowerCase() === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
            e.preventDefault();
            if (dialog.open) dialog.close();
            else open();
        }
    });
}
