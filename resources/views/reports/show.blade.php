@php
    $preview = $preview ?? false;
    $title = $version->title ?? $report->title;
    $description = $version->description ?? '';
    $sections = [];
    foreach ($blocks as $index => $block) {
        if ($block['type'] === 'text') {
            preg_match('/<h[23]\b[^>]*>(.*?)<\/h[23]>/is', \App\Support\ReportText::html($block), $heading);
            $label = isset($heading[1]) ? html_entity_decode(strip_tags($heading[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Section '.($index + 1);
        } else {
            $label = $block['artifact_title'] ?? $block['document']?->title ?? 'Artifact '.($index + 1);
        }
        $sections[] = ['anchor' => 'section-'.($index + 1), 'label' => trim($label) !== '' ? $label : 'Section '.($index + 1)];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    @if ($preview || $report->visibility !== 'public' || $report->password_hash)<meta name="robots" content="noindex">@endif
    <title>{{ $title }} · Framework</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#report-content" class="sr-only z-50 rounded bg-white px-4 py-3 text-sky-800 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to report content</a>
    @if ($preview)
        <div class="border-b border-amber-200 bg-amber-50 px-6 py-3 text-center text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            Saved draft preview. Readers see a published version. <a href="{{ url('/reports/'.$report->id.'/edit') }}" class="ml-2 font-semibold underline">Return to editor</a>
        </div>
    @endif
    <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-6 py-4 lg:px-10">
            <a href="{{ config('app.url') }}" class="inline-flex items-center gap-2 text-sm font-semibold tracking-tight"><img src="{{ asset('images/logo.png') }}" alt="" class="h-6 w-auto">Framework <span class="font-normal text-slate-400">/ Research report</span></a>
            <div class="flex items-center gap-4 print:hidden">
                @if (!$preview && count($versions ?? []) > 1)
                    <label for="report-version" class="sr-only">Report version</label>
                    <select id="report-version" onchange="location.href=this.value" class="max-w-48 rounded-md border border-slate-200 bg-transparent px-2 py-1 text-xs dark:border-slate-700">
                        @foreach ($versions as $published)
                            <option value="{{ $published['url'] }}" @selected($published['number'] === $version->number)>Version {{ $published['number'] }} · {{ $published['date']?->toFormattedDateString() }}</option>
                        @endforeach
                    </select>
                @endif
                <x-theme-toggle />
            </div>
        </div>
    </header>
    <div class="border-b border-slate-200 bg-gradient-to-b from-white to-sky-50/50 dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
        <div class="mx-auto max-w-7xl px-6 py-14 sm:py-20 lg:px-10">
            <div class="max-w-4xl">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $project->name }}</p>
                <h1 class="mt-5 max-w-4xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">{{ $title }}</h1>
                @if ($description)<p class="mt-6 max-w-3xl whitespace-pre-line text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $description }}</p>@endif
                <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 font-medium dark:border-slate-700 dark:bg-slate-900">{{ $preview ? 'Draft preview' : 'Published report · Version '.$version->number }}</span>
                    @if (!$preview && $version->created_at)<span>{{ $version->created_at->toFormattedDateString() }}</span>@endif
                    <span>{{ count($blocks) }} {{ count($blocks) === 1 ? 'section' : 'sections' }}</span>
                    @if (!$preview)<span>Artifact versions preserved at publication</span>@endif
                </div>
            </div>
        </div>
    </div>
    <div class="mx-auto grid max-w-7xl gap-10 px-6 py-10 lg:grid-cols-[12rem_minmax(0,1fr)] lg:px-10 lg:py-14">
        <aside class="min-w-0 print:hidden">
            <nav aria-label="Report sections" class="lg:sticky lg:top-8">
                <h2 class="text-sm font-medium text-slate-500">Contents</h2>
                <ol class="mt-5 flex flex-col gap-3 border-l border-slate-200 pl-4 dark:border-slate-700">
                    @foreach ($sections as $section)
                        <li><a href="#{{ $section['anchor'] }}" class="block break-words text-sm leading-relaxed text-slate-500 hover:text-sky-700 dark:text-slate-400 dark:hover:text-sky-300">{{ $section['label'] }}</a></li>
                    @endforeach
                </ol>
                @if (count($sections) === 0)<p class="mt-4 text-sm text-slate-500">No sections yet.</p>@endif
            </nav>
        </aside>
        <main id="report-content" class="min-w-0 space-y-12" tabindex="-1">
            @forelse ($blocks as $index => $block)
                <section id="section-{{ $index + 1 }}" class="min-w-0 scroll-mt-8" aria-label="{{ $sections[$index]['label'] }}">
                    @if ($block['type'] === 'text')
                        <div class="report-prose max-w-3xl break-words">
                            {!! \App\Support\ReportText::html($block) !!}
                        </div>
                    @else
                        @php
                            $document = $block['document'];
                            $artifactTitle = $block['artifact_title'] ?? $document?->title ?? $document?->slug ?? 'Artifact';
                            $artifactKind = $block['artifact_kind'] ?? $document?->kind;
                            $available = $block['available'] ?? false;
                            $canRead = $block['can_read'] ?? false;
                            $url = $block['url'] ?? null;
                            $frameUrl = $url ? $url.(str_contains($url, '?') ? '&' : '?').'embed=1' : null;
                            $kindLabel = ['html' => 'Document', 'pdf' => 'PDF', 'image' => 'Figure', 'chart' => 'Interactive chart', 'table' => 'Table', 'data' => 'Data',
                                'model' => 'Model', 'test' => 'Hypothesis test', 'text' => 'Text file', 'file' => 'File'][$artifactKind] ?? null;
                        @endphp
                        <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $available && $kindLabel ? $kindLabel : 'Supporting artifact' }}</p>
                                    <h2 class="mt-2 text-lg font-semibold">{{ $available ? $artifactTitle : 'Artifact unavailable' }}</h2>
                                    @if ($available)<p class="mt-1 text-xs text-slate-500">{{ isset($block['version_number']) ? 'Version '.$block['version_number'] : 'Latest version, until published' }}{{ ($block['password_protected'] ?? false) ? ' · Separate password required' : '' }}</p>@endif
                                </div>
                                @if ($available && $url)
                                    <div class="flex flex-wrap gap-2">
                                        @if ($canRead && $document && !empty($block['version_number']))<a href="{{ route('documents.version.download', ['key' => $document->key, 'number' => $block['version_number']]) }}" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Download</a>@endif
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-sky-700 hover:bg-sky-50 dark:border-slate-700 dark:text-sky-300 dark:hover:bg-slate-800">Open artifact ↗</a>
                                    </div>
                                @endif
                            </div>
                            @if ($available && $canRead && $frameUrl)
                                <iframe src="{{ $frameUrl }}" title="{{ $artifactTitle }}" loading="lazy" referrerpolicy="no-referrer" data-fit-frame class="h-[32rem] w-full border-0 bg-white dark:bg-slate-950"></iframe>
                            @elseif ($available && !$canRead && $url)
                                <div class="flex min-h-52 flex-col items-center justify-center gap-3 px-6 py-10 text-center">
                                    <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-lg dark:bg-slate-800">↗</span>
                                    <p class="font-medium">This artifact has its own access requirements.</p>
                                    <p class="max-w-md text-sm leading-relaxed text-slate-500">Open the artifact and enter its password. Then reload this report to view it here.</p>
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-2 rounded-lg bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Open protected artifact</a>
                                </div>
                            @else
                                <div class="px-6 py-10 text-center"><p class="text-sm leading-relaxed text-slate-500">The artifact or the version included in this report is no longer available. The report does not substitute a newer version.</p></div>
                            @endif
                            @if (!empty($block['caption']))<figcaption class="border-t border-slate-100 bg-slate-50/70 px-5 py-4 text-sm leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">{{ $block['caption'] }}</figcaption>@endif
                        </figure>
                    @endif
                </section>
            @empty
                <p class="py-10 text-sm text-slate-500">This report has no sections yet.</p>
            @endforelse
        </main>
    </div>
    <footer class="mx-auto mt-8 flex max-w-7xl flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-6 py-8 text-xs text-slate-500 dark:border-slate-800 lg:px-10">
        <span>{{ $title }}{{ !$preview ? ' · Version '.$version->number : '' }}</span>
        <a href="{{ config('app.url') }}" class="hover:text-sky-700 dark:hover:text-sky-300">Published with Framework</a>
    </footer>
    <script>
        // Embedded artifacts come from this site, so each frame can take the height of what it shows
        document.querySelectorAll('[data-fit-frame]').forEach((frame) => frame.addEventListener('load', () => {
            try {
                const content = frame.contentDocument?.querySelector('main') || frame.contentDocument?.body;
                if (content) frame.style.height = `${Math.min(1600, Math.max(200, content.scrollHeight + 4))}px`;
            } catch { /* a frame from another origin keeps its height */ }
        }));
    </script>
</body>
</html>
