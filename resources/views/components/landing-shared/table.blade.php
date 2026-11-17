@props(['rows', 'image' => false])
@php
    // The step 04 model's coefficient table, in the host's type. An image keeps its own light colors.
    $number = fn (float $value) => str_replace('-', '−', number_format($value, 2, '.', ','));
    $muted = $image ? 'text-slate-500' : 'text-slate-500 dark:text-slate-400';
    $rule = $image ? 'border-slate-800/15' : 'border-slate-800/15 dark:border-white/15';
    $divide = $image ? 'divide-slate-800/10' : 'divide-slate-800/10 dark:divide-white/10';
    $cell = 'py-1.5 pl-4 text-right font-mono text-[12px] tabular-nums';
@endphp
<table {{ $attributes->class('w-full text-sm whitespace-nowrap') }}>
    <thead class="border-b {{ $rule }} {{ $muted }}">
        <tr>
            <th scope="col" class="py-1.5 text-left font-normal">Term</th>
            <th scope="col" class="py-1.5 pl-4 text-right font-normal">Estimate</th>
            <th scope="col" class="py-1.5 pl-4 text-right font-normal">Std. error</th>
            <th scope="col" class="py-1.5 pl-4 text-right font-normal">p-value</th>
            <th scope="col" class="py-1.5 pl-4 text-right font-normal">95% CI</th>
        </tr>
    </thead>
    <tbody class="divide-y {{ $divide }}">
        @foreach ($rows as [$term, $estimate, $error, $p, $low, $high])
            <tr>
                <th scope="row" class="py-1.5 text-left font-normal">{{ $term }}</th>
                <td class="{{ $cell }}">{{ $number($estimate) }}</td>
                <td class="{{ $cell }}">{{ $number($error) }}</td>
                <td class="{{ $cell }}">{{ $p < 0.001 ? '< 0.001' : number_format($p, 3) }}</td>
                <td class="{{ $cell }}">{{ $number($low) }} to {{ $number($high) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
