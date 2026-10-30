{{-- Public site footer --}}
    <footer class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-6 py-8 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
        <div>Framework Cloud</div>
        <nav class="flex flex-wrap items-center gap-5">
            <a href="{{ route('terms') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Privacy</a>
            <a href="{{ route('self-hosting') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Self-hosting</a>
            <a href="{{ route('guide') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Documentation</a>
            <a href="https://framework.table1.org" class="hover:text-slate-900 dark:hover:text-slate-100">R package</a>
            <a href="https://github.com/frameworktools/framework-cloud" class="hover:text-slate-900 dark:hover:text-slate-100">Source code</a>
            <x-theme-toggle class="sm:hidden" />
        </nav>
    </footer>
