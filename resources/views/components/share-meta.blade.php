@props([
    'title' => 'Framework: Structured workflows for statistics and data science',
    'description' => 'Reproducible research with R, Python, and the Framework CLI. Create projects, connect databases, track data integrity, and publish versioned figures, tables, models, and reports.',
])
{{-- What a link preview shows when someone shares this page. --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="Framework">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
{{-- The version changes with the image, so caches and link previews pick up a new one --}}
<meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/og.png?v={{ substr(md5_file(public_path('images/og.png')), 0, 8) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Framework: Structured workflows for statistics and data science, at framework.pub">
<meta name="twitter:card" content="summary_large_image">
