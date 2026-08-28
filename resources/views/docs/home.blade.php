@extends('docs.layout')

@section('title', 'Framework — R package documentation')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Framework</h1>
    <p class="mt-3 max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
        Structured data science in R: convention-over-configuration project scaffolding,
        data management with integrity tracking, and publishing.
    </p>

    <div class="mt-8 max-w-2xl overflow-x-auto rounded-lg bg-neutral-900 p-5 font-mono text-sm text-neutral-100 dark:ring-1 dark:ring-neutral-700">
        <div><span class="text-neutral-500"># install</span></div>
        <div>remotes::install_github(<span class="text-orange-400">"table1/framework"</span>)</div>
        <div class="mt-3"><span class="text-neutral-500"># start a project</span></div>
        <div>framework::new(<span class="text-orange-400">"my-study"</span>)</div>
        <div class="mt-3"><span class="text-neutral-500"># every notebook starts with</span></div>
        <div>library(framework)</div>
        <div>scaffold()</div>
    </div>

    <div class="mt-12 grid gap-x-10 gap-y-8 sm:grid-cols-2">
        @foreach (\App\Support\Docs::SECTIONS as $key => $label)
            <div>
                <h2 class="font-semibold">{{ $label }}</h2>
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @foreach ($guides->where('section', $key)->take(8) as $g)
                        <li>
                            <a href="{{ route('docs.guide', $g['slug']) }}" class="text-orange-600 hover:underline dark:text-orange-400">{{ $g['title'] }}</a>
                            @if ($g['description'])
                                <span class="text-neutral-500 dark:text-neutral-400">— {{ $g['description'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div>
            <h2 class="font-semibold">Function reference</h2>
            <ul class="mt-3 flex flex-col gap-2 text-sm">
                @foreach ($categories->take(8) as $c)
                    <li><a href="{{ route('docs.reference') }}#{{ Str::slug($c->name) }}" class="text-orange-600 hover:underline dark:text-orange-400">{{ $c->name }}</a></li>
                @endforeach
                <li><a href="{{ route('docs.reference') }}" class="text-neutral-500 hover:underline">All functions →</a></li>
            </ul>
        </div>
    </div>
@endsection
