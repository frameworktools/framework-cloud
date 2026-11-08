@props(['overlay' => false])
@php
    $docsUrl = fn ($product, $path = '/') => \App\Support\Docs\Products::publicUrl($product, $path);
    $menus = [
        'Cloud' => [
            ['Overview', route('home').'#framework-cloud'],
            ['Dashboard', route('dashboard')],
            ['Projects and blueprints', route('guide', ['page' => 'projects-and-blueprints'])],
            ['Research publishing', route('guide', ['page' => 'publishing'])],
            ['AI skills and sync', route('guide', ['page' => 'skill-sync'])],
        ],
        'R' => [
            ['Overview', $docsUrl('r')],
            ['Installation', $docsUrl('r', '/guides/installation')],
            ['Project setup', $docsUrl('r', '/guides/new-project')],
            ['Data and databases', $docsUrl('r', '/guides/connections')],
            ['Publishing', $docsUrl('r', '/guides/publishing')],
            ['Function reference', $docsUrl('r', '/reference')],
        ],
        'Python' => [
            ['Overview', $docsUrl('py')],
            ['Installation', $docsUrl('py', '/guides/installation')],
            ['Project setup', $docsUrl('py', '/guides/new-project')],
            ['Data and databases', $docsUrl('py', '/guides/connections')],
            ['Publishing', $docsUrl('py', '/guides/publishing')],
            ['Function reference', $docsUrl('py', '/reference')],
        ],
        ...(isset(\App\Support\Docs\Products::listed()['stata']) ? ['Stata' => [
            ['Overview', $docsUrl('stata')],
            ['Installation', $docsUrl('stata', '/guides/installation')],
            ['Projects', $docsUrl('stata', '/guides/projects')],
            ['Data and integrity', $docsUrl('stata', '/guides/data')],
            ['Publishing', $docsUrl('stata', '/guides/publishing')],
            ['Command reference', $docsUrl('stata', '/reference')],
        ]] : []),
        'CLI' => [
            ['Overview', $docsUrl('cli')],
            ['Installation', $docsUrl('cli', '/guides/install')],
            ['Quick start', $docsUrl('cli', '/guides/quickstart')],
            ['Projects', $docsUrl('cli', '/guides/projects')],
            ['Publishing', $docsUrl('cli', '/guides/publishing')],
            ['AI agents', $docsUrl('cli', '/guides/agents')],
            ['Command reference', $docsUrl('cli', '/reference')],
        ],
        'Docs' => [
            ['Framework Cloud', route('guide')],
            ['Projects and blueprints', route('guide', ['page' => 'projects-and-blueprints'])],
            ['Artifacts and collections', route('guide', ['page' => 'publishing'])],
            ['Skill synchronization', route('guide', ['page' => 'skill-sync'])],
        ],
        'Self-hosting' => [
            ['Overview', route('self-hosting')],
            ['Docker installation', route('guide', ['page' => 'install-docker'])],
            ['Server setup', route('guide', ['page' => 'server-setup'])],
            ['Configuration', route('guide', ['page' => 'configuration'])],
            ['Backups and upgrades', route('guide', ['page' => 'maintenance'])],
        ],
    ];
