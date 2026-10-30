@props(['overlay' => false])

{{-- Public site header. It sits on the dark hero, so it always uses dark styles.
     With overlay, the hero's background shows through it. --}}
<header @class(['dark relative border-b border-white/10 text-stone-50', 'bg-neutral-950' => ! $overlay])>
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <a href="{{ route('home') }}" class="font-semibold tracking-tight">Framework <span class="text-orange-500">Cloud</span></a>
        <nav class="flex items-center gap-4 text-sm">
            <x-theme-toggle class="max-sm:hidden" />
            <a href="{{ route('self-hosting') }}" class="text-neutral-300 hover:text-white max-md:hidden">Self-hosting</a>
            <a href="{{ route('guide') }}" class="text-neutral-300 hover:text-white max-sm:hidden">Docs</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-neutral-300 hover:text-white">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Sign up</a>
            @endauth
        </nav>
    </div>
</header>
