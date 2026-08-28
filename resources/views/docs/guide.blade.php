@extends('docs.layout')

@section('title', ($guide['meta']['title'] ?? 'Guide').' — Framework docs')

@section('content')
    <div class="text-xs font-medium uppercase tracking-wide text-neutral-500">
        {{ \App\Support\Docs::SECTIONS[$guide['meta']['section'] ?? ''] ?? 'Guide' }}
    </div>
    <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $guide['meta']['title'] ?? '' }}</h1>
    @if (!empty($guide['meta']['description']))
        <p class="mt-2 text-lg text-neutral-600 dark:text-neutral-400">{{ $guide['meta']['description'] }}</p>
    @endif

    <article class="prose prose-neutral mt-8 max-w-none dark:prose-invert prose-pre:overflow-x-auto prose-code:before:content-none prose-code:after:content-none">
        {!! $guide['html'] !!}
    </article>
@endsection
