{{-- Every code block in main gets a Copy button. The button sits outside the
     pre, so it stays put while the code scrolls sideways. Blocks that bring
     their own button sit inside [data-has-copy] and are skipped.
     Folder trees get a tighter line height, so their box-drawing lines join. --}}
<script>
    (() => {
        document.querySelectorAll('main pre').forEach((pre) => {
            if (/[│├└]/.test(pre.textContent)) pre.style.lineHeight = '1.2';
            if (pre.closest('[data-has-copy]')) return;

            const wrap = document.createElement('div');
            wrap.className = 'relative';
            pre.replaceWith(wrap);
            wrap.append(pre);
            pre.style.paddingRight = '4.75rem';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'absolute top-2.5 right-2.5 rounded-lg bg-slate-700 px-3 py-1 font-sans text-xs font-medium text-slate-100 ring-1 ring-white/10 hover:bg-slate-600 hover:text-white';
            button.textContent = 'Copy';
            wrap.append(button);

            let timer;
            button.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(pre.textContent.replace(/\n+$/, '')); } catch { return; }
                button.textContent = 'Copied';
                clearTimeout(timer);
                timer = setTimeout(() => { button.textContent = 'Copy'; }, 2000);
            });
        });
    })();
</script>
