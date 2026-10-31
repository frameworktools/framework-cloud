<div id="docs-search" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-label="Search the documentation">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-search-close></div>
    <div class="relative flex min-h-full items-start justify-center p-4 pt-[13vh]">
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-white/10">
            <form action="/search" method="get" class="relative border-b border-slate-200 px-5 dark:border-slate-700">
                <svg class="pointer-events-none absolute top-1/2 left-5 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                <input id="docs-search-input" name="q" type="search" autocomplete="off" spellcheck="false"
                       placeholder="Search guides and reference"
                       class="h-14 w-full border-0 bg-transparent pr-20 pl-8 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none dark:text-white dark:placeholder:text-slate-500">
                <div class="pointer-events-none absolute inset-y-0 right-5 hidden items-center gap-1.5 sm:flex">
                    <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-[11px] text-slate-400 dark:border-slate-600 dark:bg-slate-700">↑↓</kbd>
                    <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-[11px] text-slate-400 dark:border-slate-600 dark:bg-slate-700">esc</kbd>
                </div>
            </form>
            <div id="docs-search-results" class="max-h-[60vh] overflow-y-auto py-2"></div>
            <p id="docs-search-empty" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Type to search guides, headings, and the reference.</p>
        </div>
    </div>
</div>

<script>
    (() => {
        const dialog = document.getElementById('docs-search');
        const input = document.getElementById('docs-search-input');
        const list = document.getElementById('docs-search-results');
        const empty = document.getElementById('docs-search-empty');
        const groups = { guide: 'Guides', heading: 'Sections of guides', entry: 'Reference' };
        let index = null;
        let results = [];
        let selected = 0;

        const load = async () => {
            if (index) return index;
            try {
                const response = await fetch('/search.json', { headers: { Accept: 'application/json' } });
                index = response.ok ? await response.json() : [];
            } catch { index = []; }
            return index;
        };

        // The same ranking as App\Support\Docs\Search::query()
        const rank = (query) => {
            const q = query.trim().toLowerCase();
            if (!q) return [];
            const words = q.split(/\s+/);
            return index.map((item, i) => {
                const title = item.title.toLowerCase();
                const keywords = (item.keywords || '').toLowerCase();
                const haystack = `${title} ${keywords} ${(item.text || '').toLowerCase()}`;
                if (!words.every((w) => haystack.includes(w))) return null;
                let score = title === q ? 100 : title.startsWith(q) ? 80 : title.includes(q) ? 60 : keywords.includes(q) ? 40 : 20;
                if (item.type !== 'heading') score += 5;
                return { item, score, i };
            }).filter(Boolean).sort((a, b) => b.score - a.score || a.i - b.i).slice(0, 30).map((r) => r.item);
        };

        const render = () => {
            list.replaceChildren();
            const query = input.value.trim();
            empty.hidden = results.length > 0;
            empty.textContent = query ? `Nothing matches “${query}”.` : 'Type to search guides, headings, and the reference.';

            let n = 0;
            // Groups in the order of their best match, so a function name puts Reference first
            const order = [...new Set(results.map((r) => r.type))];
            for (const type of order) {
                const label = groups[type];
                const items = results.filter((r) => r.type === type);
                if (!items.length) continue;
                const heading = document.createElement('p');
                heading.className = 'px-6 pt-3 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500';
                heading.textContent = label;
                list.append(heading);
                for (const item of items) {
                    const i = n++;
                    const a = document.createElement('a');
                    a.href = item.url;
                    a.dataset.index = i;
                    a.className = 'block px-6 py-2.5 ' + (i === selected ? 'bg-sky-50 dark:bg-sky-900/30' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50');
                    const title = document.createElement('p');
                    title.className = (type === 'entry' ? 'font-mono ' : '') + 'text-sm font-medium ' + (i === selected ? 'text-sky-700 dark:text-sky-300' : 'text-slate-900 dark:text-white');
                    title.textContent = item.title;
                    const text = document.createElement('p');
                    text.className = 'mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400';
                    text.textContent = [item.text, item.group].filter(Boolean).join(' · ');
                    a.append(title, text);
                    a.addEventListener('mouseenter', () => { selected = i; highlight(); });
                    list.append(a);
                }
            }
            // Keep the reading order of the groups for the arrow keys
            results = [...list.querySelectorAll('a')].map((a) => ({ url: a.getAttribute('href') }));
        };

        const highlight = () => {
            list.querySelectorAll('a').forEach((a) => {
                const on = Number(a.dataset.index) === selected;
                a.classList.toggle('bg-sky-50', on);
                a.classList.toggle('dark:bg-sky-900/30', on);
                if (on) a.scrollIntoView({ block: 'nearest' });
            });
        };

        const open = async () => {
            dialog.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            input.focus();
            input.select();
            await load();
        };
        const close = () => {
            dialog.classList.add('hidden');
            document.body.style.overflow = '';
        };

        input.addEventListener('input', async () => {
            await load();
            results = rank(input.value);
            selected = 0;
            render();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!results.length) return;
                selected = (selected + (e.key === 'ArrowDown' ? 1 : results.length - 1)) % results.length;
                highlight();
            } else if (e.key === 'Enter' && results[selected]) {
                e.preventDefault();
                window.location.href = results[selected].url;
                close();
            }
        });

        document.querySelectorAll('[data-search-open]').forEach((b) => b.addEventListener('click', open));
        dialog.querySelectorAll('[data-search-close]').forEach((b) => b.addEventListener('click', close));
        window.addEventListener('keydown', (e) => {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
            if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
                e.preventDefault();
                open();
            } else if (e.key === 'Escape' && !dialog.classList.contains('hidden')) {
                close();
            }
        });
    })();
</script>
