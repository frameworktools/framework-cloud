<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Self-hosting — Framework Cloud</title>
    <meta name="description" content="Self-hosted Framework Cloud with Docker. Server features, artifact storage, installation, and administration.">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

    @php
        $section = 'border-t border-slate-200 py-12 sm:py-16 dark:border-slate-800';
        $h2 = 'text-2xl font-semibold tracking-tight text-balance sm:text-3xl';
        $muted = 'text-slate-600 dark:text-slate-400';
        $code = 'rounded bg-slate-100 px-1 py-0.5 font-mono text-[0.9em] text-slate-800 dark:bg-slate-800 dark:text-slate-200';
        $link = 'font-medium text-sky-600 hover:underline dark:text-sky-400';

        $includes = [
            ['heroicon-o-square-3-stack-3d', 'Blueprints and projects', 'Built-in and custom blueprints, skills, and project setup. Projects can be local or on GitHub.'],
            ['heroicon-o-paper-airplane', 'Artifact publishing', 'Every account can publish artifacts. You set the storage, on the server or in a bucket.'],
            ['heroicon-o-user-circle', 'Account management', 'Email links, passkeys, passwords, and optional GitHub sign-in. Registration is configurable.'],
            ['heroicon-o-computer-desktop', 'Single-user mode', 'Local installation with no sign-in screen. Requests from that computer are signed in as you.'],
        ];

        $manage = [
            ['Docker host', 'Docker with the Compose plugin. For a shared server, a domain that points at the machine, with ports 80 and 443 open.'],
            ['Database and files', 'PostgreSQL runs in its own container. Back up the database, the storage volume, and the application key.'],
            ['Email', 'Sign-in links and password resets need an email service, such as SMTP or Amazon SES. Until it is set up, admins can print sign-in links on the server.'],
            ['GitHub integration (optional)', 'Creating projects on GitHub needs a GitHub App for your server. The setup page opens GitHub with the app\'s settings filled in.'],
            ['Updates', 'Pull a newer release and restart. You can pin a version. Database changes run when the server starts.'],
        ];

        $docs = [
            ['install-docker', 'Docker installation', 'Requirements, configuration, and the first sign-in.'],
            ['running-at-home', 'Local installation', 'A private, single-user setup.'],
            ['server-setup', 'Server setup', 'Email, GitHub, and artifact storage.'],
            ['configuration', 'Configuration reference', 'Every environment variable.'],
            ['maintenance', 'Maintenance', 'Upgrades, backups, and admin commands.'],
            ['install-manual', 'Manual installation', 'For servers that already run PHP.'],
        ];
    @endphp

    <div class="relative overflow-hidden border-b border-sky-900/10 bg-gradient-to-b from-sky-100 via-sky-50 to-white text-slate-900 dark:border-white/10 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 dark:text-slate-50">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(40%_60%_at_85%_20%,rgba(255,255,255,0.9),transparent_70%),radial-gradient(30%_45%_at_65%_75%,rgba(255,255,255,0.7),transparent_70%)] dark:bg-[radial-gradient(60%_80%_at_100%_0%,rgba(14,165,233,0.18),transparent_70%)]"></div>
    <x-site-header overlay />

    {{-- Intro: matches the landing page hero --}}
    <section class="relative">
        <div class="relative mx-auto max-w-6xl px-6 py-16 sm:py-20">
            <div class="max-w-2xl">
                <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Self-hosted Framework Cloud</h1>
                <p class="mt-6 text-lg leading-relaxed text-slate-700 dark:text-slate-300">
                    Framework Cloud is open source. Host it for your lab, department, or institution.
                    A single-user installation runs on a personal computer.
                </p>
                <p class="mt-4 text-slate-600 dark:text-slate-400">
                    A self-hosted server includes the same features as Framework Public Cloud.
                    You manage updates, data, and published artifacts.
                </p>
                <div class="mt-10 flex flex-wrap items-center gap-3">
                    <a href="{{ route('guide', 'install-docker') }}" class="rounded-md bg-sky-600 px-5 py-2.5 font-medium text-white hover:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">Read the installation guide</a>
                    <a href="https://github.com/frameworktools/framework-cloud" class="rounded-md border border-slate-300 bg-white/60 px-5 py-2.5 font-medium text-slate-800 hover:border-slate-400 hover:bg-white dark:border-slate-500 dark:bg-transparent dark:text-slate-50 dark:hover:border-slate-300 dark:hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">Source code on GitHub</a>
                </div>
            </div>
        </div>
    </section>
    </div>

    <main class="mx-auto max-w-6xl px-6">

        {{-- What runs --}}
        <section class="py-12 sm:py-16">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16">
                <div>
                    <h2 class="{{ $h2 }}">Server components</h2>
                    <div class="mt-4 flex flex-col gap-3 {{ $muted }}">
                        <p>
                            Each release is a Docker image. Docker Compose runs three containers:
                            a web server, a background worker, and a PostgreSQL database.
                            The worker creates GitHub repositories and sends email.
                        </p>
                        <p>
                            Download the Compose file and settings template. Start the containers.
                        </p>
                        <p>
                            The web server obtains a Let’s Encrypt HTTPS certificate for your domain.
                        </p>
                        <p>
                            Source datasets and analysis code stay on your users' computers and in
                            their repositories. Connected features send project settings,
                            blueprints, and, with the integrity ledger, file fingerprints and
                            related metadata. Publishing uploads the selected artifact and its supporting files.
                            Rendered notebooks can include code, tables, and figures.
                        </p>
                    </div>
                </div>
