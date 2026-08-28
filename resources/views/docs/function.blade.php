@extends('docs.layout')

@section('title', $fn->name.'() — Framework docs')

@section('content')
    @if ($fn->category)
        <div class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $fn->category->name }}</div>
    @endif
    <h1 class="mt-1 font-mono text-3xl font-semibold tracking-tight">{{ $fn->name }}()</h1>
    <p class="mt-2 text-lg text-neutral-600 dark:text-neutral-400">{{ $fn->title }}</p>

    @if ($fn->description)
        <div class="prose prose-neutral mt-6 max-w-none dark:prose-invert">{!! Str::markdown($fn->description) !!}</div>
    @endif

    @if ($fn->usage)
        <h2 class="mt-8 text-lg font-semibold">Usage</h2>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-neutral-900 p-4 font-mono text-sm text-neutral-100 dark:ring-1 dark:ring-neutral-700">{{ $fn->usage }}</pre>
    @endif

    @if ($fn->parameters->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold">Arguments</h2>
        <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-[max-content_1fr]">
            @foreach ($fn->parameters as $param)
                <dt class="font-mono text-sm">{{ $param->name }}</dt>
                <dd class="text-sm text-neutral-600 dark:text-neutral-400">{{ $param->description }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($fn->value)
        <h2 class="mt-8 text-lg font-semibold">Value</h2>
        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">{{ $fn->value }}</p>
    @endif

    @if ($fn->details)
        <h2 class="mt-8 text-lg font-semibold">Details</h2>
        <div class="prose prose-neutral mt-3 max-w-none text-sm dark:prose-invert">{!! Str::markdown($fn->details) !!}</div>
    @endif

    @if ($fn->examples->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold">Examples</h2>
        @foreach ($fn->examples as $example)
            <pre class="mt-3 overflow-x-auto rounded-lg bg-neutral-900 p-4 font-mono text-sm text-neutral-100 dark:ring-1 dark:ring-neutral-700">{{ $example->code }}</pre>
        @endforeach
    @endif

    @if ($fn->seealso->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold">See also</h2>
        <ul class="mt-3 flex flex-wrap gap-3 text-sm">
            @foreach ($fn->seealso as $ref)
                <li><a href="{{ route('docs.function', trim($ref->reference, '()')) }}" class="font-mono text-orange-600 hover:underline dark:text-orange-400">{{ $ref->reference }}</a></li>
            @endforeach
        </ul>
    @endif
@endsection
