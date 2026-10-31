@php
    use App\Support\Docs\Products;

    $siteTitle = $site['title'] ?? Products::NAMES[$product];
    $repository = $site['repository'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · {{ $siteTitle }}@else{{ $siteTitle }}@endif</title>
    <meta name="description" content="@yield('description', $site['tagline'] ?? 'Documentation for '.$siteTitle.'.')">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-theme-script />
    @vite(['resources/css/docs.css'])
</head>
<body class="flex min-h-full flex-col bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">

    @include('docs.partials.header')

    @hasSection('full')
        <main class="flex-1">@yield('full')</main>
    @else
        <div class="mx-auto flex w-full max-w-8xl flex-1 justify-center px-6 sm:px-8 lg:px-12">
            @if ($site)
                <aside class="hidden w-64 shrink-0 lg:block">
                    <nav class="sticky top-[4.5rem] -ml-2 max-h-[calc(100vh-4.5rem)] overflow-y-auto py-10 pr-6 pl-2 text-sm" aria-label="Sidebar">
                        @include('docs.partials.sidebar')
                    </nav>
                </aside>
            @endif

            <main class="min-w-0 max-w-3xl flex-auto py-10 lg:pl-8 xl:px-12">
                @yield('content')
            </main>

            <aside class="hidden w-56 shrink-0 xl:block">
                @hasSection('toc')
                    <nav class="sticky top-[4.5rem] max-h-[calc(100vh-4.5rem)] overflow-y-auto py-10 text-sm" aria-label="On this page">
                        @yield('toc')
                    </nav>
                @endif
            </aside>
        </div>
    @endif

    <footer class="border-t border-slate-200 dark:border-slate-800">
        <div class="mx-auto flex max-w-8xl flex-wrap items-center justify-between gap-4 px-6 py-6 text-sm text-slate-500 sm:px-8 lg:px-12 dark:text-slate-400">
            <div>
                {{ $siteTitle }}@if (! empty($site['version']) && $site['version'] !== 'docs.db') · version {{ $site['version'] }}@endif
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                @foreach (Products::LABELS as $other => $label)
                    @if ($other !== $product)
                        <a href="{{ Products::url($other) }}" class="hover:text-slate-900 dark:hover:text-white">{{ $label }} documentation</a>
                    @endif
                @endforeach
                <a href="https://framework.pub" class="hover:text-slate-900 dark:hover:text-white">Framework Public Cloud</a>
                @if ($repository)
                    <a href="{{ $repository }}" class="hover:text-slate-900 dark:hover:text-white">Source on GitHub</a>
                @endif
            </div>
        </div>
    </footer>

    @if ($site)
        @include('docs.partials.search')
    @endif

    <script>
        // One button for light and dark, as on the old site; the theme script remembers the choice
        document.querySelectorAll('[data-docs-theme]').forEach((button) => {
            button.addEventListener('click', () => {
                window.fwTheme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
            });
        });

        // Copy buttons on code blocks
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copy);
                try { await navigator.clipboard.writeText(target.innerText.trim()); } catch { return; }
                const label = button.querySelector('[data-copy-label]');
                label.textContent = 'Copied';
                setTimeout(() => { label.textContent = 'Copy'; }, 2000);
            });
        });

        // "On this page": mark the heading being read
        (() => {
            const links = [...document.querySelectorAll('[data-toc-link]')];
            if (!links.length) return;
            const headings = links.map((a) => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
            const mark = () => {
                let current = headings[0];
                for (const h of headings) if (h.getBoundingClientRect().top < 120) current = h;
                links.forEach((a) => a.toggleAttribute('data-current', a.getAttribute('href') === '#' + current?.id));
            };
            document.addEventListener('scroll', mark, { passive: true });
            mark();
        })();
    </script>
</body>
</html>
