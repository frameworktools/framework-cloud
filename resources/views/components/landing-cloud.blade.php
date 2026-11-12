@php
    // Ways to start the same penguin-study project, in two tabs: from Framework Cloud, or from your computer.
    // The CLI form follows framework-cli's quickstart guide; Download opens New project with
    // ZIP download chosen. FW_STARTER_REPOSITORY (owner/name) sets the GitHub card's repository.
    $starter = config('framework.starter_repository');
    $repo = $starter ?: 'username/penguin-study';
    $repoUrl = $starter ? "https://github.com/$starter" : route('projects.create', ['destination' => 'github']);
    $stata = isset(\App\Support\Docs\Products::listed()['stata']);
    $groups = [
        'cloud' => ['Start from Framework Cloud', [
            ['heroicon-o-squares-2x2', 'Pick a blueprint', 'Choose a blueprint, adjust its settings, and see every file before the project is made.', null, 'blueprint'],
            ['icon-fork', 'Put it on GitHub', 'Framework Cloud creates the repository. Clone it to start working.', 'git clone https://github.com/username/penguin-study.git', 'github'],
            ['heroicon-o-archive-box', 'Download a ZIP', 'Download the project files and unpack them on your computer.', null, 'zip'],
        ]],
        'local' => ['Start from your computer', [
            ['heroicon-o-code-bracket', 'Use a package', 'Create a project from R or Python. Signed in, it uses your default blueprint from Framework Cloud. Name another with type.', 'framework::new_project("penguin-study")', null],
            ['heroicon-o-command-line', 'Use the command line', 'Create a project from your terminal with the Framework CLI'.($stata ? ', including Stata projects' : '').'. Signed in, it uses your default blueprint. Name another with --blueprint.', 'fw new penguin-study', null],
        ]],
    ];
@endphp
<section id="cloud" class="py-14 sm:py-20">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Start a Framework project</h2>
        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">Set a project up in Framework Cloud or create it on your computer with the R or Python package or the command line.</p>
    </div>
    <div data-setup-tabs class="mt-8">
        <div role="tablist" aria-label="Where to start" class="mx-auto flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 dark:bg-white/5">
            @foreach ($groups as $key => [$label])
                <button type="button" role="tab" id="start-tab-{{ $key }}" aria-controls="start-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-setup-tab="{{ $key }}" class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap text-slate-600 hover:text-slate-950 aria-selected:bg-white aria-selected:text-slate-950 aria-selected:shadow-sm dark:text-slate-400 dark:hover:text-white dark:aria-selected:bg-slate-700 dark:aria-selected:text-white">{{ $label }}</button>
            @endforeach
        </div>
        @foreach ($groups as $key => [$label, $cards])
            <div role="tabpanel" id="start-panel-{{ $key }}" aria-labelledby="start-tab-{{ $key }}" data-setup-panel="{{ $key }}" @unless ($loop->first) hidden @endunless class="mx-auto mt-6 grid gap-4 {{ count($cards) === 3 ? 'md:grid-cols-3' : 'max-w-4xl md:grid-cols-2' }}">
                @foreach ($cards as [$icon, $title, $body, $command, $action])
                    <x-landing-frame class="flex flex-col p-5">
                        <x-dynamic-component :component="$icon" class="size-5 text-slate-500 dark:text-slate-400" />
                        <h3 class="mt-3 font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $body }}</p>
                        <div class="mt-auto space-y-2 pt-4">
                            @if ($action === 'github')
                                <a href="{{ $repoUrl }}" class="flex w-fit max-w-full items-center gap-1.5 text-sm text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"><x-icon-github class="size-4 shrink-0" /><span class="truncate font-mono text-xs">{{ $repo }}</span></a>
                            @endif
                            @if ($command)
                                <code class="block overflow-hidden rounded-lg bg-[#0c1526] px-2.5 py-2 font-mono text-xs whitespace-nowrap text-slate-100 ring-1 ring-slate-400/20 shadow-[0_4px_12px_-4px_rgba(15,23,42,0.25)] dark:shadow-[0_4px_12px_-4px_rgba(0,0,0,0.6)]"><span class="block overflow-hidden [mask-image:linear-gradient(to_right,black_75%,transparent)]">{{ $command }}</span></code>
                            @elseif ($action === 'zip')
                                <a href="{{ route('projects.create', ['destination' => 'download']) }}" class="flex w-fit items-center gap-1.5 py-2 text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300"><x-heroicon-m-arrow-down-tray class="size-4 shrink-0" />Download penguin-study.zip</a>
                            @elseif ($action === 'blueprint')
                                <a href="{{ route('projects.create') }}" class="flex w-fit items-center gap-1.5 py-2 text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300"><x-heroicon-m-arrow-right class="size-4 shrink-0" />New project</a>
                            @endif
                        </div>
                    </x-landing-frame>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
