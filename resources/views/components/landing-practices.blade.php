@php
    // Before and after: the setup code a project repeats by hand, and the Framework call that
    // replaces it, in each language Framework supports for the topic. Each side is a list of
    // [file, code] blocks; a null file shows no caption. Topics without a language key apply
    // to every language and show no language tabs.
    $groups = [
        'Project setup' => [
            'structure' => ['Curated project structures', 'Curated project structures', [
                'r' => [
                    [[null, "dir.create(\"data/raw\", recursive = TRUE)\ndir.create(\"data/clean\")\ndir.create(\"output/figures\", recursive = TRUE)\nfile.copy(\"../last-project/.gitignore\", \".\")\nusethis::use_git()"]],
                    [[null, "framework::new_project(\"penguin-study\")"]],
                ],
                'py' => [
                    [[null, "from pathlib import Path\n\nfor folder in [\"data/raw\", \"output/figures\"]:\n    Path(folder).mkdir(parents=True, exist_ok=True)\n# Copy .gitignore and settings from the last project"]],
                    [[null, "import framework\n\nframework.new_project(\"penguin-study\")"]],
                ],
                'stata' => [
                    [[null, "mkdir \"C:/Users/me/penguin-study\"\nmkdir \"C:/Users/me/penguin-study/data\"\nmkdir \"C:/Users/me/penguin-study/output\"\n// Write master.do and a .gitignore by hand"]],
                    [['Terminal', "fw new penguin-study --lang stata"]],
                ],
            ], 'Our project structures scale as your project grows, keep data safe, and make sense to collaborators at a glance. Prefer your own structure? Framework supports that; you can even share them with Framework Cloud.'],
            'packages' => ['Sane package management', 'Keep a dictionary of your packages for one-step installation and loading.', [
                'r' => [
                    [[null, "pkgs <- c(\"dplyr\", \"ggplot2\", \"readr\")\nnew <- setdiff(pkgs, rownames(installed.packages()))\ninstall.packages(new)\nlibrary(dplyr)\nlibrary(ggplot2)"]],
                    [['settings/packages.yml', "packages:\n  default_packages:\n    - name: dplyr\n      auto_attach: true\n    - name: ggplot2\n      auto_attach: true\n    - name: readr\n      auto_attach: false"], [null, "scaffold()  # installs all three, attaches two\npenguins <- readr::read_csv(\"data/raw/penguins.csv\")"]],
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
                'r' => 'Declare your packages in a settings file. Framework will make sure they\'re installed and even load them for you in `scaffold()` if you want. Collaborators and AI agents alike can easily see what your project needs.',
                'py' => 'Declare your packages in pyproject.toml. Framework will make sure they\'re installed. Collaborators and AI agents alike can easily see what your project needs.',
                'stata' => 'Declare your packages in a settings file. Framework will make sure they\'re installed, and `fw_scaffold` installs any that are missing. Collaborators and AI agents alike can easily see what your project needs.',
            ]],
        ],
        'Analysis' => [
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
                'r' => 'The `scaffold()` function sets up your environment, ensures packages are available, and gives you access to all your functions. No more juggling `source()` and `library()` calls in a dozen places.',
                'py' => 'The `fw.scaffold()` function sets up your environment and gives you access to all your functions. No more `os.chdir()` and `sys.path` edits at the top of every notebook.',
                'stata' => 'The `fw_scaffold` command sets up your environment, installs missing packages, and gives you access to all your programs. No more hard-coded paths at the top of every do-file.',
            ]],
            'outputs' => ['Organized outputs', 'Catalog and publish your project\'s deliverables.', [
                'r' => [
                    [[null, "dir.create(\"output/figures\", recursive = TRUE)\nggsave(\"output/figures/flipper-mass.png\", plot,\n       width = 8, height = 6)"]],
                    [[null, "save_figure(plot, \"flipper-mass\")"]],
                ],
                'py' => [
                    [[null, "os.makedirs(\"output/figures\", exist_ok=True)\nfig.savefig(\"output/figures/flipper-mass.png\")"]],
                    [[null, "fw.save_figure(fig, \"flipper-mass\")"]],
                ],
                'stata' => [
                    [[null, "capture mkdir \"\$figures\"\ngraph export \"\$figures/flipper-mass.png\", replace"]],
                    [[null, "fw_graph flipper-mass"]],
                ],
            ], 'Use our helper functions to keep track of your project\'s outputs. With Framework Cloud, you can easily publish and share plots, tables, models, notebooks, and more.'],
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
            ], 'Framework packages integrate with Framework Cloud, so you can share versioned artifacts with colleagues and the public. We\'ll make the presentation beautiful.'],
            'caching' => ['Cache slow operations', 'Run expensive operations once.', [
                'r' => [
                    [[null, "if (file.exists(\"cache/fit.rds\")) {\n  fit <- readRDS(\"cache/fit.rds\")\n} else {\n  fit <- run_bootstrap(penguins)\n  saveRDS(fit, \"cache/fit.rds\")\n}"]],
                    [[null, "fit <- cache_remember(\n  \"fit\",\n  run_bootstrap(penguins),\n  expire_after = \"1 day\"\n)"]],
                ],
                'py' => [
                    [[null, "import pickle\n\npath = \"cache/fit.pkl\"\nif os.path.exists(path):\n    fit = pickle.load(open(path, \"rb\"))\nelse:\n    fit = run_bootstrap(penguins)\n    pickle.dump(fit, open(path, \"wb\"))"]],
                    [[null, "fit = fw.cache_remember(\n    \"fit\",\n    lambda: run_bootstrap(penguins),\n    expire_after=\"1 day\",\n)"]],
                ],
            ], 'Helpers run a slow operation once and reuse the result until it expires.'],
            'databases' => ['Easy database connections', 'Connect to local or remote databases by name.', [
                'r' => [
                    [[null, "con <- DBI::dbConnect(RPostgres::Postgres(),\n  host = \"db.example.org\", user = \"analyst\",\n  password = \"hunter2\")\nvisits <- DBI::dbGetQuery(con,\n  \"SELECT * FROM visits\")\nDBI::dbDisconnect(con)"]],
                    [['settings/connections.yml', "connections:\n  study_db:\n    driver: postgres\n    host: db.example.org\n    user: analyst\n    password: env(\"DB_PASSWORD\")"], [null, "visits <- db_query(\"SELECT * FROM visits\",\n                   \"study_db\")"]],
                ],
                'py' => [
                    [[null, "conn = psycopg.connect(host=\"db.example.org\",\n    user=\"analyst\", password=\"hunter2\")\nvisits = pd.read_sql(\"SELECT * FROM visits\", conn)\nconn.close()"]],
                    [['settings/connections.yml', "connections:\n  study_db:\n    driver: postgres\n    host: db.example.org\n    user: analyst\n    password: env(\"DB_PASSWORD\")"], [null, "visits = fw.db_query(\"SELECT * FROM visits\",\n                     \"study_db\")"]],
                ],
            ], 'Declare your database connections in a settings file. Use `env()` to point to your secrets stored in a .gitignored .env file.'],
        ],
        'Data safety' => [
            'integrity' => ['Data change detection', 'Data integrity is checked on every read.', [
                'r' => [
                    [[null, "sums <- read.csv(\"data/checksums.csv\")\npath <- \"data/raw/penguins.csv\"\nexpected <- sums\$md5[sums\$file == path]\nif (tools::md5sum(path) != expected) {\n  stop(\"penguins.csv changed\")\n}\npenguins <- readr::read_csv(path)"]],
                    [[null, "penguins <- data_read(\"inputs.raw.penguins\")"]],
                ],
                'py' => [
                    [[null, "import hashlib, json\n\npath = \"data/raw/penguins.csv\"\nwith open(\"data/checksums.json\") as f:\n    expected = json.load(f)[path]\nwith open(path, \"rb\") as f:\n    md5 = hashlib.md5(f.read()).hexdigest()\nif md5 != expected:\n    raise ValueError(\"penguins.csv changed\")\npenguins = pd.read_csv(path)"]],
                    [[null, "penguins = fw.data_read(\"inputs.raw.penguins\")"]],
                ],
                'stata' => [
                    [[null, "checksum \"\$raw/penguins.csv\"\nlocal now = r(checksum)\nfile open fh using \"data/checksums.txt\", read\nfile read fh known\nfile close fh\nassert `now' == `known'\nimport delimited \"\$raw/penguins.csv\", clear"]],
                    [[null, "fw_use inputs.raw.penguins, clear"]],
                ],
            ], [
                'r' => 'Use `data_read()` to automatically hash your data. Your scripts will warn you or fail if data is changed without your knowledge.',
                'py' => 'Use `fw.data_read()` to automatically hash your data. Your scripts will warn you or fail if data is changed without your knowledge.',
                'stata' => 'Use `fw_use` to automatically hash your data. Your do-files will warn you or fail if data is changed without your knowledge.',
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
    // One list, most persuasive first. The AI agents section covers agents.
    $order = ['session', 'integrity', 'git', 'structure', 'outputs', 'packages', 'databases', 'caching', 'publish'];
    $topics = array_replace(array_flip($order), array_merge(...array_values($groups)));
    $first = $order[0];
@endphp
<section id="practices" data-practices class="scroll-mt-6 py-14 sm:py-20">
    <div class="mx-auto max-w-3xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight text-balance">Best practices made easy</h2>
        <p class="mt-4 leading-relaxed text-balance text-slate-600 dark:text-slate-400">Framework's R, Python, and Stata packages make it easy to set up projects with a sensible structure so they start organized. Data safeguards need no extra code. Use Framework Cloud to make starting projects, keeping them organized, and sharing results even easier.</p>
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
                                @if (! isset($variants['*']))
                                    <div role="group" aria-label="{{ $title }} language" class="mt-4 flex w-fit gap-1 rounded-lg bg-slate-100 p-1 dark:bg-white/5">
                                        @foreach (array_intersect_key($languages, $variants) as $language => $label)
                                            <button type="button" data-practice-language="{{ $language }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="rounded-md px-3 py-1 text-sm font-medium text-slate-600 hover:text-slate-950 aria-pressed:bg-white aria-pressed:text-slate-950 aria-pressed:shadow-sm dark:text-slate-400 dark:hover:text-white dark:aria-pressed:bg-slate-700 dark:aria-pressed:text-white">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                @endif
                                </div>
                                @foreach ($variants as $language => [$before, $after])
                                    <div data-practice-code="{{ $language }}" @unless ($loop->first) hidden @endunless class="grid gap-4 lg:grid-cols-2">
                                        @foreach (['Without Framework' => $before, 'With Framework' => $after] as $side => $blocks)
                                            <div class="min-w-0">
                                                <p @class(['text-xs font-medium', 'text-slate-500 dark:text-slate-400' => $loop->first, 'text-sky-700 dark:text-sky-400' => ! $loop->first])>{{ $side }}</p>
                                                <div class="mt-2 space-y-2">
                                                    @foreach ($blocks as [$file, $code])
                                                        <div @class(['overflow-hidden rounded-lg bg-[#0c1526] ring-1 ring-slate-400/20', 'opacity-80' => $loop->parent->first])>
                                                            @if ($file)
                                                                <p class="border-b border-white/10 px-3 py-1.5 font-mono text-[0.7rem] text-slate-400">{{ $file }}</p>
                                                            @endif
                                                            <pre class="overflow-x-auto px-3 py-2.5 font-mono text-xs leading-[1.6] text-slate-100" style="color-scheme: dark"><code>{!! \App\Support\ExampleCode::highlight($code, false) !!}</code></pre>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                    @endforeach
                </div>
            </div>
        </x-landing-frame>
    </div>
</section>
