{{-- Every code block in main becomes a card: a title, a Copy button, and the
     code below them (docs.css .code-block). The title is the fence's
     title="…" (data-title on the pre), else a name for its language. R and
     Python tab cards show their tabs in place of a title.
     Folder trees get a tighter line height, so their box-drawing lines join. --}}
<script>
    (() => {
        const names = {
            r: 'R', python: 'Python', py: 'Python', stata: 'Stata', do: 'Stata', ado: 'Stata',
            sh: 'Terminal', bash: 'Terminal', shell: 'Terminal', shellscript: 'Terminal', zsh: 'Terminal', console: 'Terminal',
            yaml: 'YAML', yml: 'YAML', json: 'JSON', toml: 'TOML', html: 'HTML', css: 'CSS', js: 'JavaScript', javascript: 'JavaScript',
            markdown: 'Markdown', md: 'Markdown', qmd: 'Quarto', dockerfile: 'Dockerfile', ini: 'INI', sql: 'SQL', php: 'PHP',
        };
        const language = (pre) => {
            const match = `${pre.className} ${pre.querySelector('code')?.className ?? ''}`.match(/language-([\w-]+)/);
            return match ? match[1].toLowerCase() : '';
        };
        const tree = (text) => /[│├└]/.test(text);
        // Shell-colored blocks of only KEY=value lines are .env settings, not commands
        const env = (text) => text.trim() !== '' && text.split('\n').every((line) => /^\s*(?:#.*|[A-Z][A-Z0-9_]*=.*)?$/.test(line));
        const title = (pre) => {
            const lang = language(pre);
            if (pre.dataset.title) return pre.dataset.title;
            if (lang === 'env' || lang === 'dotenv' || (names[lang] === 'Terminal' && env(pre.textContent))) return '.env';
            return names[lang] || (tree(pre.textContent) ? 'Folder structure' : 'Text');
        };

        // Copy takes the code that is showing: the one pre, or the open tab's
        const copyButton = (card, label) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'code-block-copy';
            button.textContent = 'Copy';
            button.setAttribute('aria-label', `Copy ${label}`);
            let timer;
            button.addEventListener('click', async () => {
                const pre = [...card.querySelectorAll('pre')].find((p) => !p.closest('[hidden]'));
                try { await navigator.clipboard.writeText(pre.textContent.replace(/\n+$/, '')); } catch { return; }
                button.textContent = 'Copied';
                clearTimeout(timer);
                timer = setTimeout(() => { button.textContent = 'Copy'; }, 2000);
            });
            return button;
        };

        // Tab cards come built (CloudGuide::languageTabs) and only need Copy
        document.querySelectorAll('main [data-code-tabs]').forEach((card) => {
            card.querySelector('.code-block-head').append(copyButton(card, 'code'));
        });

        document.querySelectorAll('main pre').forEach((pre) => {
            if (tree(pre.textContent)) pre.style.lineHeight = '1.2';
            if (pre.closest('.code-block')) return;

            const card = document.createElement('figure');
            card.className = 'code-block';
            const head = document.createElement('div');
            head.className = 'code-block-head';
            const caption = document.createElement('figcaption');
            caption.textContent = title(pre);
            head.append(caption, copyButton(card, caption.textContent));
            pre.replaceWith(card);
            card.append(head, pre);
        });
    })();
</script>
