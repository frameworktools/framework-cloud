@props(['table', 'type' => null])
@php
    $names = array_column($table['columns'], 'name');
    $featureIndex = array_search('feature', $names, true);
    $importanceIndex = array_search('importance', $names, true);
    $rows = [];
    if ($featureIndex !== false && $importanceIndex !== false) {
        foreach ($table['rows'] as $row) {
            $value = $row[$importanceIndex] ?? null;
            if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                $rows[] = ['feature' => (string) ($row[$featureIndex] ?? ''), 'importance' => $value];
            }
        }
    }
    usort($rows, fn ($a, $b) => $b['importance'] <=> $a['importance']);
    $count = count($rows);
    $rows = array_slice($rows, 0, 25);
    $typeLabel = ['gain' => 'Gain', 'impurity' => 'Mean decrease in impurity', 'weight' => 'Split count', 'split' => 'Split count', 'cover' => 'Cover'][$type] ?? ($type ? ucfirst(str_replace('_', ' ', $type)) : 'Importance');
    $plot = $rows === [] ? null : \Phiz\Plot::of($rows)
        ->aes(x: 'importance', y: \Phiz\field('feature', 'nominal', sort: array_column($rows, 'feature')))
        ->col(fill: '#0ea5e9')
        ->labs(x: $typeLabel, y: null)
        ->theme(font: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif', baseSize: 12, panelBorder: 'none', grid: true,
            gridColor: '#e2e8f0', textColor: '#475569', ticks: false,
            config: ['axis' => ['titleFontWeight' => 'normal', 'titleColor' => '#64748b', 'titlePadding' => 12, 'labelPadding' => 8, 'domain' => false], 'axisY' => ['grid' => false]])
        ->width('container')
        ->height(max(100, count($rows) * 24))
        ->tooltip(['feature', 'importance']);
@endphp
@if ($plot)
    <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="importance-plot-title">
        <h2 id="importance-plot-title" class="font-semibold text-slate-900 dark:text-slate-100">Feature importance</h2>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
            Bars show each feature's {{ lcfirst($typeLabel) }}, largest first.
            @if ($count > 25) The top 25 of {{ number_format($count) }} features are shown. @endif
            Hover a bar for its exact value.
        </p>
        <x-phiz :plot="$plot" class="mt-3 w-full" role="img" aria-label="Feature importance bar chart. Exact values appear in the table." />
    </section>
@endif
