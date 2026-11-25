<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }}</title>
    @php
        // Search engines index the newest version of a public artifact with no password, at its share link
        $isLatest = $version->number === $latest;
        $indexable = $isLatest && $document->visibility === 'public' && ! $document->isPasswordProtected() && ! request()->boolean('embed');
        $owner = $project->user;
        $artifactKind = $version->kind ?? $document->kind ?? 'html';
        // Without a description, a DAG or tree says what it shows ("Causal diagram of 4 variables…")
        $drawnAlt = in_array($preview['type'], ['dag', 'tree'], true) ? \App\Support\EmbedAlt::for($preview['type'], '', $preview).' ' : '';
        $artifactDescription = \App\Support\Seo::describe($document->description)
            ?? $drawnAlt.\App\Support\ShareImage::kindLabel($version).' from '.$project->name.($owner && $owner->profile_public ? ' by '.$owner->name : '').', published on Framework.';
        $artifactUrl = $isLatest ? $document->shareUrl() : url()->current();
        $artifactType = match ($artifactKind) {
            'table', 'data' => 'Dataset',
            'book' => 'Book',
            'report', 'notebook', 'html', 'text', 'pdf' => 'Article',
            default => 'CreativeWork',
        };
        $artifactSchema = [
            '@type' => $artifactType,
            '@id' => $artifactUrl.'#artifact',
            ($artifactType === 'Article' ? 'headline' : 'name') => $document->title ?? $document->slug,
            'description' => $artifactDescription,
            'url' => $artifactUrl,
            'author' => \App\Support\Seo::person($owner),
            'dateCreated' => \App\Support\Seo::date($document->created_at),
            'dateModified' => \App\Support\Seo::date($version->created_at ?? $document->updated_at),
            'version' => (string) $version->number,
            'encodingFormat' => $version->content_type ?? $document->content_type,
            'isAccessibleForFree' => true,
            'publisher' => ['@id' => \App\Support\Seo::home().'#organization'],
            'distribution' => $artifactType === 'Dataset' && $indexable
                ? [['@type' => 'DataDownload', 'encodingFormat' => $version->content_type ?? $document->content_type, 'contentUrl' => route('documents.download', $document->key)]]
                : null,
        ];
    @endphp
    <x-share-meta :title="($document->title ?? $document->slug).' · Framework'" :description="$artifactDescription" type="article" :canonical="$artifactUrl" :noindex="! $indexable"
        :schema="[\App\Support\Seo::organization(), $artifactSchema]"
        :image="\App\Support\ShareImage::available($document) ? ['url' => \App\Support\ShareImage::url($version), 'alt' => \App\Support\ShareImage::alt($version)] : null" />
    @if ($embedLinks ?? null)
        <link rel="alternate" type="application/json+oembed" href="{{ $embedLinks['oembed'] }}" title="{{ $document->title }}">
        <link rel="alternate" type="application/vnd.framework.embed+json" href="{{ $embedLinks['embed'] }}">
    @endif
    <x-theme-script />
    @vite(['resources/css/app.css'])
    @if (in_array($preview['type'], ['table', 'representation']))
        @vite(['resources/js/artifact-reader.js'])
    @endif
