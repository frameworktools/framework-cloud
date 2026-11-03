@props(['table', 'level' => null, 'method' => null, 'exponentiate' => null])
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
            ], fn ($value) => $value !== null) + ['interval' => $interval];
        }
    }
    // The intercept is usually on another scale and squeezes the other terms, as in phiz's coefplot()
    $isIntercept = fn (string $term) => in_array($term, ['(Intercept)', 'Intercept', 'const'], true)
        || (bool) preg_match('/: (\(Intercept\)|Intercept|const)$/', $term);
    $withIntercept = $rows;
    $rows = array_values(array_filter($rows, fn ($row) => ! $isIntercept($row['term'])));
    $interceptHidden = count($rows) < count($withIntercept) && $rows !== [];
    if ($rows === []) $rows = $withIntercept;
    $count = count($rows);
    $rows = array_slice($rows, 0, 30);
    $anyInterval = in_array(true, array_column($rows, 'interval'), true);

    $percent = is_numeric($level) && $level > 0 && $level < 1 ? rtrim(rtrim(number_format($level * 100, 1), '0'), '.').'%' : null;
    $methodLabel = ['wald-t' => 'Wald t', 'wald-normal' => 'Wald normal'][$method] ?? $method;
    $intervalLabel = ($percent ? "{$percent} confidence interval" : 'Confidence interval').($methodLabel ? " ({$methodLabel})" : '');
    $ci = $anyInterval && $percent ? " ({$percent} CI)" : '';

    // Ratios read on a log scale around 1. Estimates too large to exponentiate stay on the model scale.
    $largest = $rows === [] ? 0 : max(array_map(fn ($row) => max(abs($row['lower']), abs($row['upper'])), $rows));
    $ratio = in_array($exponentiate, ['Odds ratio', 'Rate ratio', 'Hazard ratio', 'Time ratio'], true) && $largest < 50 ? $exponentiate : null;
    $scaleLabel = ['Odds ratio' => 'Log odds', 'Rate ratio' => 'Log rate', 'Hazard ratio' => 'Log hazard', 'Time ratio' => 'Log time'][$ratio] ?? 'Estimate';

    $styled = fn ($plot) => $plot
        ->theme(font: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif', baseSize: 12, panelBorder: 'none', grid: true,
            gridColor: '#e2e8f0', textColor: '#475569', ticks: false,
            config: ['axis' => ['titleFontWeight' => 'normal', 'titleColor' => '#64748b', 'titlePadding' => 12, 'labelPadding' => 8, 'domain' => false], 'axisY' => ['grid' => false]])
        ->width('container')
        ->height(max(120, count($rows) * 34));
    $forest = fn (array $data, int|float $reference, string $axis) => \Phiz\Plot::of($data)
        ->aes(x: 'estimate', y: \Phiz\field('term', 'nominal', sort: array_column($data, 'term')), xmin: 'lower', xmax: 'upper')
        ->vline($reference, linetype: 'dashed', color: '#94a3b8')
        ->pointrange(color: '#0ea5e9', size: 80, linewidth: 2)
        ->labs(x: $axis, y: null)
        ->tooltip(['term', 'estimate', ...($anyInterval ? ['lower', 'upper'] : []),
            ...array_values(array_filter(['std_error', 'p_value'], fn ($key) => array_column($data, $key) !== []))]);

    $specs = [];
    if ($rows !== []) {
        // The reference line is always in view, with a little room on both sides
        $low = min(0, ...array_column($rows, 'lower'));
        $high = max(0, ...array_column($rows, 'upper'));
        $pad = $high > $low ? ($high - $low) * 0.06 : 1;
        $specs['model'] = $styled($forest($rows, 0, $scaleLabel.$ci)->xlim($low - $pad, $high + $pad))->toArray();

        if ($ratio) {
            $ratioRows = array_map(fn ($row) => ['estimate' => exp($row['estimate']), 'lower' => exp($row['lower']), 'upper' => exp($row['upper'])]
                + array_diff_key($row, ['std_error' => true]), $rows);
            $lowRatio = min(1, ...array_column($ratioRows, 'lower'));
            $highRatio = max(1, ...array_column($ratioRows, 'upper'));
            $specs['ratio'] = $styled($forest($ratioRows, 1, $ratio.$ci)->scaleX(type: 'log', limits: [$lowRatio / 1.15, $highRatio * 1.15]))->toArray();
        }
    }
    $chartId = 'coefficient-plot-'.substr(md5(json_encode($table['title'] ?? '')), 0, 6);
@endphp
@if ($specs)
    <section data-coefficient-plot class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="{{ $chartId }}-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 id="{{ $chartId }}-title" class="font-semibold text-slate-900 dark:text-slate-100">Coefficient forest plot</h2>
            @if ($ratio)
                <div role="group" aria-label="Scale" class="inline-flex rounded-md border border-slate-300 p-0.5 text-xs dark:border-slate-600">
                    @foreach (['ratio' => $ratio, 'model' => $scaleLabel] as $scale => $label)
                        <button type="button" data-scale="{{ $scale }}" aria-pressed="{{ $scale === 'ratio' ? 'true' : 'false' }}" class="rounded px-2.5 py-1 font-medium text-slate-600 aria-pressed:bg-sky-50 aria-pressed:text-sky-700 dark:text-slate-300 dark:aria-pressed:bg-sky-950 dark:aria-pressed:text-sky-300">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
            Points are estimates.
            @if ($anyInterval) Lines are the {{ lcfirst($intervalLabel) }}. @endif
            @if ($ratio)
                <span data-scale-note="ratio">{{ $ratio }}s are the exponentiated estimates, on a log scale. The dashed line marks 1, no difference. The table shows {{ lcfirst($scaleLabel) }}.</span>
                <span data-scale-note="model" hidden>The dashed line marks zero.</span>
            @else
                The dashed line marks zero.
            @endif
            @if ($interceptHidden) The intercept is not shown. @endif
            Hover a term for its exact values.
        </p>
        <x-phiz :plot="$specs['ratio'] ?? $specs['model']" data-coefficient-chart class="mt-3 w-full" role="img" aria-label="Coefficient forest plot. Exact values appear in the coefficient table." />
        @if ($ratio)
            <script type="application/json" data-coefficient-specs>@json($specs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
        @endif
        @if ($count > 30)<p class="mt-2 text-xs text-slate-500">The first 30 terms are shown. See the coefficient table for all loaded terms.</p>@endif
    </section>
@endif
