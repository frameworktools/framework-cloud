@php
    // Closing actions: a Cloud account, or the documentation for each listed product.
    $docNames = ['r' => 'R', 'py' => 'Python', 'stata' => 'Stata', 'cli' => 'CLI'];
    $docs = collect($docNames)->only(array_keys(\App\Support\Docs\Products::listed()));
@endphp
<section id="start" class="border-t border-slate-800/10 py-14 text-center sm:py-20 dark:border-white/10">
    <h2 class="text-3xl font-semibold tracking-tight">Get started with Framework</h2>
    <div class="mt-8 flex flex-col items-center gap-4">
        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="rounded-md bg-sky-700 px-5 py-2.5 font-medium text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">Create a Framework Cloud account</a>
        <p class="text-sm text-slate-500 dark:text-slate-400">or read the documentation</p>
        <nav aria-label="Framework documentation" class="flex flex-wrap justify-center gap-2">
            @foreach ($docs as $product => $name)
                <a href="{{ \App\Support\Docs\Products::publicUrl($product) }}" class="rounded-md border border-slate-800/10 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-950 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5 dark:hover:text-white">{{ $name }}</a>
            @endforeach
        </nav>
    </div>
</section>
