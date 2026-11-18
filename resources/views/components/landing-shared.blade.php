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
        'bluesky' => ['Bluesky', ['Copy image', 'Paste', 'Posted']],
    ];
    $first = array_key_first($hosts);
    $panel = fn (string $key) => $key === $first ? '' : 'invisible';
    // Scenes: the last (the post) shows until the script starts the loop
    $scene = fn (int $index) => '[grid-area:1/1] min-w-0 transition-opacity duration-300 motion-reduce:transition-none '.($index === 2 ? 'opacity-100' : 'opacity-0');
    $caret = '<span class="ml-px inline-block h-[1.1em] w-px translate-y-[0.15em] animate-pulse bg-current" aria-hidden="true"></span>';
    $pad = 'px-5 py-5 sm:px-8 sm:py-6';
    $wpBlue = 'bg-[#3858e9]';
    // Substack's post and editor type and the like/comment/restack pills (measured from live posts)
    $ssSans = "font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Display',system-ui,sans-serif]";
    $ssSerif = "font-[Spectral,Lora,Georgia,serif]";
    $ssPill = 'flex h-7 items-center gap-1 rounded-full border border-[#e6e6e6] px-2.5';
    $subtitle = 'Body mass, flipper length, and species in the Palmer penguins';
    // The PNG Framework's Copy image makes for this model (resources/js/embed-image.js)
    $image = ['src' => asset('images/landing/penguin-model-image.webp'), 'width' => 1248, 'height' => 852,
        'alt' => 'Penguin body mass model: estimates, standard errors, statistics, p-values, and 95% confidence intervals for the intercept, flipper length, and species.'];
    $skyBlue = 'bg-[#1185fe]';
    // bsky.app's type and colors, light and dim (from a live post page)
    $bsSans = "font-[InterVariable,Inter,-apple-system,system-ui,sans-serif]";
    $bsMuted = 'text-[#42576c] dark:text-[#aebbc9]';
    $bsRule = 'border-[#d4dbe2] dark:border-[#2e4052]';
    $avatar = 'flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-200 text-xs font-semibold text-sky-900';
    // Bluesky shortens a link in a post to its start
    $postLink = 'framework.pub/@examples/penguin-st…';
    $postText = 'Within each species, penguin body mass rises about 41 grams with each millimeter of flipper length.';
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
                    <div class="flex items-center gap-0.5 overflow-hidden border-b border-neutral-100 bg-neutral-50 px-2 py-1.5 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-300">
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
                        <span class="{{ $tool }} relative bg-sky-100 text-sky-800 ring-1 ring-sky-300 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-700">{!! $frameworkIcon !!}</span>
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

            {{-- Substack, after its real editor and post pages: always light, as Substack is. The pasted
                 image is the actual PNG Framework's Copy image makes for this model. The editor and the
                 post are taller than the frame, so each scrolls to the image once it is in view. --}}
            <div role="tabpanel" id="share-panel-substack" aria-labelledby="share-tab-substack" data-share-panel="substack" data-share-durations="[1800,2600,3600]" inert class="{{ $panel('substack') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. Framework's Share dialog: Copy image --}}
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} flex items-center justify-center bg-slate-900/5 p-5 font-sans dark:bg-black/30">
                    <x-landing-shared.copy :image="$image" :title="$embed['title']" />
                </div>
                {{-- 2. Substack's post editor: the image pasted in and selected --}}
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} relative overflow-hidden bg-white {{ $ssSans }} text-[#363737]">
                    <div data-scroll="900" class="absolute inset-x-0 top-0">
                        <div class="flex items-center gap-2 px-3 pt-2.5 text-xs">
                            <x-heroicon-m-chevron-left class="size-4 text-[#363737]" />
                            <span class="flex items-center gap-1.5 rounded border border-[#e6e6e6] px-1.5 py-0.5 text-[11px]"><span class="size-1.5 rounded-full bg-[#00c876]"></span>Saved</span>
                            <span class="ml-auto rounded-md bg-[#eeeeee] px-3 py-1.5 font-semibold">Preview</span>
                            <span class="rounded-md bg-[#ff6719] px-3 py-1.5 font-semibold text-white">Continue</span>
                        </div>
                        <div class="mt-1 flex items-center justify-center gap-2.5 px-3 py-1.5 text-[#363737] [&_svg]:size-3.5" aria-hidden="true">
                            <x-heroicon-m-arrow-uturn-left /><x-heroicon-m-arrow-uturn-right class="opacity-40" />
                            <span class="h-3.5 w-px bg-[#e6e6e6]"></span>
                            <span class="flex items-center gap-0.5 text-[11px] font-semibold">Style<x-heroicon-m-chevron-down class="!size-3" /></span>
                            <span class="h-3.5 w-px bg-[#e6e6e6]"></span>
                            <x-heroicon-m-bold /><x-heroicon-m-italic /><x-heroicon-m-strikethrough /><x-heroicon-m-code-bracket />
                            <span class="h-3.5 w-px bg-[#e6e6e6]"></span>
                            <x-heroicon-m-link /><x-heroicon-m-photo class="text-[#ff6719]" /><x-heroicon-m-musical-note class="max-sm:hidden" /><x-heroicon-m-video-camera class="max-sm:hidden" /><x-heroicon-m-chat-bubble-bottom-center-text class="max-sm:hidden" />
                            <span class="h-3.5 w-px bg-[#e6e6e6] max-sm:hidden"></span>
                            <x-heroicon-m-list-bullet class="max-sm:hidden" /><x-heroicon-m-numbered-list class="max-sm:hidden" />
                            <span class="h-3.5 w-px bg-[#e6e6e6] max-md:hidden"></span>
                            <span class="flex items-center gap-0.5 text-[11px] font-semibold max-md:hidden">Button<x-heroicon-m-chevron-down class="!size-3" /></span>
                            <span class="flex items-center gap-0.5 text-[11px] font-semibold max-md:hidden">More<x-heroicon-m-chevron-down class="!size-3" /></span>
                        </div>
                        <div class="mx-auto max-w-md px-5 pt-4 pb-8">
                            <p class="{{ $ssSerif }} text-[26px] leading-tight font-bold">{{ $title }}</p>
                            <p class="{{ $ssSerif }} mt-2 text-[15px] text-[#868787]">{{ $subtitle }}</p>
                            <p class="mt-3 flex items-center gap-1.5 text-xs">
                                <span class="flex items-center gap-1.5 rounded-full bg-[#eeeeee] py-1 pr-1.5 pl-2.5 text-[#757575]">Ada Example <x-heroicon-m-x-mark class="size-3" /></span>
                                <span class="flex size-6 items-center justify-center rounded-full border border-[#e6e6e6] text-[#757575]"><x-heroicon-m-plus class="size-3" /></span>
                            </p>
                            <p class="{{ $ssSerif }} mt-4 text-[15px] leading-relaxed">{{ $lede }}</p>
                            <div class="relative mt-5">
                                <p data-hide-on-reveal class="{{ $ssSerif }} text-[15px]">{!! $caret !!}</p>
                                <div data-reveal class="relative rounded-[3px] shadow-[0_0_0_1px_#ff6719,0_0_0_4px_#fdebe2] transition-opacity duration-300">
                                    <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="" loading="lazy" class="block h-auto w-full rounded-[3px] border border-white">
                                    <span class="absolute top-2 right-2 flex h-5 w-6 items-center justify-center rounded-md bg-[#363737] text-white"><x-heroicon-m-ellipsis-horizontal class="size-3.5" /></span>
                                    <span class="absolute top-1/2 left-1.5 h-8 w-1.5 -translate-y-1/2 rounded-full border border-[#868787] bg-white"></span>
                                    <span class="absolute top-1/2 right-1.5 h-8 w-1.5 -translate-y-1/2 rounded-full border border-[#868787] bg-white"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- 3. The published post --}}
                <div data-share-scene="2" class="{{ $scene(2) }} relative overflow-hidden bg-white {{ $ssSans }} text-[#363737]">
                    <div data-scroll="1000" class="absolute inset-x-0 top-0">
                        <div class="flex items-center border-b border-[#e6e6e6] px-4 py-3">
                            <span class="flex size-7 items-center justify-center rounded-md bg-[#363737] text-[11px] font-bold text-white" aria-hidden="true">AN</span>
                            <span class="flex-1 text-center text-base font-bold">Ada's Notebook</span>
                            <span class="rounded-lg bg-[#ff6719] px-3 py-1.5 text-xs font-semibold text-white">Subscribe</span>
                        </div>
                        <div class="mx-auto max-w-md px-5 pt-5 pb-6">
                            <p class="text-[26px] leading-[1.15] font-bold">{{ $title }}</p>
                            <p class="mt-2 text-[15px] leading-snug text-[#868787]">{{ $subtitle }}</p>
                            <div class="mt-3 flex items-center gap-2.5 text-[11px] leading-[18px] font-medium tracking-[0.2px] uppercase">
                                <span class="flex size-8 items-center justify-center rounded-full bg-sky-200 text-[10px] font-semibold text-sky-900 normal-case" aria-hidden="true">AE</span>
                                <span><span class="block">Ada Example</span><span class="block text-[#868787]">Nov 16, 2026</span></span>
                            </div>
                            <div class="mt-3 flex items-center gap-1.5 text-xs font-medium text-[#868787]" aria-hidden="true">
                                <span class="{{ $ssPill }}"><x-heroicon-o-heart class="size-3.5" />48</span>
                                <span class="{{ $ssPill }}"><x-heroicon-o-chat-bubble-oval-left class="size-3.5" />3</span>
                                <span class="{{ $ssPill }}"><x-heroicon-o-arrow-path class="size-3.5" />12</span>
                                <span class="{{ $ssPill }} ml-auto">Share</span>
                            </div>
                            <p class="{{ $ssSerif }} mt-4 text-[15px] leading-relaxed">{{ $lede }}</p>
                            <figure class="mt-5">
                                <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="{{ $image['alt'] }}" loading="lazy" class="block h-auto w-full">
                                <figcaption class="{{ $ssSerif }} mt-2 px-[15%] text-center text-[13px] leading-5 tracking-[-0.15px] text-[#868787]">Penguin body mass model. Full results on <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[#363737] underline">Framework</a>.</figcaption>
                            </figure>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Drupal or a hosted CMS: the stylesheet and script once, then the tag in an HTML block --}}
            <div role="tabpanel" id="share-panel-generic" aria-labelledby="share-tab-generic" data-share-panel="generic" data-share-durations="[1400,1400,3200]" inert class="{{ $panel('generic') }} grid min-w-0 [grid-area:1/1]">
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
            {{-- Bluesky: the copied image, attached to a post --}}
            <div role="tabpanel" id="share-panel-bluesky" aria-labelledby="share-tab-bluesky" data-share-panel="bluesky" data-share-durations="[1800,2000,3200]" inert class="{{ $panel('bluesky') }} grid min-w-0 [grid-area:1/1]">
                {{-- 1. Framework's Share dialog: Copy image --}}
                <div data-share-scene="0" aria-hidden="true" class="{{ $scene(0) }} flex items-center justify-center bg-slate-900/5 p-5 font-sans dark:bg-black/30">
                    <x-landing-shared.copy :image="$image" :title="$embed['title']" />
                </div>
                {{-- 2. Bluesky's composer: the image pasted in --}}
                <div data-share-scene="1" aria-hidden="true" class="{{ $scene(1) }} flex items-center justify-center bg-slate-900/5 p-5 font-sans dark:bg-black/30">
                    <div class="w-full max-w-lg rounded-xl bg-white shadow-xl ring-1 ring-slate-800/10 dark:bg-slate-800 dark:ring-white/10">
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-[#1185fe]">Cancel</span>
                            <span class="rounded-full {{ $skyBlue }} px-4 py-1 font-semibold text-white">Post</span>
                        </div>
                        <div class="flex gap-3 px-4 pb-3">
                            <span class="{{ $avatar }}">AE</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-relaxed text-slate-900 dark:text-slate-100">{{ $postText }} Full model: <span class="text-[#1185fe]">{{ $postLink }}</span>{!! $caret !!}</p>
                                <div class="relative mt-3 w-40 max-w-full">
                                    <span data-hide-on-reveal class="absolute top-0 left-0 rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white dark:bg-white dark:text-slate-900">⌘V</span>
                                    <div data-reveal class="relative overflow-hidden rounded-lg ring-1 ring-slate-800/10 transition-opacity duration-300">
                                        <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="" loading="lazy" class="block h-auto w-full">
                                        <span class="absolute bottom-1.5 left-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white">ALT</span>
                                        <span class="absolute top-1.5 right-1.5 flex size-5 items-center justify-center rounded-full bg-black/70 text-white"><x-heroicon-m-x-mark class="size-3.5" /></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 border-t border-slate-800/10 px-4 py-2 text-[#1185fe] dark:border-white/10">
                            <x-heroicon-o-photo class="size-5" />
                            <x-heroicon-o-face-smile class="size-5" />
                            <span class="ml-auto text-xs text-slate-500 dark:text-slate-400">{{ 300 - mb_strlen($postText.' Full model: '.$postLink) }}</span>
                        </div>
                    </div>
                </div>
                {{-- 3. The post's page on bsky.app, after a live one: a sticky "Post" header, the author with Follow,
                     the text, the image with ALT, the time, counts, and actions. Bluesky's own light and dim themes. --}}
                <div data-share-scene="2" class="{{ $scene(2) }} relative overflow-hidden bg-white {{ $bsSans }} text-[#0b0f14] dark:bg-[#151d28] dark:text-white">
                    <div class="absolute inset-x-0 top-0 z-10 border-b {{ $bsRule }} bg-white dark:bg-[#151d28]">
                        <p class="mx-auto flex max-w-sm items-center gap-4 px-5 py-2.5 text-[17px] font-semibold">
                            <x-heroicon-m-arrow-left class="size-5" aria-hidden="true" />Post
                            <x-heroicon-o-adjustments-vertical class="ml-auto size-5 {{ $bsMuted }}" aria-hidden="true" />
                        </p>
                    </div>
                    <div data-scroll="900" class="absolute inset-x-0 top-0 pt-[45px]">
                        <div class="mx-auto max-w-sm px-5 pt-3 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="{{ $avatar }} size-10" aria-hidden="true">AE</span>
                                <p class="min-w-0 flex-1 leading-tight"><span class="block text-[15px] font-semibold">Ada Example</span><span class="block text-sm {{ $bsMuted }}">@adaexample.bsky.social</span></p>
                                <span class="flex items-center gap-1 rounded-full bg-[#1e2936] px-3 py-1.5 text-[13px] font-semibold text-white dark:bg-[#2e4052]" aria-hidden="true"><x-heroicon-m-plus class="size-3.5" />Follow</span>
                            </div>
                            <p data-scroll-to class="mt-2.5 text-[15px] leading-snug">{{ $postText }} Full model: <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[#1083fe] hover:underline">{{ $postLink }}</a></p>
                            <div class="mt-2.5 overflow-hidden rounded-xl border {{ $bsRule }}">
                                <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="{{ $image['alt'] }}" loading="lazy" class="block h-auto w-full">
                            </div>
                            <p class="mt-2.5 flex items-center gap-1.5 text-[13px] {{ $bsMuted }}" aria-hidden="true">6:12 PM · Nov 17, 2026 <x-heroicon-o-globe-americas class="ml-1 size-3.5" /> Everybody can reply</p>
                            <p class="mt-2.5 flex gap-4 border-t {{ $bsRule }} pt-2.5 text-[14px] {{ $bsMuted }}" aria-hidden="true">
                                <span><b class="font-semibold text-[#0b0f14] dark:text-white">12</b> reposts</span>
                                <span><b class="font-semibold text-[#0b0f14] dark:text-white">48</b> likes</span>
                            </p>
                            <p class="mt-2 flex items-center gap-8 text-[13px] {{ $bsMuted }} [&_svg]:size-[18px]" aria-hidden="true">
                                <span class="flex items-center gap-1"><x-heroicon-o-chat-bubble-left />3</span>
                                <span class="flex items-center gap-1"><x-heroicon-o-arrow-path-rounded-square />12</span>
                                <span class="flex items-center gap-1"><x-heroicon-o-heart />48</span>
                                <span class="ml-auto flex gap-4"><x-heroicon-o-bookmark /><x-heroicon-o-arrow-uturn-right /><x-heroicon-o-ellipsis-horizontal /></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </figure>
</div>
