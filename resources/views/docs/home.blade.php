@extends('docs.layout')

@section('hero', 'dark-blue')

@section('full')
    @php
        $first = collect($site['sections'])->pluck('guides')->flatten()->first();
    @endphp
    <div class="overflow-hidden bg-blue-950">
        <div class="mx-auto max-w-8xl px-6 py-16 sm:px-8 lg:px-12 lg:py-20">
            <div class="grid grid-cols-1 items-center gap-x-12 gap-y-10 lg:grid-cols-2">
                <div>
                    <h1 @class(["text-5xl font-semibold tracking-tight text-white", "font-mono" => $site['title'] === 'fw'])>{{ $site['title'] }}</h1>
                    @if ($site['tagline'])
                        <p class="mt-4 text-2xl tracking-tight text-blue-200/80">{{ $site['tagline'] }}</p>
                    @endif
                    <div class="mt-8 flex flex-wrap gap-4">
                        @if ($first)
                            <a href="/guides/{{ $first }}" class="rounded-full bg-sky-500 px-4 py-2 text-sm font-medium text-white hover:bg-sky-600">Get started</a>
                        @endif
                        @if ($site['categories'] !== [])
                            <a href="/reference" class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-blue-50 ring-1 ring-white/15 hover:bg-white/15">Reference</a>
                        @endif
                        @if ($site['repository'])
                            <a href="{{ $site['repository'] }}" class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-blue-100/80 ring-1 ring-white/15 hover:bg-white/15 hover:text-blue-50">View on GitHub</a>
                        @endif
                    </div>
                </div>

                @if ($site['install'])
                    <div class="relative">
                        <div class="absolute inset-0 rounded-2xl bg-gradient-to-tr from-sky-300 via-sky-300/70 to-blue-300 opacity-10 blur-lg"></div>
                        <div class="relative rounded-2xl bg-black/25 ring-1 ring-white/10">
                            <div class="absolute -top-px right-11 left-20 h-px bg-gradient-to-r from-sky-300/0 via-sky-300/70 to-sky-300/0"></div>
                            <div class="flex items-center justify-between px-5 pt-4">
                                <span class="text-xs font-medium text-blue-200/70">Install</span>
                                <button type="button" data-copy="docs-install" class="rounded-lg bg-white/10 px-3 py-1 text-xs font-medium text-blue-100/80 hover:bg-white/15 hover:text-blue-50"><span data-copy-label>Copy</span></button>
                            </div>
                            <pre class="overflow-x-auto px-5 pt-3 pb-6 text-sm"><code id="docs-install" class="text-slate-50">{{ $site['install'] }}</code></pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-8xl px-6 py-16 sm:px-8 lg:px-12">
        <div class="gap-6 md:columns-2 xl:columns-3">
            @foreach ($site['sections'] as $section)
                @if ($section['reference'])
                    @continue($site['categories'] === [])
                    <section class="mb-6 break-inside-avoid rounded-xl border border-slate-200 p-6 dark:border-slate-700">
                        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white"><a href="/reference" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $section['title'] }}</a></h2>
                        <ul role="list" class="mt-4 space-y-3">
                            @foreach ($site['categories'] as $category)
                                <li>
                                    <a href="/reference#{{ $category['id'] }}" class="text-sm font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $category['title'] }}</a>
                                    @if ($category['description'])
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $category['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @else
                    @continue($section['guides'] === [])
                    <section class="mb-6 break-inside-avoid rounded-xl border border-slate-200 p-6 dark:border-slate-700">
                        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $section['title'] }}</h2>
                        <ul role="list" class="mt-4 space-y-3">
                            @foreach ($section['guides'] as $id)
                                <li>
                                    <a href="/guides/{{ $id }}" class="text-sm font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $site['guides'][$id]['title'] }}</a>
                                    @if ($site['guides'][$id]['description'])
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $site['guides'][$id]['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    </div>
@endsection