@endphp
<header @class(['relative z-30 text-slate-900 dark:text-slate-50', 'bg-white dark:bg-slate-950' => ! $overlay, 'border-b border-sky-700/40 bg-white/90 dark:border-sky-400/30 dark:bg-slate-950/90' => $overlay])>
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-6 py-4">
        <a href="{{ route('home') }}" class="shrink-0 text-lg font-semibold tracking-tight"><span class="inline-flex items-center gap-2"><img src="/images/logo-80.webp" alt="" width="73" height="80" class="h-10 w-auto"><span>Framework</span></span></a>
        <nav aria-label="Products and documentation" class="hidden items-center gap-1 lg:flex">
            @foreach ($menus as $label => $links)
                <details data-site-menu class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-1 rounded-md px-1.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-800/5 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-sky-500 sm:px-2.5 sm:text-sm dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white [&::-webkit-details-marker]:hidden">{{ $label }}<x-heroicon-o-chevron-down class="size-3 opacity-50 transition group-open:rotate-180" /></summary>
                    <div class="absolute left-0 top-full z-50 mt-2 min-w-52 rounded-xl border border-slate-800/10 bg-white p-2 shadow-xl shadow-slate-900/10 max-sm:fixed max-sm:inset-x-6 max-sm:top-[115px] dark:border-white/15 dark:bg-slate-900 dark:shadow-black/30">
                        @foreach ($links as [$title, $href])
                            <a href="{{ $href }}" class="block rounded-md px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-800/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white">{{ $title }}</a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </nav>
        <div class="flex items-center gap-3 text-sm">
            <x-theme-toggle class="mr-3 max-xl:hidden" />
            {{-- Below desktop width, one menu holds every section --}}
            <details data-site-menu class="group lg:hidden">
                <summary aria-label="Menu" class="flex size-10 cursor-pointer list-none items-center justify-center rounded-md text-slate-700 ring-1 ring-slate-800/15 hover:bg-slate-800/5 focus-visible:outline-2 focus-visible:outline-sky-500 dark:text-slate-200 dark:ring-white/20 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                    <x-heroicon-o-bars-3 class="size-5 group-open:hidden" /><x-heroicon-o-x-mark class="hidden size-5 group-open:block" />
                </summary>
                <div class="absolute inset-x-0 top-full z-50 max-h-[calc(100dvh-5rem)] overflow-y-auto border-b border-slate-800/10 bg-white px-6 pt-4 pb-6 shadow-xl shadow-slate-900/10 dark:border-white/15 dark:bg-slate-950 dark:shadow-black/30">
                    {{-- Account first, so signing in is one tap from the menu --}}
                    <div class="mb-5 flex gap-3 border-b border-slate-800/10 pb-5 dark:border-white/15">
                        @auth
                            <a href="{{ route('dashboard') }}" class="flex-1 rounded-md bg-sky-700 px-4 py-2.5 text-center font-semibold text-white hover:bg-sky-800">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="flex-1 rounded-md bg-white px-4 py-2.5 text-center font-medium text-slate-800 ring-1 ring-slate-800/15 hover:bg-slate-50 dark:bg-white/5 dark:text-slate-100 dark:ring-white/20 dark:hover:bg-white/10">Sign in</a>
                            <a href="{{ route('register') }}" class="flex-1 rounded-md bg-sky-700 px-4 py-2.5 text-center font-semibold text-white hover:bg-sky-800">Sign up</a>
                        @endauth
                    </div>
                    <nav aria-label="Products and documentation" class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                        @foreach ($menus as $label => $links)
                            <div>
                                <p class="mb-1 text-xs font-semibold tracking-wide text-slate-900 uppercase dark:text-white">{{ $label }}</p>
                                @foreach ($links as [$title, $href])
                                    <a href="{{ $href }}" class="block py-1.5 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">{{ $title }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </nav>
                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-slate-800/10 pt-4 dark:border-white/15">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Theme</span>
                        <x-theme-toggle />
                    </div>
                </div>
            </details>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white shadow-sm ring-1 ring-sky-700/40 hover:bg-sky-800 max-lg:hidden">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-md bg-white px-4 py-2 font-medium text-slate-800 shadow-sm ring-1 ring-slate-800/15 hover:bg-slate-50 hover:text-slate-950 max-lg:hidden dark:bg-white/5 dark:text-slate-100 dark:ring-white/20 dark:hover:bg-white/10">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-sky-700 px-4 py-2 font-semibold text-white shadow-sm ring-1 ring-sky-700/40 hover:bg-sky-800 max-lg:hidden">Sign up</a>
            @endauth
        </div>
    </div>
</header>
@once
    @vite('resources/js/landing-hero.js')
@endonce
