@props(['table'])
@php
    $columns = array_values(array_filter($table['columns'], fn ($column) => ! empty($column['summary'])));
    $format = fn ($value) => \App\Support\ArtifactFormatting::value($value);
    $rowCount = $table['row_count'] ?? 0;
    $missingShare = function (array $summary) use ($rowCount) {
        $count = $summary['count'] ?? $rowCount;
        return $count > 0 ? ($summary['missing'] ?? 0) / $count : 0;
    };
    $percent = fn (float $share) => $share === 0.0 ? '0%' : ($share < 0.001 ? '< 0.1%' : rtrim(rtrim(number_format($share * 100, 1), '0'), '.').'%');
@endphp
@if ($columns)
    <details @if (count($columns) <= 12) open @endif class="group rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 [&::-webkit-details-marker]:hidden">
            <span>
                <span class="block font-semibold text-slate-900 dark:text-slate-100">Columns</span>
                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Summaries over all {{ number_format($rowCount) }} rows, computed before publishing.</span>
            </span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
        </summary>
        <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700 dark:bg-slate-700">
            @foreach ($columns as $column)
                @php
                    $summary = $column['summary'];
                    $missing = $missingShare($summary);
                    $histogram = $summary['histogram'] ?? null;
                    $top = $summary['top'] ?? [];
                    $topTotal = max(1, $summary['count'] ?? $rowCount);
                @endphp
                <div class="flex min-w-0 flex-col gap-3 bg-white p-4 dark:bg-slate-800">
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="truncate font-mono text-sm font-medium text-slate-900 dark:text-slate-100" title="{{ $column['name'] }}">{{ $column['name'] }}</h3>
                            @isset($column['label'])<p class="truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $column['label'] }}">{{ $column['label'] }}</p>@endisset
                        </div>
                        <span class="shrink-0 font-mono text-[11px] text-sky-700 dark:text-sky-300">&lt;{{ $column['type'] }}&gt;</span>
                    </div>

                    @if ($histogram)
                        @php
                            $counts = $histogram['counts'];
                            $edges = $histogram['edges'];
                            $tallest = max(1, max($counts));
                            $width = 100 / count($counts);
                        @endphp
                        <div>
                            <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="h-10 w-full text-sky-500 dark:text-sky-400" role="img" aria-label="Histogram of {{ $column['name'] }}">
                                @foreach ($counts as $i => $count)
                                    <rect x="{{ $i * $width + 0.4 }}" y="{{ 32 - 32 * $count / $tallest }}" width="{{ max(0.2, $width - 0.8) }}" height="{{ 32 * $count / $tallest }}" fill="currentColor"><title>{{ $format($edges[$i]) }} to {{ $format($edges[$i + 1]) }}: {{ number_format($count) }}</title></rect>
                                @endforeach
                            </svg>
                            <div class="mt-1 flex justify-between font-mono text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                                <span>{{ $format($edges[0]) }}</span><span>{{ $format(end($edges)) }}</span>
                            </div>
                        </div>
                    @elseif ($top)
                        <ul class="space-y-1">
                            @foreach ($top as $entry)
                                @php $share = $entry['count'] / $topTotal; @endphp
                                <li class="relative flex items-center justify-between gap-2 overflow-hidden rounded px-2 py-0.5 text-xs">
                                    <span aria-hidden="true" class="absolute inset-y-0 left-0 bg-sky-100 dark:bg-sky-950" style="width: {{ round($share * 100, 1) }}%"></span>
                                    <span class="relative truncate text-slate-700 dark:text-slate-200" title="{{ $format($entry['value']) }}">{{ $format($entry['value']) }}</span>
                                    <span class="relative shrink-0 font-mono tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($entry['count']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <dl class="grid grid-cols-3 gap-x-3 gap-y-1 text-xs">
                        @if ($column['type'] === 'number')
                            @foreach (['mean' => 'Mean', 'sd' => 'SD', 'median' => 'Median', 'min' => 'Min', 'max' => 'Max'] as $key => $label)
                                @isset($summary[$key])
                                    <div><dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd data-model-summary-number="{{ $summary[$key] }}" title="{{ $summary[$key] }}" class="font-mono tabular-nums text-slate-800 dark:text-slate-200">{{ $format($summary[$key]) }}</dd></div>
                                @endisset
                            @endforeach
                        @elseif (in_array($column['type'], ['date', 'datetime']))
                            @foreach (['min' => 'Earliest', 'max' => 'Latest'] as $key => $label)
                                @isset($summary[$key])
                                    <div class="col-span-3"><dt class="inline text-slate-500 dark:text-slate-400">{{ $label }}</dt> <dd class="inline font-mono tabular-nums text-slate-800 dark:text-slate-200">{{ $summary[$key] }}</dd></div>
                                @endisset
                            @endforeach
                        @endif
                        @isset($summary['distinct'])
                            <div><dt class="text-slate-500 dark:text-slate-400">Distinct</dt><dd class="font-mono tabular-nums text-slate-800 dark:text-slate-200">{{ number_format($summary['distinct']) }}</dd></div>
                        @endisset
                        <div><dt class="text-slate-500 dark:text-slate-400">Missing</dt><dd class="font-mono tabular-nums {{ $missing > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200' }}">{{ $percent($missing) }}</dd></div>
                    </dl>
                </div>
            @endforeach
            {{-- Blank cells finish the last row, so the grid's divider color never shows as a block --}}
            @php
                $fillSmall = (2 - count($columns) % 2) % 2;
                $fillLarge = (3 - count($columns) % 3) % 3;
            @endphp
            @for ($i = 0; $i < max($fillSmall, $fillLarge); $i++)
                <div aria-hidden="true" class="hidden bg-white dark:bg-slate-800 {{ $i < $fillSmall ? 'sm:block' : '' }} {{ $i < $fillLarge ? 'lg:block' : 'lg:hidden' }}"></div>
            @endfor
        </div>
    </details>
@endif
