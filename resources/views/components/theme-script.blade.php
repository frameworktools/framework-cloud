{{-- Applies the saved theme before first paint. Choices: light, dark, or system. --}}
<script>
    (() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const read = () => {
            try { return localStorage.getItem('theme') || 'system'; } catch { return 'system'; }
        };
        const apply = () => {
            const choice = read();
            const dark = choice === 'dark' || (choice === 'system' && media.matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = choice;
        };
        window.fwTheme = {
            get: read,
            set(choice) {
                try { localStorage.setItem('theme', choice); } catch {}
                apply();
                window.dispatchEvent(new CustomEvent('fw-theme', { detail: choice }));
            },
        };
        media.addEventListener('change', apply);
        apply();
    })();
</script>
