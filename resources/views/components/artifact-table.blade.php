@props(['table', 'id' => 'artifact-table', 'model' => false])
@php
    $columns = $table['columns'];
    $rows = $table['rows'];
    $rowCount = $table['row_count'] ?? count($rows);
    $columnCount = $table['column_count'] ?? count($columns);
    $controlClass = 'rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $decimal = [];
    foreach ($columns as $index => $column) {
        $decimal[$index] = $column['type'] === 'number' && array_filter(array_column($rows, $index), fn ($value) => is_float($value)) !== [];
    }
    // A model leaves a value out when it doesn't apply (no p-value for lmer); data tables mark real missing values as NA
    $format = fn ($value, $index = null) => $value === null && $model ? '—' : \App\Support\ArtifactFormatting::value($value, $index !== null && ($decimal[$index] ?? false));
    $label = fn ($name) => $model ? \App\Support\ArtifactFormatting::coefficientLabel($name) : $name;
@endphp
<section data-artifact-table data-model-table="{{ $model ? 'true' : 'false' }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800" aria-labelledby="{{ $id }}-title">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-4 py-4 dark:border-slate-700">
        <div>
            <h2 id="{{ $id }}-title" class="font-semibold text-slate-900 dark:text-slate-100">{{ $table['title'] ?? 'Table' }}</h2>
            <p class="mt-1 font-mono text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($rowCount) }} {{ $rowCount === 1 ? 'row' : 'rows' }} × {{ number_format($columnCount) }} {{ $columnCount === 1 ? 'column' : 'columns' }}</p>
        </div>
        <label data-table-control hidden class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300" for="{{ $id }}-search">
            <span class="sr-only">Search table</span><input id="{{ $id }}-search" data-table-search type="search" class="{{ $controlClass }} max-w-52" autocomplete="off" placeholder="Search these rows">
        </label>
    </div>
    @if ($table['truncated'] ?? false)
        <p class="border-b border-slate-200 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
            @if ($rowCount > count($rows) && count($columns) === $columnCount)
                Showing the first {{ number_format(count($rows)) }} of {{ number_format($rowCount) }} rows. Column summaries cover every row; search and sorting apply to the rows shown. Download the original for the complete data.
            @else
                Preview contains {{ number_format(count($rows)) }} rows and {{ count($columns) }} columns. Search and sorting apply to these loaded values. Download the original for the complete data.
            @endif
        </p>
    @endif
    @if (!empty($table['precision_note']))<p class="border-b border-slate-200 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $table['precision_note'] }}</p>@endif
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm tabular-nums">
            <caption class="sr-only">{{ $table['title'] ?? 'Published table' }}</caption>
            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                <tr>
                    @foreach ($columns as $index => $column)
                        <th scope="col" @unless ($model) aria-sort="none" @endunless class="whitespace-nowrap px-4 py-3 {{ $column['type'] === 'number' ? 'text-right' : '' }}">
                            @if ($model)
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $label($column['name']) }}</span>
                            @else
                            <button type="button" data-table-sort="{{ $index }}" class="inline-flex items-center gap-2 rounded font-semibold text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-500 dark:text-slate-100" aria-label="Sort by {{ $label($column['name']) }}">
                                {{ $label($column['name']) }}
                                <svg data-sort-indicator aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" class="size-3 text-slate-400"><path data-sort-up d="m4 6 4-4 4 4"/><path data-sort-down d="m4 10 4 4 4-4"/></svg>
                            </button>
                            @endif
                            @unless ($model)<span class="mt-1 block font-mono text-[11px] font-normal text-sky-700 dark:text-sky-300">&lt;{{ $column['type'] }}&gt;</span>@endunless
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse (array_slice($rows, 0, 25) as $row)
                    <tr class="border-b border-slate-200 dark:border-slate-700">
                        @foreach ($columns as $index => $column)
                            <td title="{{ $row[$index] ?? ($model ? 'Not reported' : 'Missing value') }}" class="whitespace-nowrap px-4 py-2 text-slate-700 dark:text-slate-200 {{ $column['type'] === 'number' ? 'text-right' : '' }} {{ in_array($column['type'], ['number', 'date', 'datetime']) || is_numeric($row[$index] ?? null) ? 'font-mono tabular-nums' : '' }}">{{ $format($row[$index] ?? null, $index) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, count($columns)) }}" class="px-4 py-8 text-center text-slate-500">This table has no rows.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-table-control hidden class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
        <p data-table-status class="font-mono tabular-nums" role="status" aria-live="polite"></p>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-2">
                <label for="{{ $id }}-precision">Decimal places</label>
                <div class="relative">
                    <input id="{{ $id }}-precision" data-table-precision type="number" min="0" max="15" step="1" value="3" class="{{ $controlClass }} w-20 pr-8 font-mono tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    <div class="absolute inset-y-px right-px flex w-6 flex-col border-l border-slate-200 dark:border-slate-600">
                        <button data-precision-increase type="button" aria-label="Increase decimal places" class="flex flex-1 items-center justify-center rounded-tr text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:text-slate-300 dark:hover:bg-slate-700"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-3"><path d="m4 10 4-4 4 4"/></svg></button>
                        <button data-precision-decrease type="button" aria-label="Decrease decimal places" class="flex flex-1 items-center justify-center rounded-br border-t border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-3"><path d="m4 6 4 4 4-4"/></svg></button>
                    </div>
                </div>
            </div>
            <button data-table-export type="button" class="{{ $controlClass }}" title="Export all loaded rows matching the current search, in the current sort order">Export preview CSV</button>
            <label @if(count($rows) <= 25) hidden @endif class="flex items-center gap-2" for="{{ $id }}-size">Rows per page
                <select id="{{ $id }}-size" data-table-size class="{{ $controlClass }}"><option>25</option><option>50</option><option>100</option><option>250</option></select>
            </label>
            <button @if(count($rows) <= 25) hidden @endif data-table-previous type="button" class="{{ $controlClass }} disabled:opacity-40">Previous</button>
            <button @if(count($rows) <= 25) hidden @endif data-table-next type="button" class="{{ $controlClass }} disabled:opacity-40">Next</button>
        </div>
    </div>
    <noscript><p class="px-4 py-3 text-xs text-slate-500">The first 25 loaded rows are shown. Enable JavaScript to sort, search, and change pages.</p></noscript>
    <script type="application/json" data-table-values>@json($table, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
</section>
