@php
    // What Framework Cloud is, then one pane per feature. Panes with more than one
    // screenshot of the current app play as a carousel.
    $screens = [
        'defaults' => ['Default settings', 'Set your language, packages, data safety hooks, and Quarto defaults once.', [
            ['settings-language', 'Choose R, Python, or Stata for new projects.', 'Language default for new projects: R, Python, or Stata'],
            ['settings-packages', 'Pick the packages every new project installs.', 'Default R packages, each with its source and auto-attach setting'],
            ['settings-hooks', 'Stop commits that contain secrets, private folders, or notebook outputs.', 'Data safety hooks: secret detection, private folder warnings, and notebook output checks, all switched on'],
            ['settings-quarto', 'Set the theme, contents, and code options for documents.', 'Quarto defaults for HTML documents: theme, table of contents, and code options'],
        ]],
        'templates' => ['Templates', 'Manage AI skills, .gitignore rules, folder layouts, and package sets.', [
            ['template-gitignore', 'Edit the rules that keep private data out of Git.', 'Project structure editor with its .gitignore rules for private data and credentials'],
            ['template-skills', 'Copy, edit, or import the skills your AI agent uses.', 'AI agent skills for R, Python, and Stata, with tags and Copy and edit'],
            ['template-package-sets', 'Group packages you use together and add them in one step.', 'Package sets that add a group of packages to a project'],
        ]],
        'blueprints' => ['Blueprints', 'Start each new project from a saved setup.', [
            ['blueprints', 'Combine a structure, packages, and skills into a starting point.', 'Blueprints page showing a project structure and its package list'],
        ]],
        'published' => ['Published results', 'Each result gets a stable link and a readable page.', [
            ['published-index', 'Find everything you have published, across projects.', 'Artifacts page listing the published penguin model, data, figure, and table'],
            ['published-model', 'Readers get a formatted, searchable model summary.', 'Reader page for the penguin body mass model with its coefficient table'],
            ['published-figure', 'Readers see the figure and can download the file.', 'Reader page for a scatterplot of flipper length and body mass'],
        ]],
        'versions' => ['Versioned artifacts', 'Publish again and the result becomes version 2. Each version keeps its own link.', [
            ['versions', 'Open any earlier version. The main link shows the newest.', 'Versions of the penguin body mass model: version 2 is the newest, version 1 is kept'],
        ]],
        'sharing' => ['Sharing', 'Make each result public, unlisted, or password protected.', [
            ['sharing', 'Choose who can read it, copy its links, or add a password.', 'Sharing settings with reader access, share links, and an optional password'],
        ]],
        'collections' => ['Collections', 'Group related results on one page.', [
            ['collection', 'Share one link for a set of related results.', 'Collection page listing the penguin data, summary table, model, and figure'],
        ]],
    ];
