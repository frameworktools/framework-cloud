@php
    // Short notes on the features the larger sections leave out.
    $tools = [
        [
            'icon' => 'circle-stack',
            'title' => 'Database connections',
            'body' => 'Name each connection once in settings/connections.yml. Query it by name from any script or notebook.',
            'code' => "measurements <- db_query(\n  \"SELECT * FROM measurements\",\n  \"study_db\"\n)",
            'guide' => 'connections',
        ],
        [
            'icon' => 'key',
            'title' => 'Secure credential management',
            'body' => 'scaffold() loads .env into the session. Settings read secrets with env(). The .gitignore template keeps .env out of Git.',
            'code' => "# settings/connections.yml\nstudy_db:\n  host: env(\"DB_HOST\")\n  password: env(\"DB_PASSWORD\")",
            'guide' => 'scaffold',
        ],
        [
            'icon' => 'arrow-path',
            'title' => 'Result caching',
            'body' => 'Keep slow results between runs. Framework recomputes a value when it expires or when you refresh it.',
            'code' => "fit <- cache_remember(\n  \"bootstrap-fit\",\n  run_bootstrap(penguins),\n  expire_after = \"1 day\"\n)",
            'guide' => 'caching',
        ],
    ];
    // Link each documentation site the header lists, joined as R, Python, Stata, or the command line.
    $docNames = ['r' => 'R', 'py' => 'Python', 'stata' => 'Stata', 'cli' => 'the command line'];
    $docLinks = collect($docNames)->only(array_keys(\App\Support\Docs\Products::listed()))
        ->map(fn (string $name, string $product) => '<a href="'.e(\App\Support\Docs\Products::publicUrl($product)).'" class="text-slate-800 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-200 dark:decoration-slate-600">'.e($name).'</a>')
        ->values();
    $docList = $docLinks->count() > 1 ? $docLinks->slice(0, -1)->implode(', ').($docLinks->count() > 2 ? ',' : '').' or '.$docLinks->last() : $docLinks->implode('');
@endphp
<section id="tools" class="border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight text-balance">Connections, credentials, caching, and more</h2>
        <p class="mt-4 leading-relaxed text-balance text-slate-600 dark:text-slate-400">Framework also manages database connections, project secrets, slow computations, and more. Read the documentation for {!! $docList !!} to see everything Framework can do.</p>
    </div>
    <div class="mt-8 grid gap-4 md:grid-cols-3">
        @foreach ($tools as $tool)
            <x-landing-frame class="flex flex-col p-5">
                <x-dynamic-component :component="'heroicon-o-'.$tool['icon']" class="mb-3 size-5 text-slate-500 dark:text-slate-400" />
                <h3 class="text-sm font-semibold">{{ $tool['title'] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $tool['body'] }}</p>
                <pre class="mt-4 overflow-x-auto rounded-lg bg-[#0c1526] px-3 py-2.5 font-mono text-xs leading-[1.6] text-slate-100 ring-1 ring-slate-400/20" style="color-scheme: dark"><code>{!! \App\Support\ExampleCode::highlight($tool['code'], false) !!}</code></pre>
                <div class="mt-auto flex gap-4 pt-4 text-sm">
                    @foreach (['r' => 'R', 'py' => 'Python'] as $language => $label)
                        <a href="{{ \App\Support\Docs\Products::publicUrl($language, '/guides/'.$tool['guide']) }}" class="text-slate-700 underline decoration-slate-300 underline-offset-4 dark:text-slate-300 dark:decoration-slate-600">{{ $label }} &rarr;</a>
                    @endforeach
                </div>
            </x-landing-frame>
        @endforeach
    </div>
</section>
