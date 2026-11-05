@php
    $steps = [
        [
            'id' => 'setup',
            'title' => 'Start your project',
            'body' => 'Build a project from trusted blueprints. Catalog your data, manage package dependencies, set safety guidelines, configure Quarto or Jupyter notebooks, and set up database connections in one place. Signed in to Framework Cloud, a new project starts from your saved blueprint.',
            'guide' => 'new-project',
            'panels' => [
                'r' => ['label' => 'R', 'code' => "framework::new_project(\"penguin-study\")\n\n# Signed in to Framework Cloud, the project starts\n# from your saved blueprint: its folders, package\n# list, and notebook settings. Open the files to see them.\n\n# Or use a project created in Cloud\nframework::new_project(key = \"<project token>\")"],
                'py' => ['label' => 'Python', 'code' => "framework.new_project(\"penguin-study\")\n\n# Signed in to Framework Cloud, the project starts\n# from your saved blueprint: its folders, package\n# list, and notebook settings. Open the files to see them.\n\n# Or use a project created in Cloud\nframework.new_project(key=\"<project token>\")"],
                'stata' => ['label' => 'Stata', 'code' => "// In a terminal, create the project\nfw new penguin-study --language stata\n\n// Then, in Stata, set the project's paths\nfw_project"],
                'cli' => ['label' => 'CLI', 'code' => "fw new penguin-study\n\n# Or create the repository on GitHub\nfw new penguin-study --github username/penguin-study"],
            ],
        ],
        [
            'id' => 'analysis',
            'title' => 'Conduct your analysis',
            'body' => 'Load your project settings and helper functions, read data by its catalog name, and check whether an input has changed.',
            'guide' => 'scaffold',
            'panels' => [
                'r' => ['label' => 'R', 'code' => "library(framework)\n\n# Load all files in functions/.\n# Apply the configured ggplot theme.\nscaffold()\n\n# Read the input and record its hash\npenguins <- data_read(\"inputs.raw.penguins\")\npenguins <- drop_incomplete(penguins)\nmodel <- lm(body_mass_g ~ flipper_length_mm + species, penguins)\nplot <- ggplot(penguins, aes(flipper_length_mm, body_mass_g)) +\n  geom_point()\n\n# Verify that the input is unchanged\ndata_verify(\"inputs.raw.penguins\")"],
                'py' => ['label' => 'Python', 'code' => "import framework as fw\nimport statsmodels.formula.api as smf\nimport matplotlib.pyplot as plt\n\nfw.scaffold()\nfrom data_prep import drop_incomplete\n\n# Read the input and record its hash\npenguins = fw.data_read(\"inputs.raw.penguins\")\npenguins = drop_incomplete(penguins)\nmodel = smf.ols(\n    \"body_mass_g ~ flipper_length_mm + species\",\n    penguins\n).fit()\nfig, ax = plt.subplots()\nax.scatter(penguins[\"flipper_length_mm\"], penguins[\"body_mass_g\"])\n\n# Verify that the input is unchanged\nfw.data_verify(\"inputs.raw.penguins\")"],
                'stata' => ['label' => 'Stata', 'code' => "// Set path globals such as \$fw_outputs_figures\nfw_project\n\n// Load the input and check that it is unchanged\nfw_use inputs.raw.penguins, clear\n\nregress body_mass_g flipper_length_mm i.species\n\nscatter body_mass_g flipper_length_mm\ngraph export \"\$fw_outputs_figures/figure.svg\", replace"],
            ],
        ],
        [
            'id' => 'publish',
            'title' => 'Publish your work',
            'body' => 'Publish a model from your script and share its link. Publish again with the same slug and collaborators get the latest version at that link.',
            'guide' => 'publishing',
            'panels' => [
                'r' => ['label' => 'R', 'code' => "# Publish a figure\npublish(plot, slug = \"flipper-and-mass\", to = \"cloud\")\n\n# Publish a model summary. Publishing again\n# adds a version at the same link.\npublish(model, slug = \"penguin-body-mass\", to = \"cloud\")\n\n# Publish a rendered document\npublish(\"outputs/report.html\", to = \"cloud\")"],
                'py' => ['label' => 'Python', 'code' => "# Publish a figure\nfw.publish(fig, slug=\"flipper-and-mass\", to=\"cloud\")\n\n# Publish a model summary. Publishing again\n# adds a version at the same link.\nfw.publish(model, slug=\"penguin-body-mass\", to=\"cloud\")\n\n# Publish a rendered document\nfw.publish(\"outputs/report.html\", to=\"cloud\")"],
                'stata' => ['label' => 'Stata', 'code' => "// Publish a figure\nfw_publish \"\$fw_outputs_figures/figure.svg\", ///\n    slug(flipper-and-mass) to(cloud)\n\n// Publish the estimation results\nfw_publish, estimates slug(study-model) to(cloud)\n\n// Publish a rendered document\nfw_publish \"outputs/report.html\", to(cloud)"],
                'cli' => ['label' => 'CLI', 'code' => "fw publish outputs/figure.svg --to cloud\n\nfw publish outputs/model.json --to cloud\n\nfw publish outputs/report.html --to cloud\n\n# Use a named folder or storage destination\nfw publish outputs/report.html --to lab"],
            ],
        ],
    ];
    // Stata appears once the Stata package is released (FW_STATA_PACKAGE)
    if (! config('framework.stata_package')) {
        foreach ($steps as &$unreleased) {
            unset($unreleased['panels']['stata']);
        }
        unset($unreleased);
    }
