@php
    // Before and after: the setup code a project repeats by hand, and the Framework call that
    // replaces it, in each language Framework supports for the topic. Each side is a list of
    // [file, code] blocks; a null file shows no caption. A third item, 'tree' or 'output',
    // shows a folder tree or console output instead of highlighted code. Topics without a language key apply
    // to every language and show no language tabs.
    $groups = [
        'Project setup' => [
            'structure' => ['Curated project structures', 'Curated project structures', [
                'r' => [[[null, "penguin-study/\n├── data/\n│   ├── penguins.csv\n│   └── penguins_clean.csv\n├── analysis.R\n├── analysis_v2_FINAL.R\n├── clean_old.R\n├── penguins_clean (1).csv\n├── Rplot01.png\n├── flipper_mass_final.png\n├── model.rds\n├── table1.docx\n├── .env\n├── .gitignore  # data/*.csv\n└── notes.txt", 'tree']], [[null, "penguin-study/\n├── inputs/\n│   ├── private/  # never shared\n│   │   ├── raw/\n│   │   │   └── penguins.csv\n│   │   └── intermediate/\n│   │       └── penguins_clean.csv\n│   └── public/   # shareable\n├── scripts/\n│   ├── clean.R\n│   └── analysis.R\n├── outputs/\n│   ├── private/  # never shared\n│   │   └── models/\n│   │       └── model.rds\n│   └── public/   # shareable\n│       ├── figures/\n│       │   ├── flipper-mass.png\n│       │   └── body_mass.png\n│       └── tables/\n│           └── body-mass-by-species.csv\n├── docs/\n│   └── notes.txt\n├── .env\n├── settings.yml\n├── scaffold.R\n└── .gitignore    # **/private/**", 'tree']]],
                'py' => [[[null, "penguin-study/\n├── data/\n│   ├── penguins.csv\n│   └── penguins_clean.csv\n├── analysis.ipynb\n├── Untitled.ipynb\n├── Untitled1.ipynb\n├── helpers.py\n├── penguins_clean (1).csv\n├── figure1.png\n├── flipper_mass_final.png\n├── model.pkl\n├── results.xlsx\n├── .env\n├── .gitignore  # data/*.csv\n└── requirements.txt", 'tree']], [[null, "penguin-study/\n├── inputs/\n│   ├── private/  # never shared\n│   │   ├── raw/\n│   │   │   └── penguins.csv\n│   │   └── intermediate/\n│   │       └── penguins_clean.csv\n│   └── public/   # shareable\n├── notebooks/\n│   └── analysis.ipynb\n├── functions/\n│   └── helpers.py\n├── outputs/\n│   ├── private/  # never shared\n│   │   └── models/\n│   │       └── model.pkl\n│   └── public/   # shareable\n│       ├── figures/\n│       │   ├── flipper-mass.png\n│       │   └── body_mass.png\n│       └── tables/\n│           └── body-mass-by-species.csv\n├── .env\n├── pyproject.toml\n├── settings.yml\n├── scaffold.py\n└── .gitignore    # **/private/**", 'tree']]],
                'stata' => [[[null, "penguin-study/\n├── data/\n│   ├── penguins.dta\n│   └── penguins_clean.dta\n├── master_old.do\n├── analysis.do\n├── analysis_v2.do\n├── penguins.csv\n├── penguins_clean2.dta\n├── Graph.gph\n├── Graph.png\n├── results.rtf\n├── analysis.log\n├── .gitignore  # data/*.dta\n└── notes.txt", 'tree']], [[null, "penguin-study/\n├── inputs/\n│   ├── private/  # never shared\n│   │   ├── raw/\n│   │   │   └── penguins.csv\n│   │   ├── intermediate/\n│   │   │   └── penguins.dta\n│   │   └── final/\n│   │       └── penguins_clean.dta\n│   └── public/   # shareable\n├── do/\n│   └── analysis.do\n├── outputs/\n│   ├── private/  # never shared\n│   │   └── tables/\n│   │       └── results.rtf\n│   └── public/   # shareable\n│       ├── figures/\n│       │   └── flipper-mass.png\n│       └── tables/\n│           └── body-mass-by-species.csv\n├── logs/\n│   └── analysis.log\n├── docs/\n│   └── notes.txt\n├── master.do\n├── settings.do\n├── settings.yml\n└── .gitignore    # **/private/**", 'tree']]],
            ], 'Our project structures scale as your project grows and make sense to collaborators at a glance. Sensitive inputs and unreviewed outputs go in private/ folders. Reviewed results go in public/ folders. Prefer your own structure? Framework supports that, and you can share it through Framework Cloud.'],
            'packages' => ['Sane package management', 'Keep a dictionary of your packages for one-step installation and loading.', [
                'r' => [
                    [[null, "pkgs <- c(\"dplyr\", \"ggplot2\", \"readr\")\nnew <- setdiff(pkgs, rownames(installed.packages()))\ninstall.packages(new)\nlibrary(dplyr)\nlibrary(ggplot2)"]],
                    [['settings/packages.yml', "packages:\n  default_packages:\n    - name: dplyr\n      auto_attach: true\n    - name: ggplot2\n      auto_attach: true\n    - name: readr\n      auto_attach: false"], [null, "scaffold()  # installs all three, attaches two\npenguins <- readr::read_csv(\"inputs/private/raw/penguins.csv\")"]],
                ],
                'py' => [
                    [['Terminal', "pip install polars matplotlib\n# Then add both to requirements.txt by hand"]],
                    [[null, "fw.package_add(\"polars\")  # pyproject.toml and uv"]],
                ],
                'stata' => [
                    [[null, "// README: run these before master.do\nssc install estout\nssc install reghdfe\nssc install ftools"]],
                    [['settings.yml', "packages:\n  project_ado: true\n  default_packages:\n    - estout\n    - reghdfe\n    - ftools"], [null, "fw_packages install"]],
                ],
            ], [
                'r' => 'Declare your packages in a settings file. Framework installs them, and `scaffold()` can load them for you. Collaborators and AI agents can see what your project needs.',
                'py' => 'Declare your packages in pyproject.toml. Framework installs them. Collaborators and AI agents can see what your project needs.',
                'stata' => 'Declare your packages in a settings file. Framework installs them, and `fw_scaffold` adds any that are missing. Collaborators and AI agents can see what your project needs.',
            ]],
        ],
        'Analysis' => [
            // A map of the project instead of a before and after: each part links to the topic that shows it.
            'reproducible' => ['Reproducible analyses', 'Framework encourages conventions for packages, setup, data, and results.', ['map' => [
                "penguin-study/\n├── settings/\n│   ├── packages.yml\n│   └── scaffold.yml\n├── inputs/\n│   └── private/   # never in Git\n│       ├── raw/\n│       └── final/\n├── scripts/\n│   ├── clean.R\n│   └── analysis.R\n└── outputs/\n    └── public/",
                [
                    ['Packages', 'Framework installs missing packages declared in the project settings.', 'packages'],
                    ['Script setup', '`scaffold()` sets paths from the project root, loads project functions, and can set a random seed.', 'session'],
                    ['Input data', '`data_read()` records a fingerprint of each input and warns or stops when a locked input changes.', 'integrity'],
                    ['Results', 'Save helpers record figures and tables. Framework Cloud can publish them as versioned artifacts.', 'outputs'],
                    ['Private folders', '.gitignore excludes `private/` folders, so scripts, settings, and `public/` outputs can be committed.', 'git'],
                ],
            ]], 'Projects can use different folder structures. Each records its folders, packages, and setup options in settings.yml, and Framework\'s functions read from it.'],
            'session' => ['Setup code in one place', 'Bootstrap every script or notebook with one line.', [
                'r' => [
                    [[null, "setwd(\"~/projects/penguin-study\")\nreadRenviron(\".env\")\nlibrary(dplyr)\nlibrary(ggplot2)\nsource(\"R/clean.R\")\nsource(\"R/models.R\")\nset.seed(123)\ntheme_set(theme_minimal())"]],
                    [[null, "library(framework)\nscaffold()"]],
                ],
                'py' => [
                    [[null, "import os, random, sys\nimport numpy as np\nfrom dotenv import load_dotenv\n\nos.chdir(\"..\")\nload_dotenv(\".env\")\nsys.path.insert(0, \"functions\")\nrandom.seed(123)\nnp.random.seed(123)"]],
                    [[null, "import framework as fw\n\nfw.scaffold()"]],
                ],
                'stata' => [
                    [[null, "global root \"C:/Users/me/penguin-study\"\ncd \"\$root\"\nglobal raw \"\$root/data/raw\"\nglobal figures \"\$root/output/figures\"\nset seed 123\nset scheme s2color"]],
                    [[null, "fw_scaffold"]],
                ],
            ], [
                'r' => 'The `scaffold()` function sets up your environment, makes sure your packages are installed, and loads your project\'s functions. It replaces the `source()` and `library()` calls at the top of every script.',
                'py' => 'The `fw.scaffold()` function sets up your environment and loads your project\'s functions. It replaces the `os.chdir()` and `sys.path` edits at the top of every notebook.',
                'stata' => 'The `fw_scaffold` command sets up your environment, installs missing packages, and loads your project\'s programs. It replaces the hard-coded paths at the top of every do-file.',
            ]],
            'outputs' => ['Organized outputs', 'Catalog and publish your project\'s deliverables.', [
                'r' => [
                    [[null, "dir.create(\"output/figures\", recursive = TRUE)\nggsave(\"output/figures/flipper-mass.png\", plot,\n       width = 8, height = 6)\nwrite.csv(summary, \"output/body-mass.csv\")"]],
                    [[null, "save_figure(plot, \"flipper-mass\", public = TRUE)\nsave_table(summary, \"body-mass-by-species\", public = TRUE)\nresult_list()[c(\"name\", \"type\")]"], ['Console', "                                            name   type\n1 outputs/public/tables/body-mass-by-species.csv  table\n2        outputs/public/figures/flipper-mass.png figure", 'output']],
                ],
                'py' => [
                    [[null, "os.makedirs(\"output/figures\", exist_ok=True)\nfig.savefig(\"output/figures/flipper-mass.png\")\nsummary.to_csv(\"output/body-mass.csv\")"]],
                    [[null, "fw.save_figure(fig, \"flipper-mass\", public=True)\nfw.save_table(summary, \"body-mass-by-species\", public=True)\nfw.result_list()[[\"name\", \"type\"]]"], ['Console', "                                             name    type\n0  outputs/public/tables/body-mass-by-species.csv   table\n1         outputs/public/figures/flipper-mass.png  figure", 'output']],
                ],
                'stata' => [
                    [[null, "capture mkdir \"\$figures\"\ngraph export \"\$figures/flipper-mass.png\", replace\ncollapse (mean) body_mass_g, by(species)\nexport delimited \"output/body-mass.csv\", replace"]],
                    [[null, "fw_graph flipper-mass, public\ncollapse (mean) body_mass_g, by(species)\nfw_table body-mass-by-species, public"], ['Console', "Saved figure to outputs/public/figures/flipper-mass.png\nSaved table to outputs/public/tables/body-mass-by-species.csv", 'output']],
                ],
            ], 'Framework\'s save helpers record each figure, table, and model you save. With Framework Cloud, you can publish and share plots, tables, models, notebooks, and more.'],
            'publish' => ['Publish study artifacts', 'Use Framework Cloud to share your work.', [
                'r' => [
                    [[null, "quarto::quarto_render(\"notebooks/analysis.qmd\")\n# Email analysis.html to the team\n# Copy the model to a shared drive"]],
                    [[null, "publish_notebook(\"notebooks/analysis.qmd\")\npublish(model, slug = \"penguin-body-mass\")"]],
                ],
                'py' => [
                    [['Terminal', "\$ jupyter nbconvert --to html analysis.ipynb\n# Email analysis.html to the team\n# Copy the model to a shared drive"]],
                    [[null, "fw.publish_notebook(\"notebooks/analysis.ipynb\")\nfw.publish(model, slug=\"penguin-body-mass\")"]],
                ],
                'stata' => [
                    [[null, "regress body_mass_g flipper_length_mm\nesttab using \"\$tables/body-mass.rtf\", replace\n// Email body-mass.rtf to the team"]],
                    [[null, "regress body_mass_g flipper_length_mm\nfw_publish, estimates slug(penguin-body-mass)"]],
                ],
            ], 'Framework packages integrate with Framework Cloud, so you can share versioned artifacts with colleagues and the public. Each one gets a clean, readable page.'],
            'caching' => ['Cache slow operations', 'Run expensive operations once.', [
                'r' => [
                    [[null, "if (file.exists(\"cache/fit.rds\")) {\n  fit <- readRDS(\"cache/fit.rds\")\n} else {\n  fit <- run_bootstrap(penguins)\n  saveRDS(fit, \"cache/fit.rds\")\n}"]],
                    [[null, "fit <- cache_remember(\n  \"fit\",\n  run_bootstrap(penguins),\n  expire_after = \"1 day\"\n)"]],
                ],
                'py' => [
                    [[null, "import pickle\n\npath = \"cache/fit.pkl\"\nif os.path.exists(path):\n    fit = pickle.load(open(path, \"rb\"))\nelse:\n    fit = run_bootstrap(penguins)\n    pickle.dump(fit, open(path, \"wb\"))"]],
                    [[null, "fit = fw.cache_remember(\n    \"fit\",\n    lambda: run_bootstrap(penguins),\n    expire_after=\"1 day\",\n)"]],
                ],
            ], 'Framework can reuse a cached result until it expires or you refresh it.'],
            'databases' => ['Easy database connections', 'Connect to local or remote databases by name.', [
                'r' => [
                    [[null, "con <- DBI::dbConnect(RPostgres::Postgres(),\n  host = \"db.example.org\", user = \"analyst\",\n  password = \"hunter2\")\nvisits <- DBI::dbGetQuery(con,\n  \"SELECT * FROM visits\")\nDBI::dbDisconnect(con)"]],
                    [['settings/connections.yml', "connections:\n  study_db:\n    driver: postgres\n    host: db.example.org\n    user: analyst\n    password: env(\"DB_PASSWORD\")"], [null, "visits <- db_query(\"SELECT * FROM visits\",\n                   \"study_db\")"]],
                ],
                'py' => [
                    [[null, "conn = psycopg.connect(host=\"db.example.org\",\n    user=\"analyst\", password=\"hunter2\")\nvisits = pd.read_sql(\"SELECT * FROM visits\", conn)\nconn.close()"]],
                    [['settings/connections.yml', "connections:\n  study_db:\n    driver: postgres\n    host: db.example.org\n    user: analyst\n    password: env(\"DB_PASSWORD\")"], [null, "visits = fw.db_query(\"SELECT * FROM visits\",\n                     \"study_db\")"]],
                ],
            ], 'Declare each connection once in a settings file, then query it by name from any script or notebook. Keep passwords in .env, which Git ignores, and read them with `env()`.'],
        ],
        'AI agents' => [
            // The same prompt without and with a figure skill. The skill lines are an example of a user's preferences.
            'ai' => ['AI context and skills', 'Give AI agents your project\'s conventions and task instructions.', [
                'r' => [
                    [['Prompt', "Plot body mass against flipper length\nby species.", 'output'], ['Result', "ggplot(penguins, aes(flipper_len, body_mass,\n                     color = species)) +\n  geom_point()"]],
                    [['.claude/skills/ggplot2-figures/SKILL.md', "- Label axes with plain names and units.\n- Use the Okabe-Ito palette for categories.\n- Use theme_minimal(base_size = 12).\n- Save with save_figure(plot, \"name\").", 'output'], ['Prompt', "Plot body mass against flipper length\nby species.", 'output'], ['Result', "p <- ggplot(penguins, aes(flipper_len, body_mass,\n                          color = species)) +\n  geom_point(alpha = 0.7) +\n  scale_color_manual(\n    values = palette.colors(4, \"Okabe-Ito\")[-1]) +\n  labs(x = \"Flipper length (mm)\",\n       y = \"Body mass (g)\", color = NULL) +\n  theme_minimal(base_size = 12)\nsave_figure(p, \"flipper-mass\", width = 6,\n            height = 4)"]],
                ],
                'py' => [
                    [['Prompt', "Plot body mass against flipper length\nby species.", 'output'], ['Result', "for species, group in df.groupby(\"species\"):\n    plt.scatter(group.flipper_len, group.body_mass)\nplt.show()"]],
                    [['.claude/skills/matplotlib-figures/SKILL.md', "- Label axes with plain names and units.\n- Use the Okabe-Ito palette for categories.\n- Remove the top and right spines.\n- Save with fw.save_figure(fig, \"name\").", 'output'], ['Prompt', "Plot body mass against flipper length\nby species.", 'output'], ['Result', "okabe_ito = [\"#E69F00\", \"#56B4E9\", \"#009E73\"]\nfig, ax = plt.subplots(figsize=(6, 4))\nfor (species, group), color in zip(\n        df.groupby(\"species\"), okabe_ito):\n    ax.scatter(group.flipper_len, group.body_mass,\n               color=color, alpha=0.7, label=species)\nax.set(xlabel=\"Flipper length (mm)\",\n       ylabel=\"Body mass (g)\")\nax.spines[[\"top\", \"right\"]].set_visible(False)\nax.legend(frameon=False)\nfw.save_figure(fig, \"flipper-mass\", width=6,\n               height=4)"]],
                ],
            ], 'Each project has a baseline context file with its conventions, set up for the agents you choose. Skills hold instructions for specific tasks. A ggplot2 or matplotlib skill tells agents your preferences for labels, colors, themes, and saving. Framework\'s packages keep skills in sync when you use more than one agent, and Framework Cloud manages your skills in one place.'],
        ],
        'Data safety' => [
            'integrity' => ['Data change detection', 'Know when an input file changes.', [
                'r' => [
                    [[null, "sums <- read.csv(\"data/checksums.csv\")\npath <- \"data/raw/penguins.csv\"\nexpected <- sums\$md5[sums\$file == path]\nif (tools::md5sum(path) != expected) {\n  stop(\"penguins.csv changed\")\n}\npenguins <- readr::read_csv(path)"]],
                    [[null, "penguins <- data_read(\"inputs.private.raw.penguins\")"]],
                ],
                'py' => [
                    [[null, "import hashlib, json\n\npath = \"data/raw/penguins.csv\"\nwith open(\"data/checksums.json\") as f:\n    expected = json.load(f)[path]\nwith open(path, \"rb\") as f:\n    md5 = hashlib.md5(f.read()).hexdigest()\nif md5 != expected:\n    raise ValueError(\"penguins.csv changed\")\npenguins = pd.read_csv(path)"]],
                    [[null, "penguins = fw.data_read(\"inputs.private.raw.penguins\")"]],
                ],
                'stata' => [
                    [[null, "checksum \"\$raw/penguins.csv\"\nlocal now = r(checksum)\nfile open fh using \"data/checksums.txt\", read\nfile read fh known\nfile close fh\nassert `now' == `known'\nimport delimited \"\$raw/penguins.csv\", clear"]],
                    [[null, "fw_use inputs.private.raw.penguins, clear"]],
                ],
            ], [
                'r' => '`data_read()` records a fingerprint of each input. Your scripts warn you or stop when a file changes. Lock an input to stop a changed file from being read, and check inputs with `data_verify()`. Your data stays on your machine.',
                'py' => '`fw.data_read()` records a fingerprint of each input. Your scripts warn you or stop when a file changes. Lock an input to stop a changed file from being read, and check inputs with `fw.data_verify()`. Your data stays on your machine.',
                'stata' => '`fw_use` records a fingerprint of each input. Your do-files warn you or stop when a file changes. Lock an input to stop a changed file from being read. Your data stays on your machine.',
            ]],
            'git' => ['Private data out of Git', 'Avoid data and secret leakage with out-of-the-box defense in depth.', [
                '*' => [
                    [['Terminal', "\$ git add .\n\$ git commit -m \"Add study data\"\n[main 4e1f2a7] Add study data\n create mode 100644 data/private/penguins.csv\n create mode 100644 .env"]],
                    [['Terminal', trim(file_get_contents(resource_path('examples/landing/git-commit.txt')))]],
                ],
            ], 'Curated project structures and .gitignore files keep data and secrets out of version control. Add Git hooks for R and Python projects as a second layer of protection.'],
        ],
    ];
    $languages = ['r' => 'R', 'py' => 'Python', 'stata' => 'Stata'];
    // Notes are plain text, with `code` in backticks.
    $prose = fn (string $text) => preg_replace('/`([^`]+)`/', '<code class="font-mono text-[0.85em] text-slate-700 dark:text-slate-300">$1</code>', e($text));
    // One list, most persuasive first.
    $order = ['reproducible', 'structure', 'session', 'integrity', 'packages', 'git', 'ai', 'outputs', 'databases', 'caching', 'publish'];
    $topics = array_replace(array_flip($order), array_merge(...array_values($groups)));
    $first = $order[0];
    // The heading reads "A Framework for …" and ends with the selected topic's tagline.
    $taglines = [
        'reproducible' => 'reproducible analyses',
        'session' => 'script setup',
        'integrity' => 'data integrity',
        'packages' => 'package management',
        'git' => 'data safety',
        'ai' => 'AI-assisted analysis',
        'structure' => 'project organization',
        'outputs' => 'organizing research artifacts',
        'databases' => 'data access',
        'caching' => 'performance',
        'publish' => 'sharing results',
    ];
    // Link each documentation site the header lists, joined as R, Python, Stata, or the command line.
    $docNames = ['r' => 'R', 'py' => 'Python', 'stata' => 'Stata', 'cli' => 'the command line'];
    $docLinks = collect($docNames)->only(array_keys(\App\Support\Docs\Products::listed()))
        ->map(fn (string $name, string $product) => '<a href="'.e(\App\Support\Docs\Products::publicUrl($product)).'" class="text-slate-800 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-200 dark:decoration-slate-600">'.e($name).'</a>')
        ->values();
    $docList = $docLinks->count() > 1 ? $docLinks->slice(0, -1)->implode(', ').($docLinks->count() > 2 ? ',' : '').' or '.$docLinks->last() : $docLinks->implode('');
