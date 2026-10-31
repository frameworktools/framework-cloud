@php
    use App\Support\Docs\Products;

    $chevron = '<svg class="size-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>';
@endphp
<header class="sticky top-0 z-40 flex-none bg-white py-4 shadow-md shadow-slate-900/5 transition duration-500 dark:bg-slate-900/95 dark:shadow-none dark:backdrop-blur dark:[@supports(backdrop-filter:blur(0))]:bg-slate-900/80">
    <div class="mx-auto flex max-w-8xl items-center justify-between gap-6 px-6 sm:px-8 lg:px-12">
        <div class="flex min-w-0 items-center gap-8">
            <a href="/" aria-label="{{ $siteTitle }} home" class="flex shrink-0 items-center gap-3">
                <img src="/images/logo.png" alt="" class="h-8 w-auto">
                <span class="text-lg font-bold whitespace-nowrap text-slate-900 dark:text-white">{{ $siteTitle }}</span>
            </a>

            @if ($site)
                <nav class="hidden lg:flex lg:items-center lg:gap-1" aria-label="Sections">
                    @foreach ($site['sections'] as $section)
                        @php
                            $items = $section['reference']
                                ? collect($site['categories'])->map(fn ($c) => ['url' => '/reference#'.$c['id'], 'title' => $c['title'], 'text' => $c['description']])
                                : collect($section['guides'])->map(fn ($id) => ['url' => '/guides/'.$id, 'title' => $site['guides'][$id]['title'], 'text' => $site['guides'][$id]['description']]);
                            $wide = $items->count() > 6;
                        @endphp
                        @continue($items->isEmpty())
                        <div class="docs-menu relative">
                            <button type="button" aria-haspopup="true" class="flex items-center gap-1 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                {{ $section['title'] }} {!! $chevron !!}
                            </button>
                            <div @class([
                                'docs-menu-panel absolute top-full left-1/2 z-50 -translate-x-1/2 origin-top pt-1',
                                'w-[600px]' => $wide,
                                'w-72' => ! $wide,
                            ])>
                                <div class="rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-white/10">
                                    <div @class(['grid gap-1', 'grid-cols-2' => $wide])>
                                        @foreach ($items as $item)
                                            <a href="{{ $item['url'] }}" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item['title'] }}</div>
                                                @if ($item['text'])
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $item['text'] }}</div>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                    @if ($section['reference'])
                                        <a href="/reference" class="mt-1 block rounded-lg border-t border-slate-100 px-3 py-2.5 text-sm font-medium text-sky-600 hover:bg-slate-50 dark:border-slate-700 dark:text-sky-400 dark:hover:bg-slate-700/50">All {{ count($site['entries']) }} entries</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <nav class="hidden items-center gap-0.5 rounded-lg bg-slate-100 p-0.5 text-sm md:flex dark:bg-slate-800" aria-label="Products">
                @foreach (Products::LABELS as $other => $label)
                    <a href="{{ Products::url($other) }}" @if ($other === $product) aria-current="page" @endif
                       @class([
                           'rounded-md px-2.5 py-1 font-medium',
                           'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' => $other === $product,
                           'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $other !== $product,
                       ])>{{ $label }}</a>
                @endforeach
            </nav>

            @if ($site)
                <button type="button" data-search-open class="group flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500 hover:border-slate-300 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:bg-slate-700">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                    <span class="hidden sm:inline">Search</span>
                    <kbd class="hidden rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[11px] leading-none text-slate-400 sm:inline-block dark:border-slate-600 dark:bg-slate-700">⌘K</kbd>
                </button>
            @endif

            <button type="button" data-docs-theme class="flex size-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300" aria-label="Switch between light and dark">
                <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/></svg>
            </button>

            @if ($repository)
                <a href="{{ $repository }}" class="flex size-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300" aria-label="GitHub repository">
                    <svg class="size-5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8Z"/></svg>
                </a>
            @endif

            @if ($site)
                <details class="relative lg:hidden">
                    <summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 [&::-webkit-details-marker]:hidden" aria-label="Menu">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 max-h-[75vh] w-72 overflow-y-auto rounded-xl bg-white p-4 text-sm shadow-lg ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-white/10">
                        <div class="mb-4 flex gap-1 md:hidden">
                            @foreach (Products::LABELS as $other => $label)
                                <a href="{{ Products::url($other) }}" @class(['rounded-md px-2.5 py-1 font-medium', 'bg-slate-100 dark:bg-slate-700' => $other === $product, 'text-slate-500' => $other !== $product])>{{ $label }}</a>
                            @endforeach
                        </div>
                        @include('docs.partials.sidebar')
                    </div>
                </details>
            @endif
        </div>
    </div>
</header>
