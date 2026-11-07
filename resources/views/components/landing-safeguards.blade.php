@php
    $securityPanels = [];
    $securityFiles = [];
    foreach (['r' => 'R', 'py' => 'Python'] as $language => $label) {
        $securityPanels[$language] = ['label' => $label, 'path' => 'Terminal', 'code' => file_get_contents(resource_path('examples/landing/git-commit.txt'))];
        $extension = $language === 'py' ? 'py' : 'R';
        $securityFiles[$language] = [
            '.gitignore' => "# Ignore rule disabled by mistake\n# /inputs/private/\n/outputs/private/\n.env\nframework.db\n",
            'inputs/private/raw/penguins.csv' => "species,island,flipper_length_mm,body_mass_g\nAdelie,Torgersen,181,3750\nGentoo,Biscoe,215,5150\n",
            'settings/git.yml' => "default:\n  git:\n    hooks:\n      data_security: true\n",
            "scripts/verify.$extension" => $language === 'py'
                ? "import framework\n\n# Record the input hash and check it before publishing.\n# Locked catalog entries reject changed files on read.\npenguins = framework.data_read(\"inputs.private.penguins\")\nframework.data_verify(\"inputs.private.penguins\")"
                : "# Record the input hash and check it before publishing.\n# Locked catalog entries reject changed files on read.\npenguins <- framework::data_read(\"inputs.private.penguins\")\nframework::data_verify(\"inputs.private.penguins\")",
        ];
    }
@endphp
<section id="data" class="border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Data security and integrity</h2>
        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-400">Framework keeps private data out of Git, guards inputs against accidental changes, and records fingerprints you can show collaborators and reviewers.</p>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['lock-closed', 'Explicit private directories', 'Keep sensitive inputs and unreviewed outputs in private/ directories. Put reviewed results in public/ directories when they are ready to share.'],
            ['shield-check', 'Git safeguards', 'Each structure has an editable .gitignore template. Optional pre-commit checks catch private data and missing ignore rules before a commit.'],
            ['finger-print', 'File fingerprints', 'data_read() records input hashes and warns when data changes. Lock inputs to stop changed files from being read, and check them with data_verify().'],
            ['check-badge', 'Data verification ledger', 'Share input fingerprints and verification metadata through Framework Cloud for transparency. Your datasets stay on your machine.'],
        ] as [$icon, $heading, $body])
            <x-landing-frame class="p-5">
                <div><x-dynamic-component :component="'heroicon-o-'.$icon" class="mb-3 size-5 text-slate-500 dark:text-slate-400" /><h3 class="text-sm font-semibold">{{ $heading }}</h3></div>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{!! preg_replace('/\b(\w+\(\))/', '<code class="font-mono text-[0.9em] text-slate-800 dark:text-slate-200">$1</code>', e($body)) !!}</p>
            </x-landing-frame>
        @endforeach
    </div>
    <div id="integrity" class="mt-8 rounded-xl shadow-panel-deep dark:shadow-panel-deep-dark"><x-landing-editor :panels="$securityPanels" :files="$securityFiles" title="Data safeguards" /></div>
    <p class="mt-4 text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">Output excerpt from a synthetic example. The commit was blocked. Local Git hooks can be bypassed.</p>
</section>
