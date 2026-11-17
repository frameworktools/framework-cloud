@php
    // Step 04's picture: step 03's R model going into three hosts, then the published post.
    // Each host plays three scenes that fade into each other: the insert step in that host's
    // editor, the link or image going in, and the post readers see. "/framework" is typed;
    // links and code are pasted, as people do. The editors copy the real
    // controls: academic.page's "/" menu and Framework.pub embed dialog, the WordPress plugin's
    // Framework artifact block, and the Share dialog's Copy image button.
    // Academic.page and WordPress draw the live embed in the post's type. Substack takes the
    // copied image, which keeps its own look.
    // Estimates come from step 03's data (resources/examples/landing/publish.json). The intervals
    // come from the same regression's published embed document (penguin-model-embed.json, from
    // Stata), matched on the estimate. Panels and scenes share grid cells, so nothing changes height.
    // Without JavaScript, each host shows its published post.
    $model = json_decode(file_get_contents(resource_path('examples/landing/publish.json')), true)['r'];
    $embed = json_decode(file_get_contents(resource_path('examples/landing/penguin-model-embed.json')), true);
    $table = collect($embed['data']['tables'])->firstWhere('role', 'coefficients');
    $at = array_flip(array_column($table['columns'], 'name'));
    $intervals = [];
    foreach ($table['rows'] as $row) {
        $intervals[number_format($row[$at['estimate']], 6)] = [$row[$at['conf_low']], $row[$at['conf_high']]];
    }
    $rows = array_map(fn ($row) => [...$row, ...$intervals[number_format($row[1], 6)]], $model['rows']);
    $observations = collect($embed['data']['metrics'])->firstWhere('name', 'nobs')['value'];
    $url = 'https://framework.pub/@examples/'.\App\Console\Commands\HomepageExampleCommand::PROJECTS['r'].'/'.\App\Console\Commands\HomepageExampleCommand::SLUG;
    $meta = "Linear regression · Response: body_mass_g · {$observations} observations";
    $credit = 'shrink-0 text-slate-500 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-400 dark:decoration-slate-600';

    // academic.page's Framework button icon, from pequod's flux/icon/framework.blade.php
    $frameworkIcon = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M2.5 5h15M5 3v14M15 3v14M3.5 17h3M13.5 17h3M5 13.5h10"/><path d="M7.6 13.5V8.2M10 13.5v-3M12.4 13.5V7.8" stroke-width="1.7" stroke-linecap="butt"/></svg>';
    // Each host: its tab and its steps
    $hosts = [
        'wordpress' => ['WordPress', ['Type /framework', 'Paste the link', 'Published']],
        'generic' => ['Drupal / Hosted CMS', ['Load the script', 'Add the tag', 'Published']],
        'academic' => ['Academic.page', ['Click the icon or type /framework', 'Paste the link', 'Published']],
        'substack' => ['Substack', ['Copy image', 'Paste', 'Published']],
    ];
    $first = array_key_first($hosts);
    $panel = fn (string $key) => $key === $first ? '' : 'invisible';
    // Scenes: the last (the post) shows until the script starts the loop
    $scene = fn (int $index) => '[grid-area:1/1] min-w-0 transition-opacity duration-300 motion-reduce:transition-none '.($index === 2 ? 'opacity-100' : 'opacity-0');
    $caret = '<span class="ml-px inline-block h-[1.1em] w-px translate-y-[0.15em] animate-pulse bg-current" aria-hidden="true"></span>';
    $pad = 'px-5 py-5 sm:px-8 sm:py-6';
    $wpBlue = 'bg-[#3858e9]';
    // academic.page's toolbar; its Framework button's icon (pequod's flux/icon/framework.blade.php) is above
    $tool = 'flex h-7 min-w-7 shrink-0 items-center justify-center rounded';
    $toolRule = 'mx-1 h-4 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600';
    $title = 'Flipper length and body mass';
    $asset = 'https://frameworktools.github.io/framework-embed/framework-embed';
    // The post continues below the insert, so the editor scenes fill the frame
    $next = 'Gentoo penguins are about 267 grams heavier than Adélie penguins with the same flipper length. Chinstrap penguins are about 207 grams lighter.';
    $lede = 'Within each species, body mass rises about 41 grams with each millimeter of flipper length.';
