{{-- Light / system / dark switch for Blade pages; matches ThemeToggle.vue --}}
<div {{ $attributes->merge(['class' => 'fw-theme-toggle inline-flex items-center gap-0.5 rounded-full border border-slate-200 p-0.5 dark:border-slate-700']) }} role="radiogroup" aria-label="Color theme">
    @foreach (['light' => 'Light', 'system' => 'System', 'dark' => 'Dark'] as $value => $label)
        <button type="button" role="radio" data-theme-choice="{{ $value }}" title="{{ $label }}" aria-label="{{ $label }}"
            class="flex size-6 items-center justify-center rounded-full text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
            onclick="window.fwTheme.set('{{ $value }}')">
            @if ($value === 'light')
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            @elseif ($value === 'system')
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
            @else
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/></svg>
            @endif
        </button>
    @endforeach
</div>
@once
    <script>
        (() => {
            const mark = () => {
                const choice = window.fwTheme.get();
                document.querySelectorAll('[data-theme-choice]').forEach((button) => {
                    const on = button.dataset.themeChoice === choice;
                    button.setAttribute('aria-checked', on);
                    button.classList.toggle('bg-slate-200', on);
                    button.classList.toggle('dark:bg-slate-700', on);
                    button.classList.toggle('text-slate-900', on);
                    button.classList.toggle('dark:text-slate-100', on);
                });
            };
            window.addEventListener('fw-theme', mark);
            document.addEventListener('DOMContentLoaded', mark);
            mark();
        })();
    </script>
@endonce
