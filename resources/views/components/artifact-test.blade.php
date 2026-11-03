@props(['test'])
@php
    $format = fn ($value) => \App\Support\ArtifactFormatting::value($value);
    $p = $test['p_value'] ?? null;
    $pText = $p === null ? null : ($p < 0.001 ? '< 0.001' : number_format($p, 3));
    $alternative = ['two.sided' => 'Two-sided', 'less' => 'One-sided, less than', 'greater' => 'One-sided, greater than'][$test['alternative'] ?? ''] ?? null;
    $interval = $test['conf_int'] ?? null;
    $percent = $interval ? rtrim(rtrim(number_format($interval['level'] * 100, 1), '0'), '.').'%' : null;
    $null = $test['null_value'][0] ?? null;
    $estimates = $test['estimates'] ?? [];

    // An interval chart when the test reports one: the estimate when there's exactly one, and the null value
    $plot = null;
    if ($interval && isset($interval['low'], $interval['high'])) {
        $point = count($estimates) === 1 ? $estimates[0]['value'] : null;
        $label = $null['name'] ?? ($estimates[0]['name'] ?? 'Estimate');
        $row = ['label' => ucfirst($label), 'low' => $interval['low'], 'high' => $interval['high']] + ($point !== null ? ['estimate' => $point] : []);
        $values = array_filter([$interval['low'], $interval['high'], $point, $null['value'] ?? null], fn ($v) => $v !== null);
        $low = min($values);
        $high = max($values);
        $pad = $high > $low ? ($high - $low) * 0.15 : 1;
        $plot = \Phiz\Plot::of([$row])
            ->aes(y: 'label', x: 'low', xend: 'high', yend: 'label')
            ->segment(color: '#0ea5e9', linewidth: 3)
            ->labs(x: "{$percent} confidence interval", y: null)
            ->xlim($low - $pad, $high + $pad)
            ->theme(font: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif', baseSize: 12, panelBorder: 'none', grid: true,
                gridColor: '#e2e8f0', textColor: '#475569', ticks: false,
                config: ['axis' => ['titleFontWeight' => 'normal', 'titleColor' => '#64748b', 'titlePadding' => 12, 'labelPadding' => 8, 'domain' => false], 'axisY' => ['grid' => false, 'labels' => false]])
            ->width('container')
            ->height(56)
            ->tooltip(['label', 'low', 'high', ...($point !== null ? ['estimate'] : [])]);
        if ($point !== null) {
            $plot = $plot->point(aes: ['x' => 'estimate'], color: '#0ea5e9', size: 80);
        }
        if ($null !== null) {
            $plot = $plot->vline($null['value'], linetype: 'dashed', color: '#94a3b8');
        }
    }
    $cell = 'px-4 py-2';
@endphp
<section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800" aria-labelledby="test-title">
    <h2 id="test-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $test['method'] }}</h2>
    @if ($alternative)<p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $alternative }} alternative hypothesis</p>@endif

    <dl class="mt-5 grid gap-4 sm:grid-cols-3">
        @if (isset($test['statistic']))
            <div class="rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-900/60">
                <dt class="text-xs text-slate-500 dark:text-slate-400">Test statistic</dt>
                <dd class="mt-1 font-mono text-xl tabular-nums text-slate-900 dark:text-slate-100" title="{{ $test['statistic']['value'] }}"><span class="font-sans text-base text-slate-600 dark:text-slate-300">{{ $test['statistic']['name'] }} =</span> {{ $format($test['statistic']['value']) }}</dd>
            </div>
        @endif
        @if ($pText)
            <div class="rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-900/60">
                <dt class="text-xs text-slate-500 dark:text-slate-400">p-value</dt>
                <dd class="mt-1 font-mono text-xl tabular-nums text-slate-900 dark:text-slate-100" title="{{ $p }}">{{ $pText }}</dd>
            </div>
        @endif
        @if ($interval)
            <div class="rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-900/60">
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $percent }} confidence interval</dt>
                <dd class="mt-1 font-mono text-xl tabular-nums text-slate-900 dark:text-slate-100">{{ isset($interval['low']) ? $format($interval['low']) : '−∞' }} to {{ isset($interval['high']) ? $format($interval['high']) : '∞' }}</dd>
            </div>
        @endif
    </dl>

    @if ($plot)
        <x-phiz :plot="$plot" class="mt-5 w-full" role="img" aria-label="Confidence interval{{ $null ? ' with the null value marked' : '' }}" />
        @if ($null)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The dashed line marks the null value, {{ $null['name'] }} = {{ $format($null['value']) }}.</p>@endif
    @endif

    @php
        $details = [];
        foreach ($estimates as $entry) $details[] = ['Estimate', $entry['name'], $entry['value']];
        foreach ($test['parameters'] ?? [] as $entry) $details[] = ['Parameter', $entry['name'], $entry['value']];
        foreach ($test['null_value'] ?? [] as $entry) $details[] = ['Null hypothesis', $entry['name'], $entry['value']];
    @endphp
    @if ($details)
        <div class="mt-5 w-fit max-w-full overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="w-auto border-collapse text-sm">
                <caption class="sr-only">Estimates, parameters, and null values</caption>
                <tbody>
                    @foreach ($details as [$group, $name, $value])
                        <tr class="border-t border-slate-100 first:border-0 dark:border-slate-800">
                            <th scope="row" class="{{ $cell }} text-left text-xs font-normal text-slate-500 dark:text-slate-400">{{ $group }}</th>
                            <td class="{{ $cell }} text-slate-700 dark:text-slate-300">{{ $name }}</td>
                            <td data-model-summary-number="{{ $value }}" title="{{ $value }}" class="{{ $cell }} text-right font-mono tabular-nums text-slate-900 dark:text-slate-100">{{ $format($value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