</head>
<body class="flex h-full flex-col bg-white dark:bg-slate-950">
    @php
        $embedded = request()->boolean('embed');
        $structured = in_array($preview['type'], ['representation', 'chart', 'image', 'code_snippet', 'dag', 'tree'], true);
        // A figure published with a plot description draws as a live chart
        $liveFigure = $preview['type'] === 'image' && \App\Support\PlotDescription::spec($version) !== null;
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
            <x-present-link :target="$document" :title="$document->title" />
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
            <a href="{{ $version->downloadUrl() }}" class="text-sky-600 hover:underline dark:text-sky-400">Download {{ ($preview['type'] === 'representation' && empty($preview['data_file'])) || in_array($preview['type'], ['code_snippet', 'tree'], true) || ($preview['type'] === 'dag' && ! \App\Support\Dags\ArtifactDag::isDagittyFile($version->original_filename)) ? 'JSON' : ($version->original_filename ?? 'artifact') }}</a>
        </div>
        </div>
    </header>

    @if ($document->description)
        <div class="px-4 {{ $structured ? 'sm:px-6' : '' }}"><p class="{{ $structured ? 'pt-8 sm:pt-10' : 'py-3' }} text-sm text-slate-600 dark:text-slate-300 {{ $structured ? 'mx-auto max-w-4xl' : '' }}">{{ $document->description }}</p></div>
    @endif
    @endunless
    @if ($preview['type'] === 'image' && ! $liveFigure)
        <main class="grow overflow-auto {{ $embedded ? 'bg-white px-4 py-6 sm:px-8 sm:py-8' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">
            {{-- Figures assume a white background, so they sit on a white card in both themes --}}
            <figure class="mx-auto {{ $embedded ? 'w-full' : 'w-fit' }} max-w-full bg-white {{ $embedded ? '' : 'rounded-xl border border-slate-200 p-3 shadow-sm dark:border-slate-700' }}">
                <img src="{{ $version->contentUrl() }}" alt="{{ $embedded ? ($version->original_filename ?? 'Published figure') : $document->title }}" class="mx-auto block h-auto {{ $embedded ? 'w-full max-w-3xl' : 'max-h-[80vh] w-auto max-w-full' }}">
            </figure>
        </main>
    @elseif ($preview['type'] === 'frame')
    <iframe sandbox="allow-scripts allow-downloads" referrerpolicy="no-referrer" src="{{ $version->contentUrl() }}" title="{{ $document->title }}" class="w-full grow border-0 bg-white"></iframe>
    @elseif ($preview['type'] === 'table')
        <main class="grow overflow-auto p-6">
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
            <x-artifact-table :table="$table" :full="['url' => $version->downloadUrl(), 'label' => \App\Support\DataFile::delimiter($version->original_filename, $version->content_type) === chr(9) ? 'Download TSV' : 'Download CSV']" />
        </main>
    @elseif ($preview['type'] === 'representation')
        @php
            // The object's own class, e.g. lm() for a model fit with lm(), GLM for statsmodels' GLMResultsWrapper
            $sourceClass = \Illuminate\Support\Str::afterLast($preview['source']['class'][0] ?? '', '.');
            $sourceClass = preg_replace('/(?<=.)(ResultsWrapper|Results|Wrapper)$/', '', $sourceClass);
            $fittedBy = ['lm', 'glm', 'aov', 'nls', 'loess', 'coxph', 'survreg', 'polr', 'multinom', 'betareg', 'gam', 'glm.nb', 'lmer', 'glmer', 'feols', 'feglm', 'fepois', 'brm'];
            // Classes named differently from the function that fits them
            $sourceClass = $preview['source']['language'] === 'r' ? (['lmerMod' => 'lmer', 'glmerMod' => 'glmer', 'negbin' => 'glm.nb', 'brmsfit' => 'brm'][$sourceClass] ?? $sourceClass) : $sourceClass;
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
            // The common name, as embeds show it ("Logistic regression"); the family and link are then implied
            if ($model && ($commonName = \App\Support\ModelName::for($model)) !== null) {
                [$modelType, $modelDetail] = [$commonName, ''];
            } elseif (\App\Support\PosteriorTable::is($model)) {
                $modelType = $modelType ? 'Bayesian '.lcfirst($modelType) : 'Bayesian model';
            }
            $metrics = $preview['metrics'] ?? [];
            $bayesian = \App\Support\PosteriorTable::is($model ?: null);
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
        <main class="grow overflow-auto {{ $embedded ? 'p-4 sm:p-6' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">

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
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Some tables, metrics, source labels, or long values are shortened or left out here. Download JSON for everything that was published.</p>
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
                    @php
                        $ratio = \App\Support\RatioTable::ratio($model['exponentiate'] ?? null);
                        $plainTable = $tables[$coefficientIndex];
                        if ($bayesian) {
                            // A posterior's ratio scale comes from the package's exponentiated draws, or not at all
                            $ratio = $ratio && \App\Support\PosteriorTable::hasRatio($plainTable) ? $ratio : null;
                            $ratioTable = $ratio ? \App\Support\PosteriorTable::ratioScale($plainTable, $ratio) : null;
                            $logTable = $ratio ? \App\Support\PosteriorTable::modelScale($plainTable, \App\Support\RatioTable::SCALES[$ratio]) : null;
                            $plainTable = \App\Support\PosteriorTable::modelScale($plainTable);
                        } else {
                            $ratioTable = $ratio ? \App\Support\RatioTable::from($plainTable, $ratio) : null;
                            $logTable = $ratioTable ? \App\Support\RatioTable::renamed($plainTable, \App\Support\RatioTable::SCALES[$ratio]) : null;
                        }
                    @endphp
                    @if ($ratioTable)
                        {{-- One switch for the table and the coefficient plot; framework-embed has the same two views --}}
                        @foreach (['ratio' => $ratioTable, 'model' => $logTable] as $view => $viewTable)
                            <div data-scale-view="{{ $view }}" @if ($view === 'model') hidden @endif>
                                <x-artifact-table :table="$viewTable" :id="'artifact-table-'.$coefficientIndex.($view === 'ratio' ? '-ratio' : '')" :model="true">
                                    <x-slot:controls>
                                        <div data-scale-switch role="group" aria-label="Scale" class="inline-flex rounded-md border border-slate-300 p-0.5 text-sm dark:border-slate-600">
                                            @foreach (['ratio' => $ratio, 'model' => \App\Support\RatioTable::SCALES[$ratio]] as $scale => $label)
                                                <button type="button" data-scale="{{ $scale }}" aria-pressed="{{ $scale === $view ? 'true' : 'false' }}" class="rounded px-3 py-1 font-medium text-slate-600 focus-visible:outline-2 focus-visible:outline-sky-600 aria-pressed:bg-sky-50 aria-pressed:text-sky-700 dark:text-slate-300 dark:aria-pressed:bg-sky-950 dark:aria-pressed:text-sky-300">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                    </x-slot:controls>
                                </x-artifact-table>
                            </div>
                        @endforeach
                    @else
                        <x-artifact-table :table="$plainTable" :id="'artifact-table-'.$coefficientIndex" :model="true" />
                    @endif
                    <x-artifact-coefficients :table="$tables[$coefficientIndex]" :level="$metricValues['confidence_level'] ?? null"
                        :method="$metricValues['interval_method'] ?? null" :exponentiate="$model['exponentiate'] ?? null" :bayesian="$bayesian" />
                @endif

                @foreach ($bayesian ? \App\Support\PosteriorTable::warnings($tables, $metrics) : [] as $warning)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ $warning }}</p>
                @endforeach

                @foreach ($tables as $index => $table)
                    @continue($index === $coefficientIndex)
                    @if (($table['role'] ?? null) === 'random_effects' && $bayesian)
                        {{-- Group-level posteriors read like the coefficients --}}
                        @php $table = \App\Support\PosteriorTable::modelScale($table); $table['title'] = ($table['title'] ?? 'Table') === 'Table' ? 'Group-level effects' : $table['title']; @endphp
                        <x-artifact-table :table="$table" :id="'artifact-table-'.$index" :model="true" />
                        @continue
                    @endif
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
                    <x-artifact-table :table="$table" :id="'artifact-table-'.$index" :model="$tableKind($table)" :full="! empty($preview['data_file']) ? ['url' => $version->downloadUrl(), 'label' => \App\Support\DataFile::delimiter($version->original_filename, $version->content_type) === chr(9) ? 'Download TSV' : 'Download CSV'] : null" />
                @endforeach

                <x-artifact-model-summary :metrics="$metrics" :title="$preview['kind'] === 'model' ? 'Model summary' : 'Summary'" :bayesian="$bayesian" />
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
    @elseif ($preview['type'] === 'code_snippet')
        <main class="grow overflow-auto {{ $embedded ? 'p-4 sm:p-6' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">
            <div class="mx-auto {{ $embedded ? '' : 'max-w-4xl' }}">
                <x-artifact-snippet :snippet="$preview" :document="$document" :output-shown="request()->query('output') !== 'none'" />
            </div>
        </main>
    @elseif ($preview['type'] === 'dag')
        <main class="grow overflow-auto {{ $embedded ? 'p-4 sm:p-6' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">
            <div class="mx-auto {{ $embedded ? '' : 'max-w-4xl' }}">
                <x-artifact-dag :dag="$preview" />
            </div>
        </main>
    @elseif ($preview['type'] === 'tree')
        <main class="grow overflow-auto {{ $embedded ? 'p-4 sm:p-6' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">
            <div class="mx-auto {{ $embedded ? '' : 'max-w-4xl' }}">
                <x-artifact-tree :tree="$preview" />
            </div>
        </main>
    @elseif ($preview['type'] === 'chart' || $liveFigure)
        <main class="grow overflow-auto {{ $embedded ? 'p-4 sm:p-6' : 'px-4 pb-8 pt-8 sm:px-6 sm:pt-10' }}">
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
            @if ($preview['truncated'])<p class="mb-3 text-sm text-slate-600 dark:text-slate-300">Showing the first 1 MB. Download the file to read all of it.</p>@endif
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
