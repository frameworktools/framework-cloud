@props([
    'title' => 'Framework: Structured workflows for statistics and data science',
    'description' => 'Reproducible research with R, Python, and the Framework CLI. Create projects, connect databases, track data integrity, and publish versioned figures, tables, models, and reports.',
    'type' => 'website',
    'canonical' => null,
    'noindex' => false,
    'schema' => [],
    // ['url' => ..., 'alt' => ...]: a 1200 × 630 PNG of this page's own, in place of the site's
    'image' => null,
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
<meta property="og:site_name" content="{{ \App\Support\ArtifactEmbed::providerName() }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
@if ($image)
<meta property="og:image" content="{{ $image['url'] }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $image['alt'] }}">
<meta name="twitter:image" content="{{ $image['url'] }}">
<meta name="twitter:image:alt" content="{{ $image['alt'] }}">
@else
{{-- The version changes with the image, so caches and link previews pick up a new one --}}
<meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/og.png?v={{ substr(md5_file(public_path('images/og.png')), 0, 8) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Framework: Structured workflows for statistics and data science, at framework.pub">
@endif
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:card" content="summary_large_image">
@if ($nodes !== [])
<script type="application/ld+json">{!! \App\Support\Seo::json($nodes) !!}</script>
@endif
