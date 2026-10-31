{{-- "On this page" from a list of ['level' => 2|3, 'id' => …, 'text' => …] --}}
@if ($items !== [])
    <h2 class="font-display text-sm font-medium text-slate-900 dark:text-white">On this page</h2>
    <ol role="list" class="mt-4 space-y-3 text-sm">
        @foreach ($items as $item)
            <li @class(['pl-5' => $item['level'] === 3])>
                <a href="#{{ $item['id'] }}" data-toc-link class="text-slate-500 hover:text-slate-700 data-current:font-medium data-current:text-sky-600 dark:text-slate-400 dark:hover:text-slate-300 dark:data-current:text-sky-400">{{ $item['text'] }}</a>
            </li>
        @endforeach
    </ol>
@endif
