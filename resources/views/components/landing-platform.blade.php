@php
    // What Framework Cloud is, then one pane per feature. Panes with more than one
    // screenshot of the current app play as a carousel.
    $screens = [
        'blueprints' => ['Blueprints', 'Save a project setup once and start each new project from it.', [
            ['blueprints', 'See what each blueprint makes: folders, packages, agents, and skills.', 'Blueprints page with the Penguin lab blueprint, its project structure, and its packages'],
            ['blueprint-packages', 'Pick the packages every project installs and which ones load.', 'Blueprint editor Packages tab with R packages, their sources, and auto-attach switches'],
            ['blueprint-git', 'Stop commits that contain secrets, private folders, or notebook outputs.', 'Blueprint editor Git tab with secret detection, private folder warnings, and notebook output checks switched on'],
            ['blueprint-quarto', 'Set the theme, contents, and code options for documents.', 'Blueprint editor Quarto tab with the Cosmo theme, table of contents, and code options'],
            ['blueprint-connections', 'Name databases and storage buckets. Secrets stay in .env.', 'Blueprint editor Connections tab with a PostgreSQL database whose host, user, and password read env() variables'],
            ['blueprint-skills', 'Choose the skills your AI agent gets.', 'Blueprint editor AI Skills tab with skill sets by category and the skills in the Framework set'],
            ['project-defaults', 'Choose the default blueprint for each language.', 'Project defaults settings with the default language, projects folder, and Penguin lab as the R blueprint'],
        ]],
        'new-project' => ['New project', 'Start from a blueprint, adjust it, and review the result.', [
            ['new-project-start', 'Choose a language and a blueprint.', 'New project start step with R selected and the Penguin lab blueprint chosen'],
            ['new-project', 'Adjust any choice and watch the file tree update.', 'New project Project structure tab with structure choices and a preview of the project files'],
            ['new-project-review', 'Check the summary, then set the project up.', 'New project review step with the project name, description, and a summary of its setup'],
        ]],
        'projects' => ['Projects', 'Set a project up anywhere and keep it in sync.', [
            ['project-overview', 'Set up the project locally, on GitHub, or from a zip.', 'Penguin study overview with Local setup, GitHub repository, and ZIP download cards'],
            ['project-registry', 'See the last sync and the data the project reads by name.', 'Penguin study connections and data registry with four data entries'],
            ['dashboard', 'Recent artifacts, collections, and projects in one place.', 'Dashboard with recent activity and recent projects'],
            ['dashboard-commands', 'Copy commands for R, Python, Stata, or the command line.', 'Dashboard quick commands for installing the R package, signing in, and syncing setup, data registry, and skills'],
        ]],
        'pieces' => ['Project pieces', 'Manage AI skills, .gitignore rules, folder layouts, and package sets.', [
            ['pieces-skills', 'Copy, edit, or import the skills your AI agent uses.', 'Skills page with R skills filtered by category, each with tags and Copy and edit'],
            ['pieces-gitignore', 'Edit the rules that keep private data out of Git.', 'Project structure editor Git ignore tab with rules for private data, credentials, and local records'],
            ['pieces-package-sets', 'Group packages you use together and add them in one step.', 'Package sets page with R package sets by category'],
        ]],
        'published' => ['Published results', 'Each result gets a stable link and a readable page.', [
            ['published-index', 'Find everything you have published, across projects.', 'Artifacts page listing the published penguin figure, data, table, and model'],
            ['published-model', 'Readers get a formatted, searchable model summary.', 'Reader page for the penguin body mass model with its coefficient table'],
            ['published-figure', 'Readers see the figure and can download the file.', 'Reader page for a scatterplot of flipper length and body mass'],
        ]],
        'versions' => ['Versioned artifacts', 'Publish again and the result becomes version 2. Each version keeps its own link.', [
            ['versions', 'Open any earlier version. The main link shows the newest.', 'Versions of the penguin body mass model: version 2 is the newest, version 1 is kept'],
        ]],
        'sharing' => ['Sharing', 'Make each result public, unlisted, or password protected.', [
            ['sharing', 'Choose who can read it, copy its links, or add a password.', 'Sharing tab with reader access choices and the share and readable links'],
        ]],
        'collections' => ['Collections', 'Group related results on one page.', [
            ['collection-edit', 'Add artifacts, order them, and choose who can read the collection.', 'Collection editor with four penguin study artifacts and its sharing settings'],
            ['collection', 'Share one link for a set of related results.', 'Collection page listing the penguin data, summary table, model, and figure'],
        ]],
    ];
@endphp
<section id="framework-cloud" data-cloud-screens class="scroll-mt-6 border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Framework Cloud</h2>
        <p class="mt-4 text-lg leading-relaxed text-balance text-slate-700 dark:text-slate-300">Framework Cloud is a companion web app for Framework. It stores your blueprints, AI skills, and project settings. It also lets you share your work securely.</p>
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
                                        <img src="/images/landing/cloud/{{ $image }}-light.webp" srcset="/images/landing/cloud/{{ $image }}-light-1280.webp 1280w, /images/landing/cloud/{{ $image }}-light.webp 2560w" sizes="(min-width: 1152px) 1152px, 100vw" alt="{{ $alt }}" width="2560" height="1660" loading="lazy" decoding="async" class="block w-full dark:hidden">
                                        <img src="/images/landing/cloud/{{ $image }}-dark.webp" srcset="/images/landing/cloud/{{ $image }}-dark-1280.webp 1280w, /images/landing/cloud/{{ $image }}-dark.webp 2560w" sizes="(min-width: 1152px) 1152px, 100vw" alt="{{ $alt }}" width="2560" height="1660" loading="lazy" decoding="async" class="hidden w-full dark:block">
                                    </div>
                                @endforeach
                            </div>
                            {{-- Caption left and controls right hang below the screenshot, so only the screenshot is centered --}}
                            <div class="absolute inset-x-0 top-full mt-2 flex min-h-7 items-center justify-between gap-3">
                                <p class="min-w-0 text-xs leading-snug text-slate-600 dark:text-slate-400">
                                    @foreach ($slides as [$image, $caption])<span data-cloud-caption @unless ($loop->first) hidden @endunless>{{ $caption }}</span>@endforeach
                                </p>
                                @if (count($slides) > 1)
                                <div class="flex shrink-0 items-center gap-0.5" role="group" aria-label="{{ $title }} screenshots">
                                    <button type="button" data-cloud-step="-1" aria-label="Previous screenshot" class="rounded-md p-1 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-slate-200"><x-heroicon-m-chevron-left class="size-4" /></button>
                                    @foreach ($slides as $dot)
                                        <button type="button" data-cloud-dot="{{ $loop->index }}" aria-label="Screenshot {{ $loop->iteration }} of {{ count($slides) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="group flex h-6 min-w-6 items-center justify-center"><span class="h-1.5 w-1.5 rounded-full bg-slate-300 transition-all group-aria-pressed:w-5 group-aria-pressed:bg-sky-700 dark:bg-slate-600 dark:group-aria-pressed:bg-sky-400"></span></button>
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
        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Create an account</a>
        <a href="{{ route('self-hosting') }}" class="px-3 py-2 text-sm text-slate-700 underline decoration-slate-300 underline-offset-4 dark:text-slate-300 dark:decoration-slate-600">Self-hosting &rarr;</a>
    </div>
</section>
