{{-- Opens the ⌘K docs search (x-docs-search) --}}
<button type="button" data-docs-search-open aria-label="Search the docs" {{ $attributes->merge(['class' => 'group flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 hover:border-slate-300 hover:bg-slate-100 sm:px-3 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:bg-slate-700']) }}>
    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
    <span class="hidden sm:inline">Search</span>
    <kbd data-docs-search-shortcut class="hidden rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[11px] leading-none text-slate-400 sm:inline-block dark:border-slate-600 dark:bg-slate-700">⌘K</kbd>
</button>
