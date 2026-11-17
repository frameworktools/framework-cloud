@php
    use App\Support\Docs\Products;
    use App\Support\Seo;

    $siteTitle = $site['title'] ?? Products::NAMES[$product];
    $repository = $site['repository'] ?? null;

    // Structured data: the docs site, and the guide or reference entry on this page
    $docsHome = url($site['home'] ?? '/');
    $pageTitle = View::hasSection('title') ? trim(View::yieldContent('title')) : null;
    $pageDescription = trim(View::yieldContent('description', $site['tagline'] ?? 'Documentation for '.$siteTitle.'.'));
    $schema = [Seo::organization(), Seo::website($siteTitle, $docsHome)];
    $article = fn (string $headline, array $crumbs) => [
        ['@type' => 'TechArticle', '@id' => url()->current().'#article', 'headline' => $headline, 'description' => $pageDescription,
            'url' => url()->current(), 'inLanguage' => 'en', 'isPartOf' => ['@id' => $docsHome.'#website'],
            'publisher' => ['@id' => Seo::home().'#organization']],
        Seo::breadcrumbs(array_merge([[$siteTitle, $docsHome]], $crumbs, [[$headline, url()->current()]])),
    ];
    if (isset($guide) && url()->current() !== $docsHome) {
        $guideSection = collect($site['sections'] ?? [])->firstWhere('id', $guide['section'] ?? null);
        $schema = array_merge($schema, $article($guide['title'], $guideSection ? [[$guideSection['title'], $docsHome]] : []));
    } elseif (isset($entry)) {
        $schema = array_merge($schema, $article($pageTitle ?? $entry['name'], [['Reference', url('/reference')]]));
    }
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · {{ $siteTitle }}@else{{ $siteTitle }}@endif</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    {{-- Search results and an unpublished site stay out of search engines --}}
    <x-share-meta :title="$pageTitle ? $pageTitle.' · '.$siteTitle : $siteTitle" :description="$pageDescription" :type="isset($guide) || isset($entry) ? 'article' : 'website'" :noindex="isset($results) || ! $site" :schema="$schema" />
    <x-theme-script />
    @vite(['resources/css/docs.css'])
</head>
<body class="flex min-h-full flex-col bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">
    <a href="#main" class="sr-only rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-900 shadow-lg ring-1 ring-slate-900/10 focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] dark:bg-slate-800 dark:text-white">Skip to content</a>

    @include('docs.partials.header')

    @hasSection('full')
        <main id="main" tabindex="-1" class="flex-1 focus:outline-none">@yield('full')</main>
    @else
        <div class="mx-auto flex w-full max-w-8xl flex-1 justify-center px-6 sm:px-8 lg:px-12">
            @if ($site)
                <aside class="hidden w-64 shrink-0 lg:block">
                    <nav class="sticky top-[4.5rem] -ml-2 max-h-[calc(100vh-4.5rem)] docs-scroll overflow-y-auto py-10 pr-6 pl-2 text-sm" aria-label="Sidebar">
                        @include('docs.partials.sidebar')
                    </nav>
                </aside>
            @endif

            <main id="main" tabindex="-1" class="min-w-0 max-w-3xl flex-auto focus:outline-none py-10 lg:pl-8 xl:px-12">
                @yield('content')
            </main>

            <aside class="hidden w-56 shrink-0 xl:block">
                @hasSection('toc')
                    <nav class="sticky top-[4.5rem] max-h-[calc(100vh-4.5rem)] docs-scroll overflow-y-auto py-10 text-sm" aria-label="On this page">
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
                @foreach (Products::switcher($product) as $other => $label)
                    @if ($other !== $product)
                        <a href="{{ Products::docsUrl($other) }}" class="hover:text-slate-900 dark:hover:text-white">{{ $label }} documentation</a>
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
        <x-docs-search :product="$product" :index="$site['search_index'] ?? '/search.json'" />
    @endif

    <x-code-blocks />

    <script>
        // One button for light and dark, as on the old site; the theme script remembers the choice
        document.querySelectorAll('[data-docs-theme]').forEach((button) => {
            button.addEventListener('click', () => {
                window.fwTheme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
            });
        });

        // Header menus, as on the home page: one open at a time; Escape or a click elsewhere closes it
        document.querySelectorAll('[data-site-menu]').forEach((menu) => {
            menu.addEventListener('toggle', () => { if (menu.open) document.querySelectorAll('[data-site-menu]').forEach((other) => { if (other !== menu) other.open = false; }); });
            menu.addEventListener('keydown', (event) => { if (event.key === 'Escape') { menu.open = false; menu.querySelector('summary').focus(); } });
            document.addEventListener('click', (event) => { if (!menu.contains(event.target)) menu.open = false; });
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

        // R or Python tabs on code samples (CloudGuide::languageTabs): every
        // group follows the last choice, and this browser remembers it
        (() => {
            const groups = document.querySelectorAll('[data-code-tabs]');
            if (!groups.length) return;
            const show = (lang) => groups.forEach((group) => {
                group.querySelectorAll('[data-lang]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.lang === lang)));
                group.querySelectorAll('[data-lang-panel]').forEach((p) => { p.hidden = p.dataset.langPanel !== lang; });
            });
            let saved = null;
            try { saved = localStorage.getItem('docs-language'); } catch {}
            if (saved === 'python') show('python');
            groups.forEach((group) => group.addEventListener('click', (event) => {
                const lang = event.target.closest('[data-lang]')?.dataset.lang;
                if (!lang) return;
                show(lang);
                try { localStorage.setItem('docs-language', lang); } catch {}
            }));
        })();

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
