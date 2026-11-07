@php
    // Step 03's demo: the publish() call in each language, and at the seam below it, the model page a
    // collaborator gets. Formatted is Framework's model view; Raw output is what the tool itself printed,
    // so readers can check the translation. resources/examples/landing/publish.json holds real values:
    // the model JSON each package wrote for the same regression, and a trimmed excerpt of its printout.
    // "View model" opens the real page from php artisan fw:homepage-example (published from R).
    $example = config('framework.homepage_example_url');
    $exampleUrl = $example ? url(parse_url($example, PHP_URL_PATH) ?: '/') : null;
    $shown = preg_replace('#^https?://#', '', $exampleUrl ?? 'https://framework.pub/@examples/penguin-study/penguin-body-mass');
    $slug = 'penguin-body-mass';
    $languages = json_decode(file_get_contents(resource_path('examples/landing/publish.json')), true);
    $number = fn (float $value, int $decimals = 3) => str_replace('-', '−', number_format($value, $decimals, '.', ''));
    $slugCode = '<span class="text-sky-300 underline decoration-sky-400/70 decoration-2 underline-offset-4">'.$slug.'</span>';
    $toggle = 'rounded-md px-2.5 py-1 text-xs font-medium';
@endphp
<div data-publish-demo class="min-w-0 overflow-hidden rounded-xl ring-1 ring-slate-400/20">
    {{-- The call: header matches the editors above (tabs left, path right) --}}
    <div class="bg-[#0c1526] text-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 px-3 py-2">
            <div role="tablist" aria-label="Publishing language" class="flex gap-1">
                @foreach ($languages as $key => $language)
                    <button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-pd-tab="{{ $key }}" class="rounded-md px-3 py-1.5 text-xs text-slate-400 hover:text-white aria-selected:bg-white/10 aria-selected:text-white">{{ $language['label'] }}</button>
                @endforeach
            </div>
            @foreach ($languages as $key => $language)
                <span data-pd-lang="{{ $key }}" @unless ($loop->first) hidden @endunless class="max-w-full truncate font-mono text-[11px] text-slate-400">{{ $language['path'] }}</span>
            @endforeach
        </div>
        @foreach ($languages as $key => $language)
            <pre data-pd-lang="{{ $key }}" @unless ($loop->first) hidden @endunless class="overflow-x-auto px-5 py-4 font-mono text-[13px] leading-6 max-sm:break-words max-sm:whitespace-pre-wrap"><code>{!! str_replace('ZZSLUGZZ', $slugCode, \App\Support\ExampleCode::highlight($language['code'], false)) !!}</code></pre>
        @endforeach
    </div>

    {{-- The seam: what publishing prints --}}
    <div class="flex items-center gap-2 border-y border-emerald-500/20 bg-emerald-50 px-5 py-2 font-mono text-xs text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
        <x-heroicon-m-arrow-turn-down-right class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
        @foreach ($languages as $key => $language)
            <span data-pd-lang="{{ $key }}" @unless ($loop->first) hidden @endunless class="min-w-0 truncate">{{ $language['message'] }}{{ substr($shown, 0, -strlen($slug)) }}<span class="font-semibold underline decoration-emerald-500/60 decoration-2 underline-offset-4">{{ $slug }}</span></span>
        @endforeach
    </div>

    {{-- What a collaborator sees at that link --}}
    <div class="bg-white px-5 pt-4 pb-5 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <div class="min-w-0">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold tracking-tight">Penguin body mass model</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-white/10 dark:text-slate-300">Version 2</span>
                </p>
                @foreach ($languages as $key => $language)
                    <p data-pd-lang="{{ $key }}" @unless ($loop->first) hidden @endunless class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400"><span class="text-sky-700 dark:text-sky-400">{{ $language['source'] }}</span> · {{ $language['formula'] }}</p>
                @endforeach
            </div>
            <div class="flex items-center gap-4">
                @if ($exampleUrl)
                    <a href="{{ $exampleUrl }}" target="_blank" rel="noopener" class="shrink-0 text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">View model <span aria-hidden="true">↗</span></a>
                @endif
            </div>
        </div>

            <div role="group" aria-label="Model view" class="mt-3 inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-white/5">
                    <button type="button" data-pd-view="formatted" aria-pressed="true" class="{{ $toggle }} text-slate-600 dark:text-slate-400 hover:text-slate-900 aria-pressed:bg-white aria-pressed:text-slate-900 aria-pressed:shadow-sm dark:hover:text-white dark:aria-pressed:bg-slate-700 dark:aria-pressed:text-white">Formatted</button>
                    <button type="button" data-pd-view="raw" aria-pressed="false" class="{{ $toggle }} text-slate-600 dark:text-slate-400 hover:text-slate-900 aria-pressed:bg-white aria-pressed:text-slate-900 aria-pressed:shadow-sm dark:hover:text-white dark:aria-pressed:bg-slate-700 dark:aria-pressed:text-white">Raw output</button>
                </div>
        <div class="mt-3 grid">
            @foreach ($languages as $key => $language)
                {{-- Formatted --}}
                <div data-pd-lang="{{ $key }}" data-pd-pane="formatted" @unless ($loop->first) hidden @endunless tabindex="0" class="focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sky-500 [grid-area:1/1] self-start overflow-x-auto rounded-lg ring-1 ring-slate-800/10 dark:ring-white/10">
                    <table class="w-full text-[13px] whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-600 dark:bg-white/5 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-3 py-1.5 text-left font-medium">Term</th>
                                <th scope="col" class="px-3 py-1.5 text-right font-medium">Estimate</th>
                                <th scope="col" class="px-3 py-1.5 text-right font-medium">Std. error</th>
                                <th scope="col" class="px-3 py-1.5 text-right font-medium">p-value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/10 dark:divide-white/10">
                            @foreach ($language['rows'] as [$term, $estimate, $error, $p])
                                <tr>
                                    <td class="px-3 py-1.5 font-mono text-xs text-slate-800 dark:text-slate-200">{{ $term }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300">{{ $number($estimate) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300">{{ $number($error) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300">{{ $p < 0.001 ? '<0.001' : $number($p) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Raw: what the tool printed --}}
                <pre data-pd-lang="{{ $key }}" data-pd-pane="raw" hidden tabindex="0" class="focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sky-500 [grid-area:1/1] overflow-x-auto rounded-lg bg-slate-50 px-3 py-2.5 font-mono text-[11px] leading-[1.55] text-slate-700 ring-1 ring-slate-800/10 dark:bg-black/30 dark:text-slate-300 dark:ring-white/10"><code>{{ implode("\n", $language['raw']) }}</code></pre>
            @endforeach
        </div>
    </div>
</div>