@endphp
<div data-share-demo class="min-w-0">
    <figure class="min-w-0 overflow-hidden rounded-xl bg-white shadow-panel-deep ring-1 ring-slate-400/20 dark:bg-slate-900 dark:shadow-panel-deep-dark">
        {{-- The header follows step 03's: tabs on a dark bar. The steps sit on a grey row under it. --}}
        <div class="bg-[#0c1526] text-slate-100">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-3 py-2">
                <div role="tablist" aria-label="Host site" class="flex shrink-0 flex-wrap gap-1 max-md:min-w-0 max-md:shrink">
                    @foreach ($hosts as $key => [$label])
                        <button type="button" role="tab" id="share-tab-{{ $key }}" aria-controls="share-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-share-tab="{{ $key }}"
                            class="shrink-0 rounded-md px-3 py-1.5 text-xs whitespace-nowrap text-slate-400 hover:text-white focus-visible:outline-2 focus-visible:outline-sky-500 aria-selected:bg-white/10 aria-selected:text-white">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            {{-- The shown host's steps, as breadcrumbs: each jumps to its scene and stops the loop --}}
            @foreach ($hosts as $key => [$label, $steps])
                <ol data-share-host="{{ $key }}" @unless ($loop->first) hidden @endunless aria-label="Steps on {{ $label }}" class="flex min-w-0 items-center overflow-x-auto border-b border-slate-800/10 bg-slate-50 px-3 py-1 text-xs dark:border-white/10 dark:bg-slate-800">
                    @foreach ($steps as $index => $step)
                        <li class="flex shrink-0 items-center">
                            @if ($index > 0)<x-heroicon-m-chevron-right class="size-3.5 shrink-0 text-slate-400 dark:text-slate-500" aria-hidden="true" />@endif
                            <button type="button" data-share-step="{{ $index }}" aria-pressed="{{ $index === 2 ? 'true' : 'false' }}"
                                class="rounded px-1.5 py-1 whitespace-nowrap text-slate-500 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-sky-600 aria-pressed:font-medium aria-pressed:text-slate-900 dark:text-slate-400 dark:hover:text-white dark:aria-pressed:text-white">{{ $step }}</button>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        </div>

        <div class="grid">
            {{-- Academic.page --}}
            <div role="tabpanel" id="share-panel-academic" aria-labelledby="share-tab-academic" data-share-panel="academic" inert class="{{ $panel('academic') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. The editor: its toolbar's Framework button, or "/framework" --}}
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} font-sans text-zinc-800 dark:text-zinc-200">
                    <div class="flex items-center gap-0.5 overflow-hidden border-b border-neutral-100 bg-neutral-50 pb-3 px-2 py-1.5 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-300">
                        <span class="{{ $tool }} gap-0.5 px-1.5 text-sm font-bold">H<x-heroicon-m-chevron-down class="size-3" /></span>
                        <span class="{{ $toolRule }}"></span>
                        <span class="{{ $tool }}"><x-heroicon-m-bold class="size-4" /></span>
                        <span class="{{ $tool }}"><x-heroicon-m-italic class="size-4" /></span>
                        <span class="{{ $tool }} max-sm:hidden"><x-heroicon-m-link class="size-4" /></span>
                        <span class="{{ $tool }} max-sm:hidden"><x-heroicon-m-strikethrough class="size-4" /></span>
                        <span class="{{ $toolRule }}"></span>
                        <span class="{{ $tool }}"><x-heroicon-m-list-bullet class="size-4" /></span>
                        <span class="{{ $tool }}"><x-heroicon-m-numbered-list class="size-4" /></span>
                        <span class="{{ $tool }} font-serif text-base font-bold max-sm:hidden">“</span>
                        <span class="{{ $tool }} max-sm:hidden"><x-heroicon-m-code-bracket class="size-4" /></span>
                        <span class="{{ $tool }} text-base max-sm:hidden">Σ</span>
                        <span class="{{ $tool }} gap-0.5 px-1.5 max-sm:hidden"><x-heroicon-m-table-cells class="size-4" /><x-heroicon-m-chevron-down class="size-3" /></span>
                        <span class="{{ $toolRule }}"></span>
                        <span class="{{ $tool }}"><x-heroicon-m-photo class="size-4" /></span>
                        <span class="{{ $tool }} relative bg-sky-100 text-sky-800 ring-1 ring-sky-300 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-700">{!! $frameworkIcon !!}<x-heroicon-s-cursor-arrow-rays class="absolute top-3.5 left-3.5 size-5 text-zinc-900 drop-shadow dark:text-white" /></span>
                        <span class="{{ $tool }}"><x-heroicon-m-play-circle class="size-4" /></span>
                    </div>
                    <div class="px-5 py-4 sm:px-8">
                        <p class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $lede }}</p>
                        <p class="mt-3 text-sm"><span data-type="/framework">/framework</span>{!! $caret !!}</p>
                        <div class="mt-2 w-72 max-w-full rounded-lg bg-white p-1 shadow-lg ring-1 ring-zinc-800/10 dark:bg-zinc-800 dark:ring-white/10">
                            <div class="flex items-center gap-3 rounded-md bg-zinc-100 px-2.5 py-2 dark:bg-white/10">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-white text-zinc-600 ring-1 ring-zinc-800/10 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/10">{!! $frameworkIcon !!}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-white">Framework.pub embed</span>
                                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">A model, table, test, or chart from Framework.pub</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- 2. The Framework.pub embed dialog --}}
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} flex items-center justify-center bg-zinc-900/5 p-5 font-sans dark:bg-black/30">
                    <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl ring-1 ring-zinc-800/10 dark:bg-zinc-800 dark:ring-white/10">
                        <p class="font-semibold text-zinc-900 dark:text-white">Framework.pub Embed</p>
                        <p class="mt-4 text-sm font-medium text-zinc-800 dark:text-zinc-200">Artifact address</p>
                        <p class="mt-2 truncate rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-800 dark:border-zinc-600 dark:text-zinc-100"><span data-paste>{{ $url }}</span>{!! $caret !!}</p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Paste the link to a publicly available Framework.pub artifact.</p>
                        <p class="mt-4 flex justify-end gap-2 text-sm font-medium">
                            <span class="rounded-lg px-3 py-1.5 text-zinc-600 dark:text-zinc-300">Cancel</span>
                            <span class="rounded-lg bg-zinc-900 px-3 py-1.5 text-white dark:bg-white dark:text-zinc-900">Add</span>
                        </p>
                    </div>
                </div>
                {{-- 3. The post --}}
                <div data-share-scene="2" class="{{ $scene(2) }} {{ $pad }} font-serif text-slate-800 dark:text-slate-200">
                    <p class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $title }}</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                    <div class="mt-4">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $meta }}</p>
                        <div class="mt-2 overflow-x-auto" tabindex="0" role="region" aria-label="Coefficients on Academic.page"><x-landing-shared.table :rows="$rows" /></div>
                        <div class="mt-3 flex items-baseline justify-between gap-4 border-t border-slate-800/10 pt-2.5 text-xs dark:border-white/10">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $embed['title'] }}</span>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $credit }}">Framework</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WordPress --}}
            <div role="tabpanel" id="share-panel-wordpress" aria-labelledby="share-tab-wordpress" data-share-panel="wordpress" class="{{ $panel('wordpress') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. The block editor's "/" inserter --}}
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} font-sans text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-2 border-b border-slate-800/10 px-3 py-2 dark:border-white/10">
                        <span class="flex size-7 items-center justify-center rounded-sm bg-slate-900 text-xs font-bold text-white dark:bg-black">W</span>
                        <span class="flex size-7 items-center justify-center rounded-sm {{ $wpBlue }} text-white"><x-heroicon-m-plus class="size-4" /></span>
                        <span class="ml-auto rounded-sm {{ $wpBlue }} px-3 py-1 text-xs font-medium text-white">Publish</span>
                    </div>
                    <div class="px-5 py-4 sm:px-10">
                        <p class="text-xl font-medium tracking-tight text-slate-950 dark:text-white">{{ $title }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                        <p class="mt-3 text-sm"><span data-type="/framework">/framework</span>{!! $caret !!}</p>
                        <div class="mt-2 w-64 max-w-full rounded-sm bg-white py-1 shadow-lg ring-1 ring-slate-800/20 dark:bg-slate-800 dark:ring-white/15">
                            <div class="flex items-center gap-2.5 px-3 py-2 text-sm {{ $wpBlue }} text-white">
                                <x-heroicon-o-chart-bar class="size-5" /> Framework artifact
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $next }}</p>
                        <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">Type / to choose a block</p>
                    </div>
                </div>
                {{-- 2. The Framework artifact block's placeholder --}}
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} font-sans text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-2 border-b border-slate-800/10 px-3 py-2 dark:border-white/10">
                        <span class="flex size-7 items-center justify-center rounded-sm bg-slate-900 text-xs font-bold text-white dark:bg-black">W</span>
                        <span class="flex size-7 items-center justify-center rounded-sm {{ $wpBlue }} text-white"><x-heroicon-m-plus class="size-4" /></span>
                        <span class="ml-auto rounded-sm {{ $wpBlue }} px-3 py-1 text-xs font-medium text-white">Publish</span>
                    </div>
                    <div class="px-5 py-4 sm:px-10">
                        <p class="text-xl font-medium tracking-tight text-slate-950 dark:text-white">{{ $title }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                        <div class="mt-3 rounded-sm p-4 ring-1 ring-slate-900 dark:ring-white/60">
                            <p class="flex items-center gap-2 text-sm font-semibold"><x-heroicon-o-chart-bar class="size-5" /> Framework artifact</p>
                            <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400">Paste the URL of a public or unlisted artifact. The block stores a copy of that version with the post.</p>
                            <div class="mt-3 flex gap-2">
                                <p class="min-w-0 flex-1 truncate rounded-sm border border-slate-500 px-2.5 py-1.5 text-sm"><span data-paste>{{ $url }}</span>{!! $caret !!}</p>
                                <span class="shrink-0 rounded-sm {{ $wpBlue }} px-3 py-1.5 text-sm font-medium text-white">Embed</span>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600 max-sm:hidden dark:text-slate-400">{{ $next }}</p>
                    </div>
                </div>
                {{-- 3. The post --}}
                <div data-share-scene="2" class="{{ $scene(2) }} {{ $pad }} font-sans text-slate-800 dark:text-slate-200">
                    <p class="text-xs text-slate-500 dark:text-slate-400">November 16, 2026 · Field Notes</p>
                    <p class="mt-1 text-xl font-medium tracking-tight text-slate-950 dark:text-white">{{ $title }}</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                    <div class="mt-4">
                        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Coefficients on WordPress"><x-landing-shared.table :rows="$rows" /></div>
                        <div class="mt-3 flex items-baseline justify-between gap-4 text-xs">
                            <span class="text-slate-500 dark:text-slate-400"><span class="font-medium text-slate-900 dark:text-white">{{ $embed['title'] }}</span> · {{ $observations }} observations</span>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $credit }}">Framework</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Substack --}}
            <div role="tabpanel" id="share-panel-substack" aria-labelledby="share-tab-substack" data-share-panel="substack" inert class="{{ $panel('substack') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. Framework's Share dialog: Copy image --}}
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} flex items-center justify-center bg-slate-900/5 p-5 font-sans dark:bg-black/30">
                    <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl ring-1 ring-slate-800/10 dark:bg-slate-800 dark:ring-white/10">
                        <p class="font-semibold text-slate-900 dark:text-white">Share {{ $embed['title'] }}</p>
                        {{-- The image that will be copied --}}
                        <div class="mt-3 overflow-hidden rounded-md bg-white px-3 py-2 text-slate-800 ring-1 ring-slate-800/10">
                            <p class="text-[10px] text-slate-500">{{ $meta }}</p>
                            <x-landing-shared.table :rows="$rows" :image="true" class="mt-0.5 text-xs [&_td]:py-1 [&_td]:pl-2.5 [&_td]:text-[11px] [&_th]:py-1" />
                        </div>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-white">Image.</span> Copy or download a PNG for Substack, email, and slides.</p>
                        <p class="mt-3 flex justify-end gap-2 text-sm font-medium">
                            <span class="rounded-md bg-sky-700 px-3 py-1.5 text-white" data-swap="Copied">Copy image</span>
                            <span class="rounded-md bg-slate-800/5 px-3 py-1.5 text-slate-700 dark:bg-white/10 dark:text-slate-200">Download PNG</span>
                        </p>
                    </div>
                </div>
                {{-- 2. Substack's editor: the image pasted in --}}
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} {{ $pad }} font-serif text-slate-800 dark:text-slate-200">
                    <p class="text-lg font-bold text-slate-950 dark:text-white">{{ $title }}</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}{!! $caret !!}</p>
                    <div class="relative mt-4">
                        <span data-hide-on-reveal class="absolute top-0 left-0 rounded-md bg-slate-900 px-2 py-1 font-sans text-xs font-medium text-white dark:bg-white dark:text-slate-900">⌘V</span>
                        <div data-reveal class="rounded-sm bg-white px-4 py-3 font-sans text-slate-800 ring-2 ring-[#ff6719] transition-opacity duration-300">
                            <p class="text-[11px] text-slate-500">{{ $meta }}</p>
                            <x-landing-shared.table :rows="$rows" :image="true" class="mt-1" />
                        </div>
                    </div>
                </div>
                {{-- 3. The newsletter --}}
                <div data-share-scene="2" class="{{ $scene(2) }} {{ $pad }} font-serif text-slate-800 dark:text-slate-200">
                    <p class="text-center text-lg leading-snug font-bold text-slate-950 dark:text-white">{{ $title }}</p>
                    <p class="mt-1 text-center font-sans text-xs text-slate-500 dark:text-slate-400">Ada Example · Nov 16, 2026</p>
                    <div class="mx-auto mt-4 max-w-xl">
                        <div role="region" tabindex="0" aria-label="Copied image of the model, pasted into the newsletter" class="overflow-x-auto rounded-sm bg-white px-4 py-3 font-sans text-slate-800 ring-1 ring-slate-800/10 dark:ring-white/10">
                            <p class="text-[11px] text-slate-500">{{ $meta }}</p>
                            <x-landing-shared.table :rows="$rows" :image="true" class="mt-1" />
                            <p class="mt-2 text-[11px] font-semibold text-slate-900">{{ $embed['title'] }}</p>
                        </div>
                        <p class="mt-2 text-center font-sans text-xs text-slate-500 dark:text-slate-400">Full model on <a href="{{ $url }}" target="_blank" rel="noopener" class="underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:decoration-slate-600">Framework</a></p>
                    </div>
                </div>
            </div>
            {{-- Drupal or a hosted CMS: the stylesheet and script once, then the tag in an HTML block --}}
            <div role="tabpanel" id="share-panel-generic" aria-labelledby="share-tab-generic" data-share-panel="generic" inert class="{{ $panel('generic') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. The site's header code (a theme template, or a hosted CMS's code injection) loads the stylesheet and script --}}
                @php $load = "<link rel=\"stylesheet\"\n        href=\"{$asset}.css\">\n  <script defer\n          src=\"{$asset}.iife.js\"></script>"; @endphp
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} bg-[#0c1526] px-5 py-4 font-mono text-[13px] leading-6 text-slate-300">
                    <pre class="whitespace-pre-wrap [overflow-wrap:anywhere]"><code><span class="text-slate-500">&lt;head&gt;</span>
  <span class="text-slate-500">…</span>
  <span class="text-sky-300" data-paste>{{ $load }}</span>{!! $caret !!}
<span class="text-slate-500">&lt;/head&gt;</span></code></pre>
                </div>
                {{-- 2. The post editor: the tag goes in an HTML block --}}
                @php $tag = "<framework-artifact\n  src=\"{$url}\">\n</framework-artifact>"; @endphp
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} font-sans text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-3 border-b border-slate-800/10 px-4 py-2 text-xs dark:border-white/10">
                        <x-heroicon-m-chevron-left class="size-4 text-slate-400" />
                        <span class="text-slate-500 dark:text-slate-400">Posts</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600 dark:bg-white/10 dark:text-slate-300">Draft</span>
                        <span class="ml-auto rounded-md bg-slate-900 px-3 py-1 font-medium text-white dark:bg-white dark:text-slate-900">Publish</span>
                    </div>
                    <div class="px-5 py-4 sm:px-10">
                        <p class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                        <div class="mt-3 rounded-lg ring-1 ring-sky-600/60">
                            <p class="border-b border-slate-800/10 px-3 py-1 text-[11px] font-medium text-slate-500 dark:border-white/10 dark:text-slate-400">HTML</p>
                            <pre class="px-3 py-2 font-mono text-[12px] leading-5 whitespace-pre-wrap text-sky-800 [overflow-wrap:anywhere] dark:text-sky-300"><code><span data-paste>{{ $tag }}</span>{!! $caret !!}</code></pre>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600 max-sm:hidden dark:text-slate-400">{{ $next }}</p>
                    </div>
                </div>
                {{-- 3. The page --}}
                <div data-share-scene="2" class="{{ $scene(2) }} {{ $pad }} font-sans text-slate-800 dark:text-slate-200">
                    <p class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $lede }}</p>
                    <div class="mt-4">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $meta }}</p>
                        <div class="mt-2 overflow-x-auto" tabindex="0" role="region" aria-label="Coefficients on a generic site"><x-landing-shared.table :rows="$rows" /></div>
                        <div class="mt-3 flex items-baseline justify-between gap-4 border-t border-slate-800/10 pt-2.5 text-xs dark:border-white/10">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $embed['title'] }}</span>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $credit }}">Framework</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </figure>
</div>
