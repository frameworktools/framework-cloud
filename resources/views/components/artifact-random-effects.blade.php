@props(['table'])
@php
    // R sends correlations as extra rows ("(Intercept), Days"); Python puts each term's correlation
    // with the group's first term on that term's row. Both become a list of pairs here.
    $names = array_column($table['columns'], 'name');
    $at = fn (array $row, string $name) => ($index = array_search($name, $names, true)) === false ? null : ($row[$index] ?? null);
    $number = fn ($value) => (is_int($value) || is_float($value)) && is_finite((float) $value);
    $format = fn ($value) => \App\Support\ArtifactFormatting::value($value, true);

    $components = [];
    $correlations = [];
    $firstTerm = [];
    foreach ($table['rows'] as $row) {
        $group = (string) ($at($row, 'group') ?? '');
        $term = trim((string) ($at($row, 'term') ?? ''));
        $variance = $at($row, 'variance');
        $sd = $at($row, 'std_dev');
        $correlation = $at($row, 'correlation');
        if (! $number($variance) && ! $number($sd)) {
            if ($number($correlation) && str_contains($term, ', ')) {
                [$a, $b] = explode(', ', $term, 2);
                $correlations[] = compact('group', 'a', 'b') + ['value' => $correlation];
            }
            continue;
        }
        $firstTerm[$group] ??= $term;
        if ($number($correlation) && $term !== $firstTerm[$group]) {
            $correlations[] = ['group' => $group, 'a' => $firstTerm[$group], 'b' => $term, 'value' => $correlation];
        }
        $components[] = ['group' => $group, 'term' => $term, 'variance' => $variance, 'sd' => $sd];
    }
    $cell = 'px-4 py-2';
@endphp
@if ($components)
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800" aria-labelledby="random-effects-title">
        <div class="border-b border-slate-200 px-4 py-4 dark:border-slate-700">
            <h2 id="random-effects-title" class="font-semibold text-slate-900 dark:text-slate-100">Random effects</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Variance components by grouping factor.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <caption class="sr-only">Random effects variance components</caption>
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                    <tr class="font-semibold text-slate-800 dark:text-slate-100">
                        <th scope="col" class="{{ $cell }} py-3">Group</th>
                        <th scope="col" class="{{ $cell }} py-3">Term</th>
                        <th scope="col" class="{{ $cell }} py-3 text-right">Variance</th>
                        <th scope="col" class="{{ $cell }} py-3 text-right">Std. deviation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($components as $index => $component)
                        @php $newGroup = $index === 0 || $components[$index - 1]['group'] !== $component['group']; @endphp
                        <tr class="{{ $newGroup ? 'border-t border-slate-200 dark:border-slate-700' : '' }}">
                            <th scope="row" class="{{ $cell }} font-normal text-slate-700 dark:text-slate-200">{{ $newGroup ? $component['group'] : '' }}</th>
                            <td class="{{ $cell }} text-slate-700 dark:text-slate-200">{{ $component['term'] }}</td>
                            @foreach (['variance', 'sd'] as $key)
                                <td @if ($number($component[$key])) data-model-summary-number="{{ $component[$key] }}" @endif title="{{ $component[$key] ?? 'Not reported' }}"
                                    class="{{ $cell }} text-right font-mono tabular-nums text-slate-700 dark:text-slate-200">{{ $number($component[$key]) ? $format($component[$key]) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($correlations)
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                <h3 class="text-xs font-semibold text-slate-600 dark:text-slate-300">Correlations</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-200">
                    @foreach ($correlations as $pair)
                        <li>
                            <span class="text-slate-500 dark:text-slate-400">{{ $pair['group'] }}:</span>
                            {{ $pair['a'] }} and {{ $pair['b'] }},
                            <span data-model-summary-number="{{ $pair['value'] }}" title="{{ $pair['value'] }}" class="font-mono tabular-nums">{{ $format($pair['value']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
