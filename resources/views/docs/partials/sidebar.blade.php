@php
    $currentGuide = isset($guide) ? $guide['id'] : null;
    $currentEntry = isset($entry) ? $entry : null;
@endphp
<ul role="list" class="space-y-8">
    @foreach ($site['sections'] as $section)
        @if ($section['reference'])
            @continue($site['categories'] === [])
            <li>
                <h2 class="font-display font-medium text-slate-900 dark:text-white">
                    <a href="/reference" class="hover:text-sky-600 dark:hover:text-sky-400">{{ $section['title'] }}</a>
                </h2>
                <ul role="list" class="mt-3 space-y-2 border-l-2 border-slate-100 dark:border-slate-800">
                    @foreach ($site['categories'] as $category)
                        @php($open = $currentEntry && $currentEntry['category'] === $category['id'])
                        <li class="relative">
                            <a href="/reference#{{ $category['id'] }}" @class([
                                'block w-full pl-3.5 before:pointer-events-none before:absolute before:top-1/2 before:-left-1 before:h-1.5 before:w-1.5 before:-translate-y-1/2 before:rounded-full',
                                'font-semibold text-sky-600 before:bg-sky-500 dark:text-sky-400' => $open,
                                'text-slate-500 before:hidden before:bg-slate-300 hover:text-slate-600 hover:before:block dark:text-slate-400 dark:before:bg-slate-700 dark:hover:text-slate-300' => ! $open,
                            ])>{{ $category['title'] }}</a>
                        </li>
                        @if ($open)
                            <li>
                                <ul role="list" class="ml-3.5 space-y-1.5">
                                    @foreach ($category['entries'] as $id)
                                        <li>
                                            <a href="/reference/{{ $id }}" @class([
                                                'block truncate font-mono text-[13px]',
                                                'text-sky-600 dark:text-sky-400' => $currentEntry['id'] === $id,
                                                'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $currentEntry['id'] !== $id,
                                            ])>{{ $site['entries'][$id]['name'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </li>
        @else
            @continue($section['guides'] === [])
            <li>
                <h2 class="font-display font-medium text-slate-900 dark:text-white">{{ $section['title'] }}</h2>
                <ul role="list" class="mt-3 space-y-2 border-l-2 border-slate-100 dark:border-slate-800">
                    @foreach ($section['guides'] as $id)
                        <li class="relative">
                            <a href="/guides/{{ $id }}" @if ($currentGuide === $id) aria-current="page" @endif @class([
                                'block w-full pl-3.5 before:pointer-events-none before:absolute before:top-1/2 before:-left-1 before:h-1.5 before:w-1.5 before:-translate-y-1/2 before:rounded-full',
                                'font-semibold text-sky-600 before:bg-sky-500 dark:text-sky-400' => $currentGuide === $id,
                                'text-slate-500 before:hidden before:bg-slate-300 hover:text-slate-600 hover:before:block dark:text-slate-400 dark:before:bg-slate-700 dark:hover:text-slate-300' => $currentGuide !== $id,
                            ])>{{ $site['guides'][$id]['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endif
    @endforeach
</ul>