<x-code-panel title="Terminal" wrap>
<span class="text-slate-500"># Download the Compose file and the settings template</span>
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env

<span class="text-slate-500"># Fill in .env, then start it</span>
docker compose up -d</x-code-panel>
            </div>
        </section>

        {{-- What it includes --}}
        <section class="{{ $section }}">
            <h2 class="{{ $h2 }}">Server features</h2>
            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($includes as [$icon, $title, $body])
                    <div class="flex gap-4 rounded-xl border border-slate-200 p-5 dark:border-slate-800">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                            <x-dynamic-component :component="$icon" class="size-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-normal {{ $muted }}">{{ $body }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Published notebooks --}}
        <section class="{{ $section }}">
            <div class="max-w-2xl">
                <h2 class="{{ $h2 }}">Artifact storage</h2>
                <div class="mt-4 flex flex-col gap-3 {{ $muted }}">
                    <p>
                        Artifacts use the server’s disk by default, in a Docker volume.
                        For shared servers, use an S3-compatible bucket, such as Amazon S3,
                        Cloudflare R2, MinIO, or Backblaze B2.
                    </p>
                    <p>
                        Published HTML can contain scripts. On shared servers, serve HTML
                        from a separate domain, such as
                        <code class="{{ $code }}">files.example.com</code>.
                        <a href="{{ route('guide', 'server-setup') }}#published-artifacts" class="{{ $link }}">Artifact storage configuration</a>
                    </p>
                </div>
            </div>
        </section>

        {{-- Server administration --}}
        <section class="{{ $section }}">
            <h2 class="{{ $h2 }}">Server administration</h2>
            <dl class="mt-8 divide-y divide-slate-200 border-y border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                @foreach ($manage as [$title, $body])
                    <div class="grid gap-1 py-4 sm:grid-cols-[16rem_1fr] sm:gap-6">
                        <dt class="font-medium">{{ $title }}</dt>
                        <dd class="text-sm leading-relaxed {{ $muted }}">{{ $body }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-6 text-sm {{ $muted }}">
                Credentials saved on the server's setup page are encrypted with its application
                key. Keep a copy of the key with your backups.
                <a href="{{ route('guide', 'maintenance') }}" class="{{ $link }}">Backups and upgrades</a>
            </p>
        </section>

        {{-- Installation --}}
        <section class="{{ $section }}">
            <div class="rounded-2xl bg-slate-50 p-6 sm:p-10 dark:bg-slate-900">
                <h2 class="{{ $h2 }}">Installation</h2>
                <p class="mt-3 max-w-2xl {{ $muted }}">
                    The installation guide covers requirements, configuration, and the first
                    sign-in. Start with Docker unless you already run PHP applications.
                </p>
                <a href="{{ route('guide', 'install-docker') }}" class="mt-6 inline-block rounded-md bg-sky-600 px-5 py-2.5 font-medium text-white hover:bg-sky-700">Install with Docker</a>

                <ul class="mt-10 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($docs as [$slug, $title, $body])
                        <li>
                            <a href="{{ route('guide', $slug) }}" class="{{ $link }}">{{ $title }}</a>
                            <p class="mt-0.5 text-sm {{ $muted }}">{{ $body }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Hosted option --}}
        <section class="{{ $section }} text-center">
            <h2 class="text-xl font-semibold tracking-tight">Framework Public Cloud</h2>
            <p class="mx-auto mt-3 max-w-xl {{ $muted }}">
                Framework Public Cloud at framework.pub is free to use. We run and update the
                server. Each account includes 1 GB of artifact storage.
            </p>
            <a href="{{ route('register') }}" class="mt-6 inline-block rounded-md border border-slate-300 px-5 py-2.5 font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-900">Create an account on Framework Public Cloud</a>
        </section>

    </main>

    <x-site-footer />

</body>
</html>
