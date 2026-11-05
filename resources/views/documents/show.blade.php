<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $document->title }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
    @if (in_array($preview['type'], ['table', 'representation']))
        @vite(['resources/js/artifact-reader.js'])
    @endif
</head>
<body class="flex h-full flex-col bg-white dark:bg-slate-950">
    @php
        $embedded = request()->boolean('embed');
        $structured = in_array($preview['type'], ['representation', 'chart', 'image'], true);
    @endphp
    @unless ($embedded)
    <header class="border-b border-slate-200 bg-white px-4 {{ $structured ? 'py-4 sm:px-6' : 'py-2.5' }} dark:border-slate-700 dark:bg-slate-800">
        <div class="flex w-full flex-wrap items-center justify-between gap-4 {{ $structured ? 'mx-auto max-w-4xl' : '' }}">
        <div class="min-w-0 flex-1 basis-64">
            <h1 class="break-words font-semibold text-slate-900 dark:text-slate-100 {{ $structured ? 'text-lg' : 'text-base' }}">{{ $document->title }}</h1>
            <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                {{ $project->name }} &middot;
                @if ($version->number === $latest)
                    updated {{ ($version->created_at ?? $document->updated_at)->diffForHumans() }}
                @else
                    <span class="font-medium text-sky-700 dark:text-sky-300">Version {{ $version->number }} of {{ $latest }}</span>,
                    published {{ $version->created_at?->toFormattedDateString() }}
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            @if ($versions->count() > 1)
                <label class="sr-only" for="version">Version</label>
                <select id="version" onchange="location.href = this.value"
                    class="rounded-md border border-slate-300 bg-white py-1 pr-7 pl-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    @foreach ($versions as $v)
                        <option value="{{ $v['url'] }}" @selected($v['number'] === $version->number)>
                            Version {{ $v['number'] }}{{ $v['number'] === $latest ? ' (latest)' : '' }} &middot; {{ $v['date']?->toFormattedDateString() }}
                        </option>
                    @endforeach
                </select>
            @endif
            @unless ($structured)<a href="{{ $version->contentUrl() }}" class="text-sky-600 hover:underline dark:text-sky-400">Open original</a>@endunless
            <a href="{{ $version->downloadUrl() }}" class="text-sky-600 hover:underline dark:text-sky-400">Download {{ $preview['type'] === 'representation' && empty($preview['data_file']) ? 'JSON' : ($version->original_filename ?? 'artifact') }}</a>
        </div>
        </div>
    </header>

    @if ($document->description)
        <div class="px-4 {{ $structured ? 'sm:px-6' : '' }}"><p class="py-3 text-sm text-slate-600 dark:text-slate-300 {{ $structured ? 'mx-auto max-w-4xl' : '' }}">{{ $document->description }}</p></div>
    @endif
    @endunless
    @if ($preview['type'] === 'image')
        <main class="grow overflow-auto {{ $embedded ? 'bg-white px-4 py-6 sm:px-8 sm:py-8' : 'p-4 sm:p-6' }}">
            {{-- Figures assume a white background, so they sit on a white card in both themes --}}
            <figure class="mx-auto {{ $embedded ? 'w-full' : 'w-fit' }} max-w-full bg-white {{ $embedded ? '' : 'rounded-xl border border-slate-200 p-3 shadow-sm dark:border-slate-700' }}">
                <img src="{{ $version->contentUrl() }}" alt="{{ $embedded ? ($version->original_filename ?? 'Published figure') : $document->title }}" class="mx-auto block h-auto {{ $embedded ? 'w-full max-w-3xl' : 'max-h-[80vh] w-auto max-w-full' }}">
            </figure>
        </main>
    @elseif ($preview['type'] === 'frame')
    <iframe sandbox="allow-scripts allow-downloads" referrerpolicy="no-referrer" src="{{ $version->contentUrl() }}" title="{{ $document->title }}" class="w-full grow border-0 bg-white"></iframe>
    @elseif ($preview['type'] === 'table')
        <main class="grow overflow-auto p-6">
            @if ($preview['truncated'])<p class="mb-3 text-sm text-slate-600 dark:text-slate-300">Preview limited to 100 rows, 50 columns, and 1 MB. Download the artifact to read the full table.</p>@endif
            @php
                $rows = $preview['rows'];
                $headers = array_shift($rows) ?? [];
                $columns = [];
                foreach ($headers as $index => $name) {
                    $values = array_filter(array_column($rows, $index), fn ($value) => $value !== null && $value !== '');
                    $numeric = count($values) > 0 && count(array_filter($values, fn ($value) => preg_match('/^[+-]?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/D', $value))) === count($values);
                    $columns[] = ['name' => $name ?? '', 'type' => $numeric ? 'number' : 'string'];
                }
                $table = ['title' => $embedded ? 'Table' : ($document->title ?? 'Table'), 'columns' => $columns, 'rows' => $rows, 'truncated' => $preview['truncated']];
            @endphp
            <x-artifact-table :table="$table" />
        </main>
    @elseif ($preview['type'] === 'representation')
        @php
            // The object's own class, e.g. lm() for a model fit with lm(), GLM for statsmodels' GLMResultsWrapper
            $sourceClass = \Illuminate\Support\Str::afterLast($preview['source']['class'][0] ?? '', '.');
            $sourceClass = preg_replace('/(?<=.)(ResultsWrapper|Results|Wrapper)$/', '', $sourceClass);
            $fittedBy = ['lm', 'glm', 'aov', 'nls', 'loess', 'coxph', 'survreg', 'polr', 'multinom', 'betareg', 'gam', 'glm.nb', 'lmer', 'glmer', 'feols', 'feglm', 'fepois'];
            // Classes named differently from the function that fits them
            $sourceClass = $preview['source']['language'] === 'r' ? (['lmerMod' => 'lmer', 'glmerMod' => 'glmer', 'negbin' => 'glm.nb'][$sourceClass] ?? $sourceClass) : $sourceClass;
            $sourceLabel = $preview['source']['language'] === 'r' && in_array($sourceClass, $fittedBy, true) ? $sourceClass.'()' : $sourceClass;
            $languageLabel = ['r' => 'R', 'python' => 'Python', 'stata' => 'Stata'][$preview['source']['language']] ?? null;
            // A published CSV or TSV shows just its format
            if ($languageLabel === null) {
                [$languageLabel, $sourceLabel] = [strtoupper($preview['source']['class'][0] ?? 'Data'), null];
            }
            $model = $preview['model'] ?? null;
            $modelType = ['linear' => 'Linear model', 'glm' => 'Generalized linear model', 'mixed' => 'Mixed-effects model', 'survival' => 'Survival model',
                'ordinal' => 'Ordinal regression', 'multinomial' => 'Multinomial regression', 'additive' => 'Generalized additive model',
                'fixed_effects' => 'Fixed-effects regression', 'quantile' => 'Quantile regression', 'linear_ml' => 'Linear model',
                'tree_ensemble' => 'Tree-based model'][$model['type'] ?? ''] ?? null;
            $modelDetail = trim(implode(', ', array_filter([
                isset($model['family']) && ($model['family'] !== 'gaussian' || ($model['type'] ?? '') === 'glm') ? (['poisson' => 'Poisson', 'weibull' => 'Weibull', 'gaussian' => 'Gaussian'][$model['family']] ?? $model['family']).' family' : null,
                isset($model['link']) && $model['link'] !== 'identity' ? $model['link'].' link' : null,
            ])));
            $metrics = $preview['metrics'] ?? [];
            $metricValues = collect($metrics)->pluck('value', 'name');
            $tables = $preview['tables'];
            $coefficientIndex = null;
            foreach ($tables as $index => $table) {
                if (($table['role'] ?? null) === 'coefficients') { $coefficientIndex = $index; break; }
            }
            if ($coefficientIndex === null && $preview['kind'] === 'model' && isset($tables[0]) && ($tables[0]['role'] ?? null) === null) {
                $coefficientIndex = 0;
            }
            $tableKind = fn (array $table) => in_array($table['role'] ?? null, ['coefficients', 'random_effects', 'smooth_terms', 'anova', 'importance'], true) || $preview['kind'] !== 'table';
        @endphp
        <main class="grow overflow-auto p-4 sm:p-6">

            <div class="mx-auto flex {{ $embedded ? '' : 'max-w-4xl' }} flex-col gap-5">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-500 dark:text-slate-400">
                    <span title="{{ implode(', ', $preview['source']['class']) }}" class="rounded bg-sky-50 px-2 py-1 font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                        {{ $languageLabel }}@if ($sourceLabel) &middot; <span class="font-mono">{{ $sourceLabel }}</span>@endif
                    </span>
                    @if ($modelType)
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ $modelType }}@if ($modelDetail), {{ $modelDetail }}@endif</span>
                    @endif
                </div>
                @if ($preview['truncated'] && empty($preview['data_file']))
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">This is a limited preview. Some tables, metrics, source labels, or long values may be shortened or omitted. Download the original JSON for the complete representation.</p>
                @endif

                @if ($preview['kind'] === 'test')
                    <x-artifact-test :test="$preview['test']" />
                @endif

                @if (!empty($preview['formula']) || isset($model['response']))
                    <div>
                        <h2 class="mb-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ !empty($preview['formula']) ? 'Model formula' : 'Outcome' }}</h2>
                        <pre class="overflow-x-auto rounded-lg bg-slate-50 p-4 font-mono text-sm text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">{{ $preview['formula'] ?? $model['response'] }}</pre>
                    </div>
                @endif

                @if ($coefficientIndex !== null)
                    <x-artifact-table :table="$tables[$coefficientIndex]" :id="'artifact-table-'.$coefficientIndex" :model="true" />
                    <x-artifact-coefficients :table="$tables[$coefficientIndex]" :level="$metricValues['confidence_level'] ?? null"
                        :method="$metricValues['interval_method'] ?? null" :exponentiate="$model['exponentiate'] ?? null" />
                @endif

                @foreach ($tables as $index => $table)
                    @continue($index === $coefficientIndex)
                    @if (($table['role'] ?? null) === 'random_effects')
                        <x-artifact-random-effects :table="$table" />
                        @continue
                    @endif
                    @if (($table['role'] ?? null) === 'importance')
                        <x-artifact-importance :table="$table" :type="$metricValues['importance_type'] ?? null" />
                    @endif
                    @if ($preview['kind'] === 'table' && ($table['role'] ?? 'data') === 'data')
                        <x-artifact-profile :table="$table" />
                    @endif
                    @php
                        $titles = ['random_effects' => 'Random effects', 'smooth_terms' => 'Smooth terms', 'importance' => 'Importance values'];
                        $table['title'] = ($table['title'] ?? 'Table') === 'Table' && isset($titles[$table['role'] ?? '']) ? $titles[$table['role']] : ($table['title'] ?? 'Table');
                        if ($embedded && $preview['kind'] === 'table') $table['title'] = 'Table';
                    @endphp
                    <x-artifact-table :table="$table" :id="'artifact-table-'.$index" :model="$tableKind($table)" />
                @endforeach

                <x-artifact-model-summary :metrics="$metrics" :title="$preview['kind'] === 'model' ? 'Model summary' : 'Summary'" />
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if (! empty($preview['data_file']))
                        Published data file. Column types were inferred from its values. Download {{ $version->original_filename }} for every row exactly as published.
                    @else
                        Published {{ ['model' => 'model summary', 'test' => 'test result', 'table' => 'table'][$preview['kind']] }}.
                        Download the original JSON to keep every value, column type, and source detail.
                    @endif
                </p>
            </div>
        </main>
    @elseif ($preview['type'] === 'chart')
        <main class="grow overflow-auto p-4 sm:p-6">
            <figure class="mx-auto {{ $embedded ? '' : 'max-w-4xl' }} rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <iframe data-chart-frame data-src="{{ $version->chartUrl() }}" sandbox="allow-scripts" referrerpolicy="no-referrer" title="{{ $document->title }}" class="h-96 w-full border-0"></iframe>
            </figure>
            <script>
                // The chart draws in a sandbox that can't see this page, so it is told the theme
                (() => {
                    const frame = document.querySelector('[data-chart-frame]');
                    const load = () => {
                        const dark = document.documentElement.classList.contains('dark');
                        frame.src = frame.dataset.src + (dark ? '?dark=1' : '');
                    };
                    load();
                    new MutationObserver(load).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    // The chart reports its height so the frame fits it
                    window.addEventListener('message', (event) => {
                        if (event.source !== frame.contentWindow || typeof event.data?.chartHeight !== 'number') return;
                        frame.style.height = Math.min(4000, Math.max(120, Math.ceil(event.data.chartHeight))) + 'px';
                    });
                })();
            </script>
        </main>
    @elseif ($preview['type'] === 'text')
        <main class="grow overflow-auto p-6">
            @if ($preview['truncated'])<p class="mb-3 text-sm text-slate-600 dark:text-slate-300">Preview limited to 1 MB. Download the artifact to read the full file.</p>@endif
            <pre class="whitespace-pre-wrap break-words rounded bg-white p-4 text-sm text-slate-800">{{ $preview['text'] }}</pre>
        </main>
    @else
        <main class="grow p-8 text-center text-slate-600 dark:text-slate-300"><p>{{ $preview['message'] ?? 'This artifact is available to download.' }}</p></main>
    @endif
    @unless ($embedded)
    <footer class="shrink-0 border-t border-slate-200 px-4 py-3 {{ $structured ? 'sm:px-6' : '' }} dark:border-slate-800">
        <div class="flex items-center justify-between gap-4 text-xs text-slate-500 dark:text-slate-400 {{ $structured ? 'mx-auto max-w-4xl' : '' }}">
            <a href="{{ config('app.url') }}" class="hover:underline">Published with Framework Cloud</a>
            <x-theme-toggle />
        </div>
    </footer>
    @endunless
</body>
</html>
