@php
    // One analysis of the Palmer penguins data in each language: read the data, summarize it, fit a
    // model, plot it, and publish all four. Each output block is real console output, trimmed (… marks
    // a cut), from R and Python run locally and Stata 17 run on a cluster. The JSON files in
    // resources/examples/landing/setup hold the trimmed output; the figures are each language's own plot.
    // php artisan fw:homepage-example publishes the four linked artifacts.
    $example = config('framework.homepage_example_url');
    $base = $example ? preg_replace('#/[^/]+$#', '', $example) : null;
    $published = [
        ['kind' => 'Data', 'slug' => 'penguins', 'icon' => 'heroicon-o-circle-stack'],
        ['kind' => 'Table', 'slug' => 'mass-by-species', 'icon' => 'heroicon-o-table-cells'],
        ['kind' => 'Model', 'slug' => 'penguin-body-mass', 'icon' => 'heroicon-o-variable'],
        ['kind' => 'Figure', 'slug' => 'flipper-and-mass', 'icon' => 'heroicon-o-chart-bar'],
    ];
    $languages = [
        'r' => [
            'label' => 'R', 'path' => 'scripts/analysis.R',
            'code' => "library(framework)\nscaffold()\n\n# Read a dataset from the project catalog\npenguins <- data_read(\"inputs.raw.penguins\")\npenguins\n\n# Summarize, fit, and plot\nby_species <- penguins |>\n  group_by(species) |>\n  summarize(n = n(), mean_mass_g = mean(body_mass_g, na.rm = TRUE))\nby_species\n\nmodel <- lm(body_mass_g ~ flipper_length_mm + species, data = penguins)\nsummary(model)\n\nplot <- ggplot(penguins, aes(flipper_length_mm, body_mass_g)) +\n  geom_point(aes(color = species))\n\n# Publish each one\npublish(penguins, slug = \"penguins\")\npublish(by_species, slug = \"mass-by-species\")\npublish(model, slug = \"penguin-body-mass\")\npublish(plot, slug = \"flipper-and-mass\")",
            'labels' => ['penguins', 'by_species', 'summary(model)'],
        ],
        'python' => [
            'label' => 'Python', 'path' => 'scripts/analysis.py',
            'code' => "import framework as fw\nimport seaborn as sns\nimport statsmodels.formula.api as smf\n\nfw.scaffold()\n\n# Read a dataset from the project catalog\npenguins = fw.data_read(\"inputs.raw.penguins\")\nprint(penguins)\n\n# Summarize, fit, and plot\nby_species = penguins.groupby(\"species\").agg(\n    n=(\"species\", \"size\"), mean_mass_g=(\"body_mass_g\", \"mean\")\n)\nprint(by_species)\n\nformula = \"body_mass_g ~ flipper_length_mm + species\"\nmodel = smf.ols(formula, penguins).fit()\nprint(model.summary())\n\nplot = sns.scatterplot(\n    penguins, x=\"flipper_length_mm\", y=\"body_mass_g\", hue=\"species\"\n)\n\n# Publish each one\nfw.publish(penguins, slug=\"penguins\")\nfw.publish(by_species, slug=\"mass-by-species\")\nfw.publish(model, slug=\"penguin-body-mass\")\nfw.publish(plot, slug=\"flipper-and-mass\")",
            'labels' => ['print(penguins)', 'print(by_species)', 'print(model.summary())'],
        ],
        'stata' => [
            'label' => 'Stata', 'path' => 'analysis.do',
            'code' => "fw_project\n\n// Read a dataset from the project catalog\nfw_use inputs.raw.penguins, clear\ndestring bill_length_mm-body_mass_g, replace force\nencode species, generate(species_id)\nlist species island flipper_length_mm body_mass_g sex in 1/5\n\n// Summarize, fit, and plot\ntable species, statistic(frequency) ///\n    statistic(mean body_mass_g)\ncollect export \"\$fw_outputs_tables/mass-by-species.xlsx\", replace\n\nregress body_mass_g flipper_length_mm i.species_id\n\nseparate body_mass_g, by(species_id) veryshortlabel\nscatter body_mass_g? flipper_length_mm, ytitle(body_mass_g)\ngraph export \"\$fw_outputs_figures/flipper-and-mass.svg\", replace\n\n// Publish each one\nfw_publish \"\$fw_inputs_raw/penguins.csv\", slug(penguins)\nfw_publish \"\$fw_outputs_tables/mass-by-species.xlsx\", ///\n    slug(mass-by-species)\nfw_publish, estimates slug(penguin-body-mass)\nfw_publish \"\$fw_outputs_figures/flipper-and-mass.svg\", ///\n    slug(flipper-and-mass)",
            'labels' => ['. list … in 1/5', '. table species, …', '. regress body_mass_g flipper_length_mm i.species_id'],
        ],
        'cli' => [
            'label' => 'Command line', 'path' => 'Terminal',
            'code' => "cd penguin-study\n\n# Publish files saved by any analysis\nfw publish inputs/raw/penguins.csv --slug penguins\nfw publish outputs/tables/mass-by-species.csv\nfw publish outputs/models/penguin-body-mass.json\nfw publish outputs/figures/flipper-and-mass.svg",
        ],
    ];
    $card = 'overflow-hidden rounded-lg bg-slate-50 ring-1 ring-slate-200 dark:bg-white/[0.03] dark:ring-white/10';
    $cardHead = 'border-b border-slate-200 px-3 py-1.5 font-mono text-[11px] text-slate-500 dark:border-white/10';
    $pre = 'overflow-x-auto py-2 font-mono text-[11px] leading-[1.5] text-slate-700 dark:text-slate-300';
    $cli = "Publishing penguins.csv, 15 KB, to this project on framework.pub.\nUpload it? [y/N] y\nPublished penguins, version 1.\nLink: https://framework.pub/@examples/penguin-study/penguins\n…\nPublished mass-by-species, version 1.\n…\nPublished penguin-body-mass, version 1.\n…\nPublished flipper-and-mass, version 1.\nLink: https://framework.pub/@examples/penguin-study/flipper-and-mass";
