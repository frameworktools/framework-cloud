@php
    // An agent connected to Framework Cloud writing a report from published results. The
    // numbers are the real penguin model in resources/examples/landing/publish.json, so the
    // reply matches the model page step 03 shows.
    $points = [
        ['heroicon-o-document-text', 'Reports from your results', 'The agent reads your published tables, models, and figures, then writes a report around them. Every number comes from an artifact.'],
        ['heroicon-o-squares-plus', 'Projects and blueprints', 'Describe the project you need. The agent proposes a blueprint, shows its files, and saves it when you agree.'],
        ['heroicon-o-arrow-up-tray', 'Publishing from your computer', 'In Claude Code or Codex, the agent publishes notebooks and figures straight from your project.'],
        ['heroicon-o-shield-check', 'Access you choose', 'You approve each agent with full or read-only access. Reports stay private until you publish them.'],
    ];
    $steps = [
        ['artifacts_list', 'Penguin study: 3 artifacts'],
        ['artifacts_show', 'penguin-body-mass, version 2'],
        ['reports_create', 'Penguin body mass (private draft)'],
    ];
@endphp
<section id="agents" class="border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">AI agents</h2>
        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">Connect Claude or ChatGPT to Framework Cloud. Your agent can work with your projects, published results, and reports, and Framework's skills keep its code to your project's conventions.</p>
    </div>

    <div class="mt-10 grid items-start gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
        <div class="flex flex-col gap-6">
            @foreach ($points as [$icon, $title, $body])
                <div class="flex gap-3">
                    <x-dynamic-component :component="$icon" class="mt-0.5 size-5 shrink-0 text-slate-500" />
                    <div>
                        <h3 class="font-semibold">{{ $title }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $body }}</p>
                    </div>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-1 text-sm">
                <a href="{{ auth()->check() ? route('agents') : route('register') }}" class="rounded-md bg-sky-700 px-4 py-2 font-medium text-white hover:bg-sky-800">Connect an agent</a>
                <a href="{{ route('guide', 'ai-apps') }}" class="text-slate-700 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-300">Claude and ChatGPT setup &rarr;</a>
            </div>
        </div>

        {{-- The conversation, drawn like the code panels --}}
        <div class="min-w-0 overflow-hidden rounded-xl bg-[#0c1526] text-sm text-slate-200 shadow-panel-deep ring-1 ring-white/10 dark:shadow-panel-deep-dark">
            <div class="flex items-center justify-between border-b border-white/10 px-5 py-3 text-xs text-slate-400">
                <span>Claude, connected to Framework Cloud</span>
                <span class="hidden font-mono sm:inline">framework.pub/mcp</span>
            </div>
            <div class="flex flex-col gap-5 px-5 py-6">
                <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-sky-700 px-4 py-2.5 text-white">
                    Write a short report on the penguin body mass model from my published results.
                </div>
                <ol class="flex flex-col gap-1.5 font-mono text-xs" aria-label="Agent actions">
                    @foreach ($steps as [$tool, $detail])
                        <li class="flex min-w-0 items-center gap-2 text-slate-400">
                            <x-heroicon-m-check class="size-4 shrink-0 text-emerald-400" />
                            <span class="text-slate-200">{{ $tool }}</span>
                            <span class="truncate">{{ $detail }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="max-w-[92%] leading-relaxed">
                    <p>I drafted <span class="font-medium text-white">Penguin body mass</span> with the model table and the flipper figure from your project.</p>
                    <p class="mt-3">Each extra millimeter of flipper length goes with 40.7 g more body mass (SE 3.1), holding species fixed. The model explains 78% of the variance.</p>
                    <p class="mt-3">The draft is private. Publish it when you're ready.</p>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-white/5 px-4 py-3 ring-1 ring-white/10">
                    <x-heroicon-o-document-text class="size-5 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <div class="font-medium text-white">Penguin body mass</div>
                        <div class="truncate text-xs text-slate-400">Report · 2 artifacts · Private draft</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