@endphp
<section id="workflow" class="scroll-mt-6 border-t border-slate-800/10 py-14 sm:py-20 dark:border-white/10">
    <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-3xl font-semibold tracking-tight text-balance">Framework keeps your project structured from start to finish</h2>
        <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-pretty text-slate-600 dark:text-slate-400">Start the project, run the analysis, and publish the results with one folder layout, one data catalog, and one set of settings.</p>
    </div>
    <div class="mt-12 space-y-14">
        @foreach ($steps as $step)
            @php
                $panels = $step['panels'];
                $files = [];
                $companions = [];
                foreach ($panels as $language => &$panel) {
                    $stata = $language === 'stata';
                    $extension = match ($language) { 'py' => 'py', 'stata' => 'do', default => 'R' };
                    $scripts = $stata ? 'do' : 'scripts';
                    $panel['path'] = $language === 'cli' || ($stata && $step['id'] === 'setup') ? 'Terminal' : match ($step['id']) {
                        'setup' => $language === 'py' ? 'Python console' : 'R console',
                        'publish' => "$scripts/publish.$extension",
                        default => "$scripts/analysis.$extension",
                    };
                    $files[$language] = \App\Support\LandingProject::files($language === 'cli' ? 'r' : $language);
                    if ($step['id'] === 'setup') {
                        $files[$language]['.gitignore'] = \App\Support\GitignoreTemplate::for('project');
                    }
                    foreach ($steps as $sourceStep) {
                        if (in_array($sourceStep['id'], ['analysis', 'publish']) && isset($sourceStep['panels'][$language])) {
                            $name = $sourceStep['id'] === 'analysis' ? 'analysis' : 'publish';
                            $files[$language]["$scripts/$name.$extension"] = $sourceStep['panels'][$language]['code'];
                        }
                    }
                    $illustration = $stata ? match ($step['id']) {
                        'setup' => ['.gitignore', 'settings.yml', 'settings.do', 'settings/data.yml', 'master.do', 'AGENTS.md'],
                        'analysis' => ['settings/data.yml', 'master.do', 'do/publish.do'],
                        'publish' => ['outputs/figure.svg', 'outputs/model.json', 'outputs/report.html', 'do/analysis.do'],
                        default => [],
                    } : match ($step['id']) {
                        'setup' => ['.gitignore', 'settings.yml', $language === 'py' ? 'pyproject.toml' : 'settings/packages.yml', 'settings/data.yml', 'settings/connections.yml', 'settings/quarto.yml', '.claude/skills/framework-data/SKILL.md'],
                        'analysis' => ["functions/data_prep.$extension", $language === 'py' ? 'pyproject.toml' : 'settings/packages.yml', 'settings.yml'],
                        'publish' => ['outputs/figure.svg', 'outputs/model.json', 'outputs/report.html'],
                        default => [],
                    };
                    $files[$language] = array_intersect_key($files[$language], array_flip($illustration));
                    if ($step['id'] === 'setup' && ! in_array($language, ['cli', 'stata'], true)) {
                        $python = $language === 'py';
                        $companions[$language] = [
                            ($python ? 'pyproject.toml' : 'settings/packages.yml') => ['path' => "scripts/install-packages.$extension", 'code' => $python
                                ? "import framework\n\n# Install dependencies declared in pyproject.toml.\n# Uses uv sync when uv is available.\nframework.packages_install()"
                                : "# Install packages declared in settings/packages.yml.\n# Session setup and attachment happen later in scaffold().\nframework::packages_install()"],
                            'settings.yml' => ['path' => "scripts/analysis.$extension", 'code' => $python
                                ? "import framework\n\n# Apply the configured seed and prepare helper imports.\nframework.scaffold()"
                                : "# Apply the configured seed and ggplot theme.\nframework::scaffold()"],
                            'settings/data.yml' => ['path' => "scripts/data.$extension", 'code' => $python
                                ? "# Resolve the catalog name and record the file hash.\npenguins = fw.data_read(\"inputs.raw.penguins\")"
                                : "# Resolve the catalog name and record the file hash.\npenguins <- data_read(\"inputs.raw.penguins\")"],
                            'settings/connections.yml' => ['path' => "scripts/data.$extension", 'code' => $python
                                ? "measurements = fw.db_query(\n    \"SELECT species, island FROM field_measurements\",\n    \"study_db\"\n)"
                                : "measurements <- db_query(\n  \"SELECT species, island FROM field_measurements\",\n  \"study_db\"\n)"],
                        ];
                    }
                }
                unset($panel);
            @endphp
            <div>
            <div class="grid items-start gap-6 lg:grid-cols-[0.65fr_1.35fr] lg:gap-12">
                <div>
                    <p class="font-mono text-sm text-slate-400">0{{ $loop->iteration }}</p>
                    <h3 class="mt-3 text-2xl font-semibold tracking-tight">{{ $step['title'] }}</h3>
                    <p class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ $step['body'] }}</p>
                    <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <a class="text-slate-700 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-300" href="{{ \App\Support\Docs\Products::publicUrl('r', '/guides/'.$step['guide']) }}">R &rarr;</a>
                        <a class="text-slate-700 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-300" href="{{ \App\Support\Docs\Products::publicUrl('py', '/guides/'.$step['guide']) }}">Python &rarr;</a>
                        @if (config('framework.stata_package'))<a class="text-slate-700 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-600 dark:text-slate-300" href="{{ \App\Support\Docs\Products::publicUrl('stata', '/guides/'.['setup' => 'projects', 'analysis' => 'data', 'publish' => 'publishing'][$step['id']]) }}">Stata &rarr;</a>@endif
                    </div>
                </div>
                <div class="min-w-0 rounded-xl shadow-panel-deep dark:shadow-panel-deep-dark">
                @if ($step['id'] === 'publish')
                    <x-landing-published />
                @else
                    <x-landing-editor :panels="$panels" :files="$files" :companions="$companions" :helper="$step['id'] === 'analysis'" :arrow="in_array($step['id'], ['setup', 'publish'])" :priority="$step['id'] === 'publish' ? ['outputs/', 'scripts/', 'settings/'] : ['settings.yml', 'settings/', 'inputs/', 'scripts/', 'functions/', 'notebooks/', 'outputs/', 'AGENTS.md', '.gitignore']" :title="$step['title']" />
                @endif
                </div>
            </div>
            </div>
        @endforeach
    </div>
</section>