@endphp
<section id="setup" class="scroll-mt-6 border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-balance text-3xl font-semibold tracking-tight">Framework works with R, Python, Stata, and the command line</h2>
        <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-400">The same analysis in each language. Read the penguins dataset from the project's data catalog, summarize it, fit a model, and publish each result to its own link.</p>
    </div>

    <x-landing-frame bare shadow="xl" class="mt-10">
    <div data-setup-tabs class="overflow-hidden rounded-xl bg-[#0c1526] text-slate-100 ring-1 ring-slate-400/20">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="min-w-0 border-b border-white/10 px-3 py-2.5 sm:px-4 lg:border-r">
            <div role="tablist" aria-label="Example language" class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg bg-white/5 p-0.5">
                @foreach ($languages as $key => $language)
                    <button type="button" role="tab" id="setup-tab-{{ $key }}" aria-controls="setup-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-setup-tab="{{ $key }}" class="shrink-0 rounded-md px-3 py-1 text-xs font-medium whitespace-nowrap text-slate-400 hover:text-white aria-selected:bg-slate-700 aria-selected:text-white">{{ $language['label'] }}</button>
                @endforeach
            </div>
            </div>
            <div class="hidden items-center justify-end border-b border-slate-200 bg-white px-4 lg:flex dark:border-white/10 dark:bg-black/25"><span class="text-[11px] text-slate-500">Palmer penguins data</span></div>
        </div>
        <div>
            @foreach ($languages as $key => $language)
                @php $output = isset($language['labels']) ? json_decode(file_get_contents(resource_path("examples/landing/setup/$key.json")), true) : null; @endphp
                <div role="tabpanel" id="setup-panel-{{ $key }}" aria-labelledby="setup-tab-{{ $key }}" data-setup-panel="{{ $key }}" @unless ($loop->first) hidden @endunless class="grid min-w-0 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    {{-- Code --}}
                    <div class="min-w-0 border-white/10 max-lg:border-b lg:border-r">
                        <p class="border-b border-white/5 px-4 py-2 font-mono text-[11px] text-slate-500">{{ $language['path'] }}</p>
                        <pre class="overflow-x-auto px-4 py-4 font-mono text-xs leading-[1.65]"><code>{!! \App\Support\ExampleCode::highlight($language['code'], false) !!}</code></pre>
                    </div>

                    {{-- Output: real console output, trimmed --}}
                    <div class="min-w-0 space-y-3 bg-white p-4 text-slate-900 dark:bg-black/25 dark:text-slate-100">
                        @if ($output)
                            @foreach ([['Data', 0, 'data', false], ['Model', 2, 'model', false]] as [$title, $label, $part, $open])
                                <details @if ($open) open @endif class="group/section {{ $card }}">
                                    <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-100 group-open/section:border-b group-open/section:border-slate-200 dark:text-slate-300 dark:hover:bg-white/[0.03] dark:group-open/section:border-white/10 [&::-webkit-details-marker]:hidden">
                                        <x-heroicon-m-chevron-right class="size-3.5 shrink-0 text-slate-500 transition group-open/section:rotate-90" />
                                        <span class="font-medium">{{ $title }}</span>
                                        <span class="ml-auto truncate font-mono text-[11px] text-slate-500">{{ $language['labels'][$label] }}</span>
                                    </summary>
                                    <pre class="{{ $pre }} px-3"><code>{{ implode("\n", $output[$part]) }}</code></pre>
                                </details>
                            @endforeach
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="{{ $card }}"><p class="{{ $cardHead }}">{{ $language['labels'][1] }}</p><pre class="{{ $pre }} px-2.5"><code>{{ implode("\n", $output['table']) }}</code></pre></div>
                                <img src="/images/landing/setup/{{ $key }}-figure.svg" alt="Scatterplot of body mass against flipper length for 342 penguins, colored by species" loading="lazy" class="aspect-[3/2] w-full self-start rounded-lg bg-white object-contain p-1 ring-1 ring-slate-200 dark:ring-0">
                            </div>
                        @else
                            <div class="{{ $card }}"><pre class="{{ $pre }} px-3"><code>{{ $cli }}</code></pre></div>
                        @endif

                        {{-- Published --}}
                        <div class="{{ $card }}">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-3 py-1.5 dark:border-white/10"><span class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300">Published to Framework Cloud</span><span class="text-[11px] text-slate-500">version 1</span></div>
                            <ul>
                                @foreach ($published as $item)
                                    <li>
                                        <a @if ($base) href="{{ $base }}/{{ $item['slug'] }}" target="_blank" rel="noopener" @endif class="group flex items-center gap-2.5 border-t border-slate-200 px-3 py-1.5 text-[11px] hover:bg-slate-100 dark:border-white/5 dark:hover:bg-white/5">
                                            <x-dynamic-component :component="$item['icon']" class="size-3.5 shrink-0 text-slate-500 group-hover:text-emerald-600 dark:group-hover:text-emerald-300" />
                                            <span class="w-12 shrink-0 text-slate-500">{{ $item['kind'] }}</span>
                                            <span class="truncate font-mono text-slate-800 group-hover:text-slate-950 dark:text-slate-200 dark:group-hover:text-white">{{ $item['slug'] }}</span>
                                            @if ($base)<span class="ml-auto shrink-0 text-slate-500 group-hover:text-emerald-600 dark:group-hover:text-emerald-300">(Click to view)</span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    </x-landing-frame>
    <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">Connect to Framework Cloud to reuse project settings and publish updates at the same link.</p>
</section>
