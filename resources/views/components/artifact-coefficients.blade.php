@props(['table', 'level' => null, 'method' => null, 'exponentiate' => null, 'bayesian' => false])
@php
    $names = array_column($table['columns'], 'name');
    $column = function (string ...$candidates) use ($names) {
        foreach ($candidates as $name) {
            if (($index = array_search($name, $names, true)) !== false) return $index;
        }
        return null;
    };
    $termIndex = $column('term');
    $componentIndex = $column('component');
    $estimateIndex = $column('estimate');
    $lowIndex = $column('conf_low', 'conf.low');
    $highIndex = $column('conf_high', 'conf.high');
    $errorIndex = $column('std_error', 'std.error');
    $pIndex = $column('p_value', 'p.value');
    // A posterior's ratio scale: the package's summaries of the exponentiated draws
    $ratioIndex = ['estimate' => $column('ratio_estimate'), 'lower' => $column('ratio_conf_low'), 'upper' => $column('ratio_conf_high')];
    $postRatio = $bayesian && ! in_array(null, $ratioIndex, true);
    $number = fn ($value) => (is_int($value) || is_float($value)) && is_finite((float) $value);

    $rows = [];
    if ($termIndex !== null && $estimateIndex !== null) {
        foreach ($table['rows'] as $row) {
            $estimate = $row[$estimateIndex] ?? null;
            if (! $number($estimate)) continue;
            // Ordinal thresholds are cut points, not effects
            if ($componentIndex !== null && ($row[$componentIndex] ?? null) === 'threshold') continue;
            $low = $lowIndex === null ? null : ($row[$lowIndex] ?? null);
            $high = $highIndex === null ? null : ($row[$highIndex] ?? null);
            $interval = $number($low) && $number($high) && $low <= $estimate && $estimate <= $high;
            $rows[] = array_filter([
                'term' => (string) ($row[$termIndex] ?? ''),
                'estimate' => $estimate,
                'lower' => $interval ? $low : $estimate,
                'upper' => $interval ? $high : $estimate,
                'std_error' => $errorIndex === null ? null : ($row[$errorIndex] ?? null),
                'p_value' => $pIndex === null ? null : ($row[$pIndex] ?? null),
            ], fn ($value) => $value !== null) + ['interval' => $interval]
                + ($postRatio ? ['ratio' => array_map(fn ($index) => $row[$index] ?? null, $ratioIndex)] : []);
        }
    }
    // The intercept is usually on another scale and squeezes the other terms, as in phiz's coefplot()
    $isIntercept = fn (string $term) => in_array($term, ['(Intercept)', 'Intercept', 'const', '_cons'], true)
        || (bool) preg_match('/: (\(Intercept\)|Intercept|const|_cons)$/', $term);
    $withIntercept = $rows;
    $rows = array_values(array_filter($rows, fn ($row) => ! $isIntercept($row['term'])));
    $interceptHidden = count($rows) < count($withIntercept) && $rows !== [];
    if ($rows === []) $rows = $withIntercept;
    $count = count($rows);
    $rows = array_slice($rows, 0, 30);
    $withIntercept = array_slice($withIntercept, 0, 30);
    $anyInterval = in_array(true, array_column($rows, 'interval'), true);

    $percent = is_numeric($level) && $level > 0 && $level < 1 ? rtrim(rtrim(number_format($level * 100, 1), '0'), '.').'%' : null;
    $methodLabel = ['wald-t' => 'Wald t', 'wald-normal' => 'Wald normal'][$method] ?? $method;
    $kindLabel = $bayesian ? 'credible interval' : 'confidence interval';
    $intervalLabel = ($percent ? "{$percent} {$kindLabel}" : ucfirst($kindLabel)).($methodLabel ? " ({$methodLabel})" : '');
    $ci = $anyInterval && $percent ? " ({$percent} ".($bayesian ? 'CrI' : 'CI').')' : '';

    // Ratios read on a log scale around 1. Estimates too large to exponentiate stay on the model scale.
    $largest = $rows === [] ? 0 : max(array_map(fn ($row) => max(abs($row['lower']), abs($row['upper'])), $rows));
    $ratio = in_array($exponentiate, ['Odds ratio', 'Rate ratio', 'Hazard ratio', 'Time ratio'], true) && $largest < 50 ? $exponentiate : null;
    $scaleLabel = ['Odds ratio' => 'Log odds', 'Rate ratio' => 'Log rate', 'Hazard ratio' => 'Log hazard', 'Time ratio' => 'Log time'][$ratio] ?? 'Estimate';

    $styled = fn ($plot, int $terms) => $plot
        ->theme(font: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif', baseSize: 12, panelBorder: 'none', grid: true,
            gridColor: '#e2e8f0', textColor: '#475569', ticks: false,
            config: ['axis' => ['titleFontWeight' => 'normal', 'titleColor' => '#64748b', 'titlePadding' => 12, 'labelPadding' => 8, 'domain' => false], 'axisY' => ['grid' => false]])
        ->width('container')
        ->height(max(120, $terms * 34));
    $forest = fn (array $data, int|float $reference, string $axis) => \Phiz\Plot::of($data)
        ->aes(x: 'estimate', y: \Phiz\field('term', 'nominal', sort: array_column($data, 'term')), xmin: 'lower', xmax: 'upper')
        ->vline($reference, linetype: 'dashed', color: '#94a3b8')
        ->pointrange(color: '#0ea5e9', size: 80, linewidth: 2)
        ->labs(x: $axis, y: null)
        ->tooltip(['term', 'estimate', ...($anyInterval ? ['lower', 'upper'] : []),
            ...array_values(array_filter(['std_error', 'p_value'], fn ($key) => array_column($data, $key) !== []))]);

    // One spec per scale, without the intercept and, when it was left out, with it ("model:intercept")
    $build = function (array $rows) use ($styled, $forest, $scaleLabel, $ci, $ratio) {
        $specs = [];
        // The reference line is always in view, with a little room on both sides
        $low = min(0, ...array_column($rows, 'lower'));
        $high = max(0, ...array_column($rows, 'upper'));
        $pad = $high > $low ? ($high - $low) * 0.06 : 1;
        $plain = array_map(fn ($row) => array_diff_key($row, ['ratio' => true]), $rows);
        $specs['model'] = $styled($forest($plain, 0, $scaleLabel.$ci)->xlim($low - $pad, $high + $pad), count($rows))->toArray();

        $largest = max(array_map(fn ($row) => max(abs($row['lower']), abs($row['upper'])), $rows));
        if ($ratio) {
            // An intercept too large to exponentiate keeps the model scale
            if ($largest >= 50) {
                $specs['ratio'] = $specs['model'];

                return $specs;
            }
            $ratioRows = array_map(fn ($row) => (isset($row['ratio']) && ! in_array(null, $row['ratio'], true)
                ? $row['ratio']
                : ['estimate' => exp($row['estimate']), 'lower' => exp($row['lower']), 'upper' => exp($row['upper'])])
                + array_diff_key($row, ['std_error' => true, 'ratio' => true]), $rows);
            $lowRatio = min(1, ...array_column($ratioRows, 'lower'));
            $highRatio = max(1, ...array_column($ratioRows, 'upper'));
            $specs['ratio'] = $styled($forest($ratioRows, 1, $ratio.$ci)->scaleX(type: 'log', limits: [$lowRatio / 1.15, $highRatio * 1.15]), count($rows))->toArray();
        }

        return $specs;
    };
    $specs = [];
    if ($rows !== []) {
        $specs = $build($rows);
        if ($interceptHidden) {
            foreach ($build($withIntercept) as $scale => $spec) {
                $specs["{$scale}:intercept"] = $spec;
            }
        }
    }
    $chartId = 'coefficient-plot-'.substr(md5(json_encode($table['title'] ?? '')), 0, 6);
