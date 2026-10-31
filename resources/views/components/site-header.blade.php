@props(['overlay' => false])

{{-- Public site header. With overlay, the hero's background shows through it. --}}
<header @class([
    'relative border-b border-sky-900/10 text-slate-900 dark:border-white/10 dark:text-slate-50',
    'bg-white dark:bg-slate-950' => ! $overlay,
])>
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="{{ route('home') }}" class="font-semibold tracking-tight"><span class="inline-flex items-center gap-2"><img src="/images/logo.png" alt="" class="h-9 w-auto"><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span></a>
        <nav class="flex items-center gap-4 text-sm">
            <x-theme-toggle class="max-sm:hidden" />
            <a href="{{ route('self-hosting') }}" class="text-slate-600 hover:text-slate-900 max-md:hidden dark:text-slate-300 dark:hover:text-white">Self-hosting</a>
            <a href="{{ route('guide') }}" class="text-slate-600 hover:text-slate-900 max-sm:hidden dark:text-slate-300 dark:hover:text-white">Docs</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">Sign up</a>
            @endauth
        </nav>
    </div>
</header>
