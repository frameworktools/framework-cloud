@props(['snippet', 'document', 'outputShown' => true])
@php
    use App\Support\Snippets\ArtifactSnippet;
    use App\Support\Snippets\StyledText;

    $source = $snippet['source'];
    $language = ArtifactSnippet::LANGUAGES[$source['language']] ?? 'Code';
    $name = collect([isset($source['file']) ? basename($source['file']) : null, $source['label'] ?? null])->filter()->implode(' · ');
    $lines = isset($source['lines']) ? ($source['lines'][0] === $source['lines'][1] ? 'Line '.$source['lines'][0] : 'Lines '.$source['lines'][0].'–'.$source['lines'][1]) : null;
    $width = $snippet['snippet']['width'] ?? null;
    $links = ArtifactSnippet::resolvedLinks($snippet, $document);
    $labels = ['output' => 'Output', 'message' => 'Message', 'warning' => 'Warning', 'error' => 'Error'];
    $tones = [
        'output' => 'text-slate-800 dark:text-slate-200',
        'message' => 'text-slate-500 dark:text-slate-400',
        'warning' => 'border-l-2 border-amber-400 bg-amber-50/60 text-amber-900 dark:border-amber-500/70 dark:bg-amber-950/30 dark:text-amber-200',
        'error' => 'border-l-2 border-red-500 bg-red-50/60 text-red-800 dark:border-red-500/70 dark:bg-red-950/30 dark:text-red-300',
    ];
    // Output sits closer, so Stata's box lines nearly join from row to row
    // Counts group digits from five digits up, as framework-embed shows them
    $count = fn (int $n) => $n >= 10000 ? number_format($n) : (string) $n;
    $pre = 'overflow-x-auto px-4 py-3 font-mono text-[0.8125rem] whitespace-pre';
    $minWidth = $width ? 'style="min-width: '.$width.'ch"' : '';
    $hasOutput = collect($snippet['snippet']['blocks'])->contains(fn ($block) => $block['type'] !== 'code');
    $button = 'inline-flex h-6 items-center gap-1 rounded-md px-2 font-medium text-slate-600 hover:bg-slate-800/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white';
@endphp
<div class="flex min-w-0 flex-col gap-3" data-snippet>
    <figure class="snippet min-w-0 overflow-hidden rounded-xl border border-slate-800/10 bg-white dark:border-white/15 dark:bg-slate-950">
        <figcaption class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-slate-800/10 px-4 py-2 text-xs text-slate-500 dark:border-white/15 dark:text-slate-400">
            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $language }}</span>
            @if ($name !== '')<span class="min-w-0 truncate font-mono">{{ $name }}</span>@endif
            @if ($lines)
                @if (isset($source['permalink']))
                    <a href="{{ $source['permalink'] }}" class="underline decoration-slate-300 underline-offset-2 hover:text-slate-800 dark:decoration-slate-600 dark:hover:text-slate-200" rel="noopener">{{ $lines }}</a>
                @else
                    <span>{{ $lines }}</span>
                @endif
            @endif
            @if ($hasOutput)
                <button type="button" data-output-toggle data-shown="{{ $outputShown ? 'true' : 'false' }}" class="group/toggle ml-auto {{ $button }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 group-data-[shown=false]/toggle:hidden" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 group-data-[shown=true]/toggle:hidden" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <span data-output-label>{{ $outputShown ? 'Hide output' : 'Show output' }}</span>
                </button>
            @endif
            <button type="button" data-copy-code class="{{ $hasOutput ? '' : 'ml-auto' }} {{ $button }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-9.201a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                <span data-copy-label>Copy</span>
            </button>
        </figcaption>
        {{-- Rules sit between shown blocks only, so hidden output leaves no stray line --}}
        <div class="[&>*]:border-t-slate-800/10 [&>:not([hidden])~:not([hidden])]:border-t dark:[&>*]:border-t-white/10">
            @foreach ($snippet['snippet']['blocks'] as $block)
                @if ($block['type'] === 'code')
                    <pre class="snippet-code {{ $pre }} leading-relaxed bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-200" data-code><code class="block" {!! $minWidth !!}>{!! StyledText::html($block['lines']) !!}</code></pre>
                @else
                    @php
                        $lineCount = count($block['lines']) + ($block['truncated']['lines'] ?? 0);
                        $body = '<pre class="snippet-output '.$pre.' leading-[1.45]"><code class="block" '.$minWidth.'>'.StyledText::html($block['lines']).'</code></pre>';
                        $more = isset($block['truncated']) ? '<p class="px-4 pb-3 font-sans text-xs text-slate-500 dark:text-slate-400">'.$count($block['truncated']['lines']).' more '.\Illuminate\Support\Str::plural('line', $block['truncated']['lines']).' not shown.</p>' : '';
                    @endphp
                    <div class="{{ $tones[$block['type']] }}" data-output{{ $outputShown ? '' : ' hidden' }}>
                        @if ($block['folded'])
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center gap-1.5 px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform group-open:rotate-90" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                    {{ $labels[$block['type']] }}, {{ $count($lineCount) }} {{ \Illuminate\Support\Str::plural('line', $lineCount) }}
                                </summary>
                                {!! $body !!}{!! $more !!}
                            </details>
                        @else
                            {!! $body !!}{!! $more !!}
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </figure>
    @if ($links !== [])
        <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Related">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="text-slate-800 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-500 dark:text-slate-200 dark:decoration-slate-600" rel="noopener">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    @endif
</div>
<script>
    // Copy takes every code block, joined, without the output between them
    (() => {
        const root = document.currentScript.previousElementSibling;
        // The toggle keeps ?output=none in the address, so a copied link opens the same way
        const toggle = root.querySelector('[data-output-toggle]');
        toggle?.addEventListener('click', () => {
            const show = toggle.dataset.shown !== 'true';
            root.querySelectorAll('[data-output]').forEach((block) => { block.hidden = !show; });
            toggle.dataset.shown = String(show);
            toggle.querySelector('[data-output-label]').textContent = show ? 'Hide output' : 'Show output';
            const url = new URL(window.location.href);
            show ? url.searchParams.delete('output') : url.searchParams.set('output', 'none');
            history.replaceState(history.state, '', url);
        });
        const button = root.querySelector('[data-copy-code]');
        const label = button.querySelector('[data-copy-label]');
        button.addEventListener('click', async () => {
            const code = [...root.querySelectorAll('[data-code]')].map((pre) => pre.textContent).join('\n');
            try {
                await navigator.clipboard.writeText(code);
                label.textContent = 'Copied';
            } catch {
                label.textContent = 'Copy failed';
            }
            setTimeout(() => { label.textContent = 'Copy'; }, 2000);
        });
    })();
</script>