@endphp
@if ($specs)
    <section data-coefficient-plot class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="{{ $chartId }}-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 id="{{ $chartId }}-title" class="font-semibold text-slate-900 dark:text-slate-100">Coefficient plot</h2>
            <div class="flex flex-wrap gap-2">
            @if ($interceptHidden)
                <div role="group" aria-label="Intercept" class="inline-flex rounded-md border border-slate-300 p-0.5 text-xs dark:border-slate-600">
                    @foreach (['without' => 'Without intercept', 'with' => 'With intercept'] as $value => $label)
                        <button type="button" data-intercept="{{ $value === 'with' ? 1 : 0 }}" aria-pressed="{{ $value === 'without' ? 'true' : 'false' }}" class="rounded px-2.5 py-1 font-medium text-slate-600 aria-pressed:bg-sky-50 aria-pressed:text-sky-700 dark:text-slate-300 dark:aria-pressed:bg-sky-950 dark:aria-pressed:text-sky-300">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
            </div>
        </div>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
            Points are estimates.
            @if ($anyInterval) Lines are the {{ lcfirst($intervalLabel) }}. @endif
            @if ($ratio)
                <span data-scale-note="ratio">{{ $ratio }}s are the exponentiated estimates, on a log scale. The dashed line marks 1, no difference.</span>
                <span data-scale-note="model" hidden>The dashed line marks zero.</span>
            @else
                The dashed line marks zero.
            @endif
            @if ($interceptHidden)<span data-intercept-note="0">The intercept is not shown.</span>@endif
            Hover a term for its exact values.
        </p>
        <x-phiz :plot="$specs['ratio'] ?? $specs['model']" data-coefficient-chart class="mt-3 w-full" role="img" aria-label="Coefficient plot. Exact values appear in the coefficient table." />
        @if ($ratio || $interceptHidden)
            <script type="application/json" data-coefficient-specs>@json($specs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
        @endif
        @if ($count > 30)<p class="mt-2 text-xs text-slate-500">The first 30 terms are shown. See the coefficient table for all loaded terms.</p>@endif
    </section>
@endif
