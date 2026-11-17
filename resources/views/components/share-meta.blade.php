@props([
    'title' => 'Framework: Structured workflows for statistics and data science',
    'description' => 'Reproducible research with R, Python, and the Framework CLI. Create projects, connect databases, track data integrity, and publish versioned figures, tables, models, and reports.',
    'type' => 'website',
    'canonical' => null,
    'noindex' => false,
    'schema' => [],
])
@php
    $canonical ??= url()->current();
    $description = \App\Support\Seo::describe($description) ?? '';
    $nodes = array_values(array_filter(array_map(fn ($node) => $node ? \App\Support\Seo::clean($node) : null, $schema)));
@endphp
{{-- What search engines and link previews read about this page --}}
<meta name="description" content="{{ $description }}">
@if ($noindex)
<meta name="robots" content="noindex">
@else
<link rel="canonical" href="{{ $canonical }}">
@endif
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="Framework">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
{{-- The version changes with the image, so caches and link previews pick up a new one --}}
<meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/og.png?v={{ substr(md5_file(public_path('images/og.png')), 0, 8) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Framework: Structured workflows for statistics and data science, at framework.pub">
<meta name="twitter:card" content="summary_large_image">
@if ($nodes !== [])
<script type="application/ld+json">{!! \App\Support\Seo::json($nodes) !!}</script>
@endif