@endphp
<section id="practices" data-practices class="scroll-mt-6 py-14 sm:py-20">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">
            A Framework for
            <span class="sr-only">best practices</span>
            {{-- Stacked in one grid cell so the line keeps the longest tagline's width; landing-hero.js slides in the selected topic's --}}
            <span data-practice-words aria-hidden="true" class="grid justify-items-center overflow-hidden pb-[0.08em] text-sky-700 dark:text-sky-300">
                @foreach ($topics as $key => $topic)
                    <span data-practice-word="{{ $key }}" @class(['[grid-area:1/1] text-balance sm:whitespace-nowrap transition duration-500 ease-out motion-reduce:transition-none', 'translate-y-full opacity-0' => $key !== $first])>{{ $taglines[$key] }}</span>
                @endforeach
            </span>
        </h2>
        <p class="mt-4 leading-relaxed text-balance text-slate-600 dark:text-slate-400">Framework's R, Python, and Stata packages organize inputs, code, and outputs in a project structure you choose. They install declared packages, set up each script and notebook from the project root, and record a fingerprint of each data file. Framework Cloud sets up projects and publishes versioned artifacts.</p>
    </div>

    <div class="mt-10">
        <x-landing-frame shadow="xl" class="overflow-hidden">
            <div class="grid min-w-0 md:grid-cols-[15rem_minmax(0,1fr)]">
                <div role="tablist" aria-label="Best practices" aria-orientation="vertical" class="flex gap-0.5 overflow-x-auto border-b border-slate-800/10 p-2 md:flex-col md:py-3 md:overflow-visible md:border-r md:border-b-0 dark:border-white/10">
                    @foreach ($topics as $key => [$title])
                            <button type="button" role="tab" id="practice-tab-{{ $key }}" aria-controls="practice-panel-{{ $key }}" aria-selected="{{ $key === $first ? 'true' : 'false' }}" tabindex="{{ $key === $first ? '0' : '-1' }}" data-practice-tab="{{ $key }}" class="shrink-0 rounded-md px-3 py-1.5 text-left text-sm leading-snug font-medium whitespace-nowrap md:whitespace-normal text-slate-700 hover:bg-slate-50 hover:text-slate-950 aria-selected:bg-sky-50 aria-selected:text-sky-800 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white dark:aria-selected:bg-white/10 dark:aria-selected:text-white">{{ $title }}</button>
                    @endforeach
                </div>

                <div class="min-w-0 p-4 sm:p-6">
                    @foreach ($topics as $key => [$title, $summary, $variants, $note])
                            <div role="tabpanel" id="practice-panel-{{ $key }}" aria-labelledby="practice-tab-{{ $key }}" data-practice-panel="{{ $key }}" @unless ($key === $first) hidden @endunless>
                                <div class="mb-5">
                                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $summary }}</h3>
                                @foreach ((array) $note as $language => $text)
                                    <p @if (is_array($note)) data-practice-code="{{ $language }}" @unless ($loop->first) hidden @endunless @endif class="mt-1 text-sm text-slate-500 dark:text-slate-400">{!! $prose($text) !!}</p>
                                @endforeach
                                @if (! isset($variants['*']) && ! isset($variants['map']))
                                    <div role="group" aria-label="{{ $title }} language" class="mt-4 flex w-fit gap-1 rounded-lg bg-slate-100 p-1 dark:bg-white/5">
                                        @foreach (array_intersect_key($languages, $variants) as $language => $label)
                                            <button type="button" data-practice-language="{{ $language }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="rounded-md px-3 py-1 text-sm font-medium text-slate-600 hover:text-slate-950 aria-pressed:bg-white aria-pressed:text-slate-950 aria-pressed:shadow-sm dark:text-slate-400 dark:hover:text-white dark:aria-pressed:bg-slate-700 dark:aria-pressed:text-white">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                @endif
                                </div>
                                @if (isset($variants['map']))
                                    @php([$mapTree, $mapParts] = $variants['map'])
                                    <div class="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)] lg:items-start">
                                        <div class="overflow-hidden rounded-lg bg-[#0c1526] ring-1 ring-slate-400/20">
                                            <x-folder-tree :text="$mapTree" />
                                        </div>
                                        <dl class="divide-y divide-slate-800/10 dark:divide-white/10">
                                            @foreach ($mapParts as [$part, $text, $target])
                                                <div class="py-2.5 first:pt-0 last:pb-0">
                                                    <dt class="flex items-baseline justify-between gap-3 text-sm font-medium text-slate-900 dark:text-white">
                                                        {{ $part }}
                                                        <button type="button" data-practice-goto="{{ $target }}" class="shrink-0 text-xs font-normal text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-800 hover:decoration-slate-600 dark:text-slate-400 dark:decoration-slate-600 dark:hover:text-slate-200">{{ $topics[$target][0] }}</button>
                                                    </dt>
                                                    <dd class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{!! $prose($text) !!}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                @else
                                @foreach ($variants as $language => [$before, $after])
                                    <div data-practice-code="{{ $language }}" @unless ($loop->first) hidden @endunless class="grid gap-4 lg:grid-cols-2">
                                        @foreach (['Without Framework' => $before, 'With Framework' => $after] as $side => $blocks)
                                            <div class="min-w-0">
                                                <p @class(['text-xs font-medium', 'text-slate-500 dark:text-slate-400' => $loop->first, 'text-sky-700 dark:text-sky-400' => ! $loop->first])>{{ $side }}</p>
                                                <div class="mt-2 space-y-2">
                                                    @foreach ($blocks as $block)
                                                        @php([$file, $code] = $block)
                                                        <div @class(['overflow-hidden rounded-lg bg-[#0c1526] ring-1 ring-slate-400/20', 'opacity-80' => $loop->parent->first])>
                                                            @if ($file)
                                                                <p class="border-b border-white/10 px-3 py-1.5 font-mono text-[0.7rem] text-slate-400">{{ $file }}</p>
                                                            @endif
                                                            @if (($block[2] ?? null) === 'tree')
                                                                <x-folder-tree :text="$code" />
                                                            @else
                                                                <pre class="overflow-x-auto px-3 py-2.5 font-mono text-xs leading-[1.6] text-slate-100" style="color-scheme: dark"><code>{!! ($block[2] ?? null) === 'output' ? e($code) : \App\Support\ExampleCode::highlight($code, false) !!}</code></pre>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                                @endif
                            </div>
                    @endforeach
                </div>
            </div>
        </x-landing-frame>
    </div>
    @if ($docLinks->isNotEmpty())
        <p class="mt-6 text-center text-sm leading-relaxed text-balance text-slate-600 dark:text-slate-400">Read the documentation for {!! $docList !!} to see everything Framework can do.</p>
    @endif
</section>