@endphp
<section id="framework-cloud" data-cloud-screens class="scroll-mt-6 border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Framework Cloud</h2>
        <p class="mt-4 text-lg leading-relaxed text-balance text-slate-700 dark:text-slate-300">Framework Cloud is a companion web app for Framework. It stores your project defaults, blueprints, AI skills, and other settings. It also lets you share your work securely.</p>
        <p class="mt-3 text-balance text-slate-600 dark:text-slate-400">Use it for free at <a href="https://framework.pub" class="text-slate-800 underline decoration-slate-300 underline-offset-4 dark:text-slate-200 dark:decoration-slate-600">framework.pub</a> or host it on your own server.</p>
    </div>

        <div class="mt-10">
        <x-landing-frame shadow="xl" class="overflow-hidden">
            <div class="grid min-w-0 md:grid-cols-[16rem_minmax(0,1fr)]">
                <div role="tablist" aria-label="Framework Cloud features" aria-orientation="vertical" class="flex gap-1 overflow-x-auto border-b border-slate-800/10 p-2 md:flex-col md:overflow-visible md:border-r md:border-b-0 dark:border-white/10">
                    @foreach ($screens as $key => [$title, $summary])
                        <button type="button" role="tab" id="cloud-tab-{{ $key }}" aria-controls="cloud-screen-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-cloud-tab="{{ $key }}" class="w-52 shrink-0 rounded-lg px-3 py-2.5 text-left hover:bg-slate-50 aria-selected:bg-sky-50 md:w-auto dark:hover:bg-white/5 dark:aria-selected:bg-white/10">
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $title }}</span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $summary }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex min-w-0 flex-col justify-center bg-slate-50 px-3 pt-10 pb-20 sm:px-5 sm:pb-14 dark:bg-white/[0.03]">
                    @foreach ($screens as $key => [$title, $summary, $slides])
                        <div role="tabpanel" id="cloud-screen-{{ $key }}" aria-labelledby="cloud-tab-{{ $key }}" data-cloud-screen="{{ $key }}" @unless ($loop->first) hidden @endunless class="relative">
                            <div class="overflow-hidden rounded-lg shadow-lg ring-1 shadow-slate-900/10 ring-slate-800/10 dark:shadow-black/30 dark:ring-white/15">
                                @foreach ($slides as [$image, $caption, $alt])
                                    <div data-cloud-slide @unless ($loop->first) hidden @endunless>
                                        <img src="/images/landing/cloud/{{ $image }}-light.webp" alt="{{ $alt }}" width="2560" height="1660" @unless ($loop->parent->first && $loop->first) loading="lazy" @endunless class="block w-full dark:hidden">
                                        <img src="/images/landing/cloud/{{ $image }}-dark.webp" alt="{{ $alt }}" width="2560" height="1660" @unless ($loop->parent->first && $loop->first) loading="lazy" @endunless class="hidden w-full dark:block">
                                    </div>
                                @endforeach
                            </div>
                            {{-- Caption left and controls right hang below the screenshot, so only the screenshot is centered --}}
                            <div class="absolute inset-x-0 top-full mt-2 flex min-h-7 items-center justify-between gap-3">
                                <p class="min-w-0 text-xs leading-snug text-slate-600 dark:text-slate-400">
                                    @foreach ($slides as [$image, $caption])<span data-cloud-caption @unless ($loop->first) hidden @endunless>{{ $caption }}</span>@endforeach
                                </p>
                                @if (count($slides) > 1)
                                <div class="flex shrink-0 items-center gap-2" role="group" aria-label="{{ $title }} screenshots">
                                    <button type="button" data-cloud-step="-1" aria-label="Previous screenshot" class="rounded-md p-1 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-slate-200"><x-heroicon-m-chevron-left class="size-4" /></button>
                                    @foreach ($slides as $dot)
                                        <button type="button" data-cloud-dot="{{ $loop->index }}" aria-label="Screenshot {{ $loop->iteration }} of {{ count($slides) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="h-1.5 w-1.5 rounded-full bg-slate-300 transition-all aria-pressed:w-5 aria-pressed:bg-sky-600 dark:bg-slate-600 dark:aria-pressed:bg-sky-400"></button>
                                    @endforeach
                                    <button type="button" data-cloud-step="1" aria-label="Next screenshot" class="rounded-md p-1 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-slate-200"><x-heroicon-m-chevron-right class="size-4" /></button>
                                    <button type="button" data-cloud-pause aria-label="Pause screenshots" aria-pressed="false" class="group ml-1 rounded-md p-1 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-slate-200"><x-heroicon-m-pause class="size-4 group-aria-pressed:hidden" /><x-heroicon-m-play class="hidden size-4 group-aria-pressed:block" /></button>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-landing-frame>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Create an account</a>
        <a href="{{ route('self-hosting') }}" class="px-3 py-2 text-sm text-slate-700 underline decoration-slate-300 underline-offset-4 dark:text-slate-300 dark:decoration-slate-600">Self-hosting &rarr;</a>
    </div>
</section>
