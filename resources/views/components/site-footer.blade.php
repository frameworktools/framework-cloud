{{-- Public site footer --}}
    <footer class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 border-t border-neutral-200 px-6 py-8 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
        <div>Framework Cloud</div>
        <nav class="flex flex-wrap items-center gap-5">
            <a href="{{ route('terms') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Privacy</a>
            <a href="{{ route('self-hosting') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Self-hosting</a>
            <a href="{{ route('guide') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Documentation</a>
            <a href="https://framework.table1.org" class="hover:text-neutral-900 dark:hover:text-neutral-100">R package</a>
            <a href="https://github.com/frameworktools/framework-cloud" class="hover:text-neutral-900 dark:hover:text-neutral-100">Source code</a>
            <x-theme-toggle class="sm:hidden" />
        </nav>
    </footer>
