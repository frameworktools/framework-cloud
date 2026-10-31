<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Framework Cloud: rigorous, responsible, and reproducible data science for the AI age</title>
    <meta name="description" content="Framework brings project structure, AI guidance, data integrity checks, and safeguards for sensitive data into your R workflow. Framework Cloud helps you set up these practices and reuse them across projects.">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

    @php
        $features = [
            ['anchor' => 'structure', 'icon' => 'heroicon-o-square-3-stack-3d', 'heading' => 'Start with a clear project structure', 'body' => 'Give data, code, notebooks, and results a consistent place.'],
            ['anchor' => 'ai', 'icon' => 'heroicon-o-sparkles', 'heading' => 'Guide AI assistants', 'body' => 'Give assistants project context and task-specific instructions that follow your research workflow.'],
            ['anchor' => 'data', 'icon' => 'heroicon-o-shield-check', 'heading' => 'Help protect sensitive data', 'body' => 'Set up private folders, assistant restrictions, and Git checks that help reduce accidental data exposure.'],
            ['anchor' => 'integrity', 'icon' => 'heroicon-o-finger-print', 'heading' => 'Check data integrity', 'body' => 'Record file fingerprints and check whether a cataloged dataset has changed since it was recorded.'],
            ['anchor' => 'environments', 'icon' => 'heroicon-o-cube', 'heading' => 'Manage reproducible environments', 'body' => 'List project packages, install them automatically, and use optional version snapshots to restore them.'],
            ['anchor' => 'cli', 'icon' => 'heroicon-o-command-line', 'heading' => 'Work from the command line', 'body' => 'The `fw` command-line tool gives you and your AI agents the platform from a terminal, with JSON output for scripts.'],
            ['anchor' => 'copy', 'icon' => 'heroicon-o-document-duplicate', 'heading' => 'Copy and share project setups', 'body' => 'Adapt a colleague\'s blueprint, or share a reusable setup with your lab or team.'],
            ['anchor' => 'github', 'icon' => 'icon-github', 'heading' => 'Create projects on GitHub', 'body' => 'Create a GitHub repository with your project files committed and ready to clone.'],
            ['anchor' => 'publishing', 'icon' => 'heroicon-o-paper-airplane', 'heading' => 'Publish and share notebooks', 'body' => 'Publish Quarto notebooks as web pages that collaborators can open from a link.'],
        ];

        $screens = [
            ['key' => 'blueprints', 'label' => 'Blueprints', 'caption' => 'Each blueprint lists the folders, skills, and files a new project receives.', 'alt' => 'The Blueprints page, with the Standard Project Structure blueprint expanded to show its folders, skills, and files.'],
            ['key' => 'new-project', 'label' => 'New project', 'caption' => 'Choose a blueprint, name the project, and choose where to set it up.', 'alt' => 'The New project page, with a list of blueprints to choose from and a summary of the next steps.'],
            ['key' => 'skills', 'label' => 'Skills', 'caption' => 'Skills are instruction files for AI assistants. Copy Framework\'s or import your own from GitHub.', 'alt' => 'The Skills page, listing Framework\'s skills for data, outputs, packages, sensitive data, and workflow.'],
            ['key' => 'profile', 'label' => 'Author profile', 'caption' => 'Your author details go into the front matter of new notebooks, reports, and slides.', 'alt' => 'The Profile settings page, with name, email, ORCID iD, website, and an affiliation filled in.'],
        ];

        $c = 'text-slate-500'; // code comments
        $s = 'text-sky-400'; // code strings
        $em = 'font-medium text-slate-900 dark:text-slate-100';
        $section = 'scroll-mt-6 border-t border-slate-200 py-12 sm:py-16 dark:border-slate-800';
        $h2 = 'text-2xl font-semibold tracking-tight text-balance sm:text-3xl';
        $prose = 'mt-4 flex flex-col gap-3 text-slate-600 dark:text-slate-400';
        $code = 'rounded bg-slate-100 px-1 py-0.5 font-mono text-[0.9em] text-slate-800 dark:bg-slate-800 dark:text-slate-200';
    @endphp

    <div class="relative overflow-hidden border-b border-sky-900/10 bg-gradient-to-b from-sky-100 via-sky-50 to-white text-slate-900 dark:border-white/10 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 dark:text-slate-50">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(40%_60%_at_85%_20%,rgba(255,255,255,0.9),transparent_70%),radial-gradient(30%_45%_at_65%_75%,rgba(255,255,255,0.7),transparent_70%)] dark:bg-[radial-gradient(60%_80%_at_100%_0%,rgba(14,165,233,0.18),transparent_70%)]"></div>
    <x-site-header overlay />

    {{-- Hero: a light sky gradient, or slate in dark mode --}}
    <section class="relative">
        <div class="relative mx-auto max-w-6xl px-6 py-20 sm:py-28">
            <div class="max-w-5xl">
                <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl xl:text-6xl">
                    Rigorous, responsible, and reproducible data science for the AI age
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-700 dark:text-slate-200">
                    Framework Cloud helps you build and share consistent research workflows in R,
                    with reusable project setups, instructions for AI assistants, and tools for
                    managing data integrity and sensitive data.
                </p>
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-slate-700 dark:text-slate-200">
                    It works with the
                    <a href="https://framework.table1.org" class="font-medium text-sky-700 underline-offset-2 hover:underline dark:text-sky-400">Framework R package</a>,
                    which brings those setups and tools into your local analysis workflow.
                </p>
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-slate-700 dark:text-slate-200">Use our cloud or host it yourself.</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register') }}" class="rounded-md bg-sky-600 px-5 py-2.5 font-medium text-white hover:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">Create an account</a>
                    <a href="{{ route('self-hosting') }}" class="rounded-md border border-slate-300 bg-white/60 px-5 py-2.5 font-medium text-slate-800 hover:border-slate-400 hover:bg-white dark:border-slate-500 dark:bg-transparent dark:text-slate-50 dark:hover:border-slate-300 dark:hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">Host it yourself</a>
                </div>
            </div>
        </div>
    </section>
    </div>

    <main class="mx-auto max-w-6xl px-6">

        {{-- Features --}}
        <section id="features" class="py-12 sm:py-16">
            <h2 class="text-center {{ $h2 }}">Features</h2>
            <div class="mx-auto mt-8 grid max-w-5xl gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="group relative flex flex-col rounded-xl border border-slate-200 p-6 transition-colors focus-within:border-sky-400 hover:border-sky-400 hover:bg-sky-50/50 dark:border-slate-800 dark:hover:border-sky-800 dark:hover:bg-sky-950/20">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                            <x-dynamic-component :component="$feature['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-4 leading-snug font-semibold">
                            <a href="#{{ $feature['anchor'] }}" class="after:absolute after:inset-0 after:rounded-xl focus:outline-none">{{ $feature['heading'] }}</a>
                        </h3>
                        {{-- Bodies are fixed strings here; backticks mark code such as `fw` --}}
                        <p class="mt-2 flex-1 text-sm leading-normal text-slate-600 dark:text-slate-400 [&_code]:font-mono [&_code]:text-[0.9em] [&_code]:text-slate-800 dark:[&_code]:text-slate-200">{!! Str::inlineMarkdown($feature['body']) !!}</p>
                        <span aria-hidden="true" class="mt-4 text-sm font-medium text-sky-600 dark:text-sky-400">
                            How it works <span class="inline-block transition-transform group-hover:translate-x-0.5">&rarr;</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Screens from the application --}}
        <section id="screens" class="border-t border-slate-200 py-12 sm:py-16 dark:border-slate-800" data-carousel>
            <h2 class="text-center {{ $h2 }}">In the app</h2>
            <div class="mt-6 flex flex-wrap justify-center gap-2" role="tablist" aria-label="Screens">
                @foreach ($screens as $i => $screen)
                    <button type="button" role="tab" data-slide-tab="{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                        class="rounded-full border px-4 py-1.5 text-sm font-medium transition-colors aria-selected:border-sky-500 aria-selected:bg-sky-600 aria-selected:text-white border-slate-300 text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-500">
                        {{ $screen['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="relative mt-8">
                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-lg shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/40">
                    @foreach ($screens as $i => $screen)
                        <figure data-slide="{{ $i }}" @class(['transition-opacity duration-300', 'hidden' => $i !== 0]) role="tabpanel">
                            <img src="/images/landing/{{ $screen['key'] }}-light.png" width="2560" height="1660" alt="{{ $screen['alt'] }}" class="block w-full dark:hidden" @if ($i) loading="lazy" @endif>
                            <img src="/images/landing/{{ $screen['key'] }}-dark.png" width="2560" height="1660" alt="{{ $screen['alt'] }}" class="hidden w-full dark:block" @if ($i) loading="lazy" @endif>
                        </figure>
                    @endforeach
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-white to-transparent dark:from-slate-950"></div>
                </div>

                <button type="button" data-slide-step="-1" aria-label="Previous screen" class="absolute top-1/2 -left-4 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:text-sky-600 sm:flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <x-heroicon-o-chevron-left class="size-5" />
                </button>
                <button type="button" data-slide-step="1" aria-label="Next screen" class="absolute top-1/2 -right-4 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:text-sky-600 sm:flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <x-heroicon-o-chevron-right class="size-5" />
                </button>
            </div>

            @foreach ($screens as $i => $screen)
                <p data-slide-caption="{{ $i }}" @class(['mt-4 text-center text-sm text-slate-500 dark:text-slate-400', 'hidden' => $i !== 0])>{{ $screen['caption'] }}</p>
            @endforeach

            <script>
                (() => {
                    const root = document.currentScript.closest('[data-carousel]');
                    const tabs = root.querySelectorAll('[data-slide-tab]');
                    const count = tabs.length;
                    let current = 0;
                    const show = (index) => {
                        current = (index + count) % count;
                        root.querySelectorAll('[data-slide]').forEach((el) => el.classList.toggle('hidden', +el.dataset.slide !== current));
                        root.querySelectorAll('[data-slide-caption]').forEach((el) => el.classList.toggle('hidden', +el.dataset.slideCaption !== current));
                        tabs.forEach((tab) => tab.setAttribute('aria-selected', +tab.dataset.slideTab === current ? 'true' : 'false'));
                    };
                    tabs.forEach((tab) => tab.addEventListener('click', () => show(+tab.dataset.slideTab)));
                    root.querySelectorAll('[data-slide-step]').forEach((button) => button.addEventListener('click', () => show(current + +button.dataset.slideStep)));
                    root.querySelector('[role=tablist]').addEventListener('keydown', (event) => {
                        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                            show(current + (event.key === 'ArrowRight' ? 1 : -1));
                            tabs[current].focus();
                        }
                    });
                })();
            </script>
        </section>

        {{-- Project structure --}}
        <section id="structure" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div>
                    <h2 class="{{ $h2 }}">Start with a clear project structure</h2>
                    <div class="{{ $prose }}">
                        <p>
                            A blueprint is a reusable project setup. It defines a project's
                            folders, R packages, Quarto settings, and instructions for AI
                            assistants.
                        </p>
                        <p>
                            Framework includes blueprints for analysis projects, projects with
                            sensitive data, presentations, and courses. Raw inputs, intermediate
                            files, notebooks, and results each have a place.
                        </p>
                        <p>
                            Choose a blueprint in Framework Cloud to get a project token.
                            <code class="{{ $code }}">setup()</code> builds the project on your
                            computer.
                        </p>
                    </div>
                </div>
<x-code-panel title="R console">
framework::setup(<span class="{{ $s }}">"&lt;project token&gt;"</span>)

<span class="{{ $c }}"># Standard Project Structure creates:</span>
my-study/
├── AGENTS.md
├── settings.yml
├── docs/
├── functions/
├── inputs/
│   ├── raw/
│   ├── intermediate/
│   └── final/
├── notebooks/
├── outputs/
│   ├── cache/
│   ├── figures/
│   ├── models/
│   ├── reports/
│   └── tables/
├── scratch/
└── scripts/</x-code-panel>
            </div>
        </section>

        {{-- AI guidance --}}
        <section id="ai" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div class="lg:order-2">
                    <h2 class="{{ $h2 }}">Guide AI assistants</h2>
                    <div class="{{ $prose }}">
                        <p>
                            Framework adds written guidance to each project for AI coding
                            assistants such as Claude Code, GitHub Copilot, and Codex.
                        </p>
                        <p>
                            <span class="{{ $em }}">A context file</span>, AGENTS.md, describes the
                            project, how it is organized, and the rules for working in it.
                        </p>
                        <p>
                            <span class="{{ $em }}">Skills</span> are instructions for particular
                            tasks, such as reading and saving data, writing notebooks, and
                            publishing results. Each skill names the Framework functions to use and
                            explains the project's working conventions.
                        </p>
                        <p>
                            Assistants decide when to read a skill. You can edit Framework's
                            skills or write your own in Framework Cloud and add them to your
                            blueprints.
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-4 lg:order-1">
<x-code-panel title="my-study">
my-study/
├── AGENTS.md
└── .claude/skills/
    ├── framework-data/
    ├── framework-outputs/
    ├── framework-packages/
    └── framework-workflow/</x-code-panel>
<x-code-panel title=".claude/skills/framework-data/SKILL.md" wrap>
---
name: framework-data
description: <span class="{{ $s }}">Use whenever reading or writing data files in this Framework project - data_read() and data_save() are mandatory; base R and readr I/O functions are forbidden.</span>
---</x-code-panel>
                </div>
            </div>
        </section>

        {{-- Sensitive data --}}
        <section id="data" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div>
                    <h2 class="{{ $h2 }}">Help protect sensitive data</h2>
                    <div class="{{ $prose }}">
                        <p>
                            The blueprint for sensitive data separates private and public folders.
                            Its <code class="{{ $code }}">.gitignore</code> keeps the private
                            folders out of Git.
                        </p>
                        <p>
                            <span class="{{ $em }}">A sensitive-data skill</span> instructs
                            assistants to keep identifiable data in private folders and not to
                            commit or publish from them.
                        </p>
                        <p>
                            <span class="{{ $em }}">For Claude Code</span>, the project also adds
                            permission rules and sandbox settings that block reads of
                            <code class="{{ $code }}">.env</code> and the private folders. Other
                            assistants receive the written rules only.
                        </p>
                        <p>
                            <span class="{{ $em }}">An optional pre-commit check</span> stops a
                            commit when private data is tracked by Git, when private folders are
                            missing from <code class="{{ $code }}">.gitignore</code>, or when data
                            files appear in the repository's history. It checks commits, not other
                            ways data can leave a computer.
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-4">
<x-code-panel title=".gitignore">
<span class="{{ $c }}"># Private directories</span>
/inputs/private/
/reference/private/
/outputs/private/</x-code-panel>
<x-code-panel title="R console">
<span class="{{ $c }}"># Run the data check before each commit</span>
framework::git_hooks_enable(<span class="{{ $s }}">"data_security"</span>)</x-code-panel>
                </div>
            </div>
        </section>

        {{-- Data integrity --}}
        <section id="integrity" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div class="lg:order-2">
                    <h2 class="{{ $h2 }}">Check data integrity</h2>
                    <div class="{{ $prose }}">
                        <p>
                            Framework helps you check that you are using the dataset you expect.
                        </p>
                        <p>
                            Register a dataset in the project's data catalog. Saving it records a
                            fingerprint of the file. Reading it by its catalog name compares the
                            file with that record.
                        </p>
                        <p>
                            A changed file produces a warning. For a dataset marked as locked, the
                            read stops with an error.
                        </p>
                        <p>
                            <code class="{{ $code }}">data_verify()</code> compares a file with its
                            local record and, for projects connected to Framework Cloud, with the
                            project's ledger there. The ledger receives file fingerprints and
                            associated metadata, such as the dataset name, file size, and time,
                            when you push them or turn on live updates. Dataset contents are not
                            uploaded.
                        </p>
                    </div>
                </div>
<x-code-panel title="R console" class="lg:order-1">
<span class="{{ $c }}"># Register a file in the data catalog</span>
framework::data_add(<span class="{{ $s }}">"inputs/raw/survey.csv"</span>,
                    name = <span class="{{ $s }}">"inputs.raw.survey"</span>)

<span class="{{ $c }}"># Reads by catalog name compare the fingerprint</span>
survey &lt;- framework::data_read(<span class="{{ $s }}">"inputs.raw.survey"</span>)

<span class="{{ $c }}"># Compare with the local record and the cloud ledger</span>
framework::data_verify(<span class="{{ $s }}">"inputs.raw.survey"</span>)

<span class="{{ $c }}"># Send recorded fingerprints to Framework Cloud</span>
framework::ledger_push()</x-code-panel>
            </div>
        </section>

        {{-- Environments --}}
        <section id="environments" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div>
                    <h2 class="{{ $h2 }}">Manage reproducible environments</h2>
                    <div class="{{ $prose }}">
                        <p>
                            A project lists its R packages in
                            <code class="{{ $code }}">settings.yml</code>. When a notebook or script
                            starts with <code class="{{ $code }}">scaffold()</code>, Framework
                            installs missing packages and attaches the ones marked to load.
                        </p>
                        <p>
                            To record exact versions, turn on Framework's optional renv
                            integration. A snapshot records the versions in use, and a restore
                            reinstalls them on another computer.
                        </p>
                        <p>
                            Package versions are one part of reproducing an analysis. R itself,
                            system libraries, and the data still need their own records.
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-4">
<x-code-panel title="settings.yml">
packages:
  - name: dplyr
    auto_attach: <span class="{{ $s }}">true</span>
  - name: ggplot2
    auto_attach: <span class="{{ $s }}">true</span></x-code-panel>
<x-code-panel title="R console">
framework::renv_enable()
framework::packages_snapshot()  <span class="{{ $c }}"># record versions</span>
framework::packages_restore()   <span class="{{ $c }}"># reinstall them</span></x-code-panel>
                </div>
            </div>
        </section>

        {{-- Settings --}}
        <section id="cli" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div class="lg:order-2">
                    <h2 class="{{ $h2 }}">Work from the command line</h2>
                    <div class="{{ $prose }}">
                        <p>
                            <code class="{{ $code }}">fw</code> is a single program for macOS, Linux,
                            and Windows. It lists your projects and blueprints, sets up projects from
                            Framework Cloud, and checks a project's setup. It also builds Stata
                            projects (scaffolding only).
                        </p>
                        <p>
                            AI agents can use it too. Commands print JSON with
                            <code class="{{ $code }}">--json</code>, and each kind of failure has its own
                            exit code. To sign in, <code class="{{ $code }}">fw login</code> prints a link
                            for you to approve in your browser, so the agent never handles your token.
                        </p>
                        <p>
                            <code class="{{ $code }}">fw</code> can also publish a rendered notebook and
                            send the project's settings to Framework Cloud. It sends only the files you
                            name, and asks before it sends anything from a folder named private.
                        </p>
                    </div>
                </div>
<x-code-panel title="Terminal" class="lg:order-1">
<span class="{{ $c }}"># You approve the link. The agent never sees your token.</span>
$ fw login --yes --json
{"expires_in": 600,
 "user_code": <span class="{{ $s }}">"QGMK-JE6Q"</span>,
 "verification_url":
   <span class="{{ $s }}">"https://framework.pub/device?code=QGMK-JE6Q"</span>}

$ fw projects list --json | jq -r '.projects[].slug'
vaccine-study

$ fw new --from <span class="{{ $s }}">vaccine-study</span>
$ cd vaccine-study &amp;&amp; fw project bootstrap
$ fw project push-spec
$ fw publish outputs/notebooks/results.html \
    --slug <span class="{{ $s }}">results</span> --yes
Published results, version 1.
Link: https://framework.pub/d/lh2hr0gjulf3vj1fzhmscgr2</x-code-panel>
            </div>
        </section>

        {{-- Copy and share --}}
        <section id="copy" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div>
                    <h2 class="{{ $h2 }}">Copy and share project setups</h2>
                    <div class="{{ $prose }}">
                        <p>
                            Copy any blueprint you can see, then change its folders, packages,
                            templates, or skills.
                        </p>
                        <p>
                            Each save creates a new version. Existing projects keep the version
                            they were made with.
                        </p>
                        <p>Skills can also be imported from a GitHub repository.</p>
                    </div>
                </div>
                <dl class="divide-y divide-slate-200 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                    @foreach ([
                        ['Private', 'Only you can see and use it.'],
                        ['Unlisted', 'Anyone with the link can use and copy it.'],
                        ['Public', 'Everyone on the server can find, use, and copy it.'],
                    ] as [$level, $meaning])
                        <div class="flex items-center gap-4 px-5 py-4">
                            <dt class="w-20 shrink-0"><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $level }}</span></dt>
                            <dd class="text-sm text-slate-600 dark:text-slate-400">{{ $meaning }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- GitHub --}}
        <section id="github" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div class="lg:order-2">
                    <h2 class="{{ $h2 }}">Create projects on GitHub</h2>
                    <div class="{{ $prose }}">
                        <p>
                            Connect GitHub and install the Framework app on your account or
                            organization. The app can reach only the repositories it creates or
                            that you give it.
                        </p>
                        <p>
                            Repositories are private by default. Projects from the sensitive-data
                            blueprint must be private.
                        </p>
                    </div>
                </div>
                <ol class="flex flex-col gap-5 rounded-xl border border-slate-200 p-6 lg:order-1 dark:border-slate-800">
                    @foreach ([
                        'Create a project and choose On GitHub.',
                        'Framework Cloud creates the repository.',
                        'A one-time GitHub Action builds the project files and commits them.',
                        'Clone the repository and start working.',
                    ] as $i => $step)
                        <li class="flex items-start gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">{{ $i + 1 }}</span>
                            <span class="pt-0.5 text-slate-700 dark:text-slate-300">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Publishing --}}
        <section id="publishing" class="{{ $section }}">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16 [&>*]:min-w-0">
                <div>
                    <h2 class="{{ $h2 }}">Publish and share notebooks</h2>
                    <div class="{{ $prose }}">
                        <p>
                            <code class="{{ $code }}">publish()</code> renders a Quarto notebook and
                            uploads it. Publishing the same file again updates the page at the
                            same link.
                        </p>
                        <p>
                            The Published page in Framework Cloud lists your notebooks. If a
                            project has its own S3 bucket, <code class="{{ $code }}">publish()</code>
                            uploads there instead.
                        </p>
                    </div>
                </div>
<x-code-panel title="R console" wrap>
framework::publish(<span class="{{ $s }}">"notebooks/analysis.qmd"</span>)
<span class="{{ $c }}">#> Published: https://framework.pub/d/q7w2k9m4x8c1v5b3n6z0p2rt</span></x-code-panel>
            </div>
        </section>

    </main>

    <x-site-footer />

</body>
</html>
