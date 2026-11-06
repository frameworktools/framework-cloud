@props([
    'title' => 'Framework: structured data science workflows',
    'description' => 'Reproducible research with R, Python, and the Framework CLI. Create projects, connect databases, track data integrity, and publish versioned figures, tables, models, and reports.',
])
{{-- What a link preview shows when someone shares this page. --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="Framework">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Framework: structured data science workflows, at framework.pub">
<meta name="twitter:card" content="summary_large_image">
