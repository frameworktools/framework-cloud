@php
    // Four ways to start the same penguin-study project, each with a short preview of its action.
    // The CLI form follows framework-cli's quickstart guide; Download opens New project with
    // ZIP download chosen. FW_STARTER_REPOSITORY (owner/name) sets the GitHub card's repository.
    $starter = config('framework.starter_repository');
    $repo = $starter ?: 'username/penguin-study';
    $repoUrl = $starter ? "https://github.com/$starter" : route('projects.create', ['destination' => 'github']);
    $cards = [
        ['heroicon-o-code-bracket', 'Use a package', 'Create a project from R or Python.', 'framework::new_project(...)'],
        ['heroicon-o-command-line', 'Use the command line', 'Create a project from your terminal with the Framework CLI'.(isset(\App\Support\Docs\Products::listed()['stata']) ? ', including Stata projects.' : '.'), 'fw new penguin-study'],
        ['icon-fork', 'Start from GitHub', 'Start with a blueprint on GitHub and clone the repository.', "git clone https://github.com/username/penguin-study.git"],
        ['heroicon-o-archive-box', 'Download a ZIP', 'Download the project files and unpack them on your computer.', null],
    ];
@endphp
<section id="cloud" class="py-14 sm:py-20">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Four ways to start a Framework project</h2>
        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">Start a project in Framework Cloud, with the R or Python package, or from the command line. Afterward, manage its packages and AI skills in Framework Cloud and pull the changes into the project, or push your local changes back.</p>
    </div>
    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as [$icon, $title, $body, $command])
            <x-landing-frame class="flex flex-col p-5">
                <x-dynamic-component :component="$icon" class="size-5 text-slate-500 dark:text-slate-400" />
                <h3 class="mt-3 font-semibold">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $body }}</p>
                <div class="mt-auto space-y-2 pt-4">
                    @if ($title === 'Start from GitHub')
                        <a href="{{ $repoUrl }}" class="flex w-fit max-w-full items-center gap-1.5 text-sm text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"><x-icon-github class="size-4 shrink-0" /><span class="truncate font-mono text-xs">{{ $repo }}</span></a>
                    @endif
                    @if ($command)
                        <code class="block overflow-hidden rounded-lg bg-[#0c1526] px-2.5 py-2 font-mono text-xs whitespace-nowrap xl:text-[11px] text-slate-100 ring-1 ring-slate-400/20 shadow-[0_4px_12px_-4px_rgba(15,23,42,0.25)] dark:shadow-[0_4px_12px_-4px_rgba(0,0,0,0.6)]"><span class="block overflow-hidden [mask-image:linear-gradient(to_right,black_75%,transparent)]">{{ $command }}</span></code>
                    @else
                        <a href="{{ route('projects.create', ['destination' => 'download']) }}" class="flex w-fit items-center gap-1.5 py-2 text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300"><x-heroicon-m-arrow-down-tray class="size-4 shrink-0" />Download penguin-study.zip</a>
                    @endif
                </div>
            </x-landing-frame>
        @endforeach
    </div>
</section>
