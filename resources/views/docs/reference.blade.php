@extends('docs.layout')

@section('title', 'Function reference — Framework docs')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Function reference</h1>
    <p class="mt-2 text-neutral-600 dark:text-neutral-400">Every exported function, grouped by what it's for.</p>

    <div class="mt-10 flex flex-col gap-10">
        @foreach ($categories as $category)
            <section id="{{ Str::slug($category->name) }}">
                <h2 class="border-b border-neutral-200 pb-2 text-xl font-semibold dark:border-neutral-800">{{ $category->name }}</h2>
                @if ($category->description)
                    <p class="mt-2 text-sm text-neutral-500">{{ $category->description }}</p>
                @endif
                <dl class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-[max-content_1fr]">
                    @foreach ($byCategory->get($category->id, collect()) as $fn)
                        <dt><a href="{{ route('docs.function', $fn->name) }}" class="font-mono text-sm text-orange-600 hover:underline dark:text-orange-400">{{ $fn->name }}()</a></dt>
                        <dd class="text-sm text-neutral-600 dark:text-neutral-400">{{ $fn->title }}</dd>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
@endsection
