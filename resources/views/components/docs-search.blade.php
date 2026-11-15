@props(['product' => null, 'index'])
{{-- The ⌘K search across every docs site and the Cloud guide (resources/js/docs-search.js) --}}
<dialog data-docs-search data-index="{{ $index }}" data-product="{{ $product }}" aria-label="Search the documentation"
        class="m-0 mx-auto mt-[12vh] max-h-[76vh] w-[calc(100%-2rem)] max-w-2xl overflow-hidden rounded-2xl bg-white p-0 text-slate-900 shadow-2xl ring-1 ring-slate-900/5 backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm open:flex open:flex-col dark:bg-slate-800 dark:text-white dark:ring-white/10">
    <div class="relative shrink-0 border-b border-slate-200 px-5 dark:border-slate-700">
        <svg class="pointer-events-none absolute top-1/2 left-5 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
        <input data-docs-search-input type="search" autocomplete="off" spellcheck="false" placeholder="Search the docs"
               role="combobox" aria-expanded="false" aria-controls="docs-search-results" aria-autocomplete="list"
               class="h-14 w-full border-0 bg-transparent pr-14 pl-8 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none dark:text-white dark:placeholder:text-slate-500 [&::-webkit-search-cancel-button]:hidden">
        <button type="button" data-docs-search-close class="absolute inset-y-0 right-5 my-auto h-fit rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-[11px] text-slate-400 hover:text-slate-600 dark:border-slate-600 dark:bg-slate-700 dark:hover:text-slate-200">esc</button>
    </div>
    <div data-docs-search-filters role="group" aria-label="Product" class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 px-5 py-2 dark:border-slate-700" hidden></div>
    <div id="docs-search-results" data-docs-search-results role="listbox" aria-label="Results" class="min-h-0 flex-1 overflow-y-auto py-2"></div>
    <p data-docs-search-status role="status" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Search guides and reference for R, Python, Stata, the CLI, and Framework Cloud.</p>
    <div class="hidden shrink-0 items-center gap-4 border-t border-slate-200 px-5 py-2.5 text-xs text-slate-400 sm:flex dark:border-slate-700">
        <span><kbd class="font-mono">↑↓</kbd> to move</span>
        <span><kbd class="font-mono">↵</kbd> to open</span>
        <span><kbd class="font-mono">⌘↵</kbd> in a new tab</span>
    </div>
</dialog>
@once
    @vite('resources/js/docs-search.js')
@endonce
