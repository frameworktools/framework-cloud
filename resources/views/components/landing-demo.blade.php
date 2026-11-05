@php
    $examples = [
        ['title' => 'Project setup', 'description' => 'Folders, settings, and agent instructions from one project definition.', 'r' => "library(framework)\n\nnew_project(\"penguin-study\")\n\n# Or use a project from Framework Cloud\nnew_project(key = \"<project token>\")", 'python' => "import framework as fw\n\nfw.new_project(\"penguin-study\")\n\n# Or use a project from Framework Cloud\nfw.new_project(key=\"<project token>\")", 'cli' => 'fw new penguin-study', 'stata' => "// Create the project with the Framework CLI:\n//   fw new penguin-study --lang stata\n\n// Then, in Stata, from the project folder\nfw_project\ndisplay \"\$fw_inputs_raw\""],
        ['title' => 'Read and save cataloged data', 'description' => 'Project packages, helper functions, and settings at the start of each analysis.', 'r' => "library(framework)\nscaffold()\n\n# Read a cataloged dataset; its hash is recorded\npenguins <- data_read(\"inputs.raw.penguins\")\n\n# Keep complete records for analysis\nanalysis <- penguins[complete.cases(penguins), ]\n\n# Save it to the catalog under a new name\ndata_save(analysis, \"inputs.final.analysis\")\ndata_info(\"inputs.final.analysis\")", 'python' => "import framework as fw\nfw.scaffold()\n\n# Read a cataloged dataset; its hash is recorded\npenguins = fw.data_read(\"inputs.raw.penguins\")\n\n# Keep complete records for analysis\nanalysis = penguins.dropna()\n\n# Save it to the catalog under a new name\nfw.data_save(analysis, \"inputs.final.analysis\")\nfw.data_info(\"inputs.final.analysis\")", 'cli' => 'fw project bootstrap', 'stata' => "fw_project\n\n// Read a cataloged dataset; its checksum is checked\nfw_use inputs.raw.penguins, clear\n\n// Keep complete records for analysis\ndestring body_mass_g flipper_length_mm, replace force\ndrop if missing(body_mass_g, flipper_length_mm)\n\n// Save it under a new name; fw_save records\n// its SHA-256 checksum and datasignature\nfw_save inputs.final.analysis"],
        ['title' => 'Publish a searchable results table', 'description' => 'Interactive tables and versioned outputs on Cloud, Pages, a folder, or object storage.', 'r' => "library(framework)\nscaffold()\n\nresults <- data.frame(\n  group = c(\"Control\", \"Treatment\"),\n  estimate = c(1.24, 1.87),\n  std_error = c(0.11, 0.13)\n)\n\n# Publish a searchable table with its own link\npublish(results, slug = \"study-results\",\n        title = \"Study results\",\n        description = \"Estimates by group\")", 'python' => "import framework as fw\nimport pandas as pd\nfw.scaffold()\n\nresults = pd.DataFrame({\n    \"group\": [\"Control\", \"Treatment\"],\n    \"estimate\": [1.24, 1.87],\n    \"std_error\": [0.11, 0.13],\n})\n\n# Publish a searchable table with its own link\nfw.publish(results, slug=\"study-results\",\n           title=\"Study results\")", 'cli' => 'fw publish results.csv --to cloud', 'stata' => "fw_project\n\nclear\ninput str9 group estimate std_error\n\"Control\"   1.24 0.11\n\"Treatment\" 1.87 0.13\nend\n\n// Publish a searchable table with its own link\nlocal file \"\$fw_outputs_tables/study-results.csv\"\nexport delimited \"`file'\", replace\nfw_publish \"`file'\", slug(study-results) ///\n    title(\"Study results\")"],
        ['title' => 'Query a named database connection', 'description' => 'Named connections and query helpers. Credentials stay in environment variables.', 'r' => "library(framework)\nscaffold()\n\n# study_db is set in settings/connections.yml\nmeasurements <- db_query(\n  \"SELECT species, island, body_mass_g\n     FROM field_measurements\n    WHERE year >= 2008\",\n  \"study_db\"\n)\n\n# Save the result to the catalog\ndata_save(measurements, \"inputs.raw.measurements\")", 'python' => "import framework as fw\nfw.scaffold()\n\n# study_db is set in settings/connections.yml\nmeasurements = fw.db_query(\n    \"\"\"SELECT species, island, body_mass_g\n         FROM field_measurements\n        WHERE year >= 2008\"\"\",\n    \"study_db\",\n)\n\n# Save the result to the catalog\nfw.data_save(measurements, \"inputs.raw.measurements\")", 'cli' => 'fw project status', 'stata' => "// The Stata package does not query databases.\n// Run the query in R or Python and save it\n// with data_save(), or use Stata's odbc.\n\nfw_project\n\n// Load the saved result by name; fw_use checks\n// its checksum against the last record\nfw_use inputs.raw.measurements, clear\n\nsummarize body_mass_g\ntabulate species island"],
        ['title' => 'Log inputs and verify file integrity', 'description' => 'File fingerprints, read records, and integrity checks alongside your analysis.', 'r' => "library(framework)\nscaffold()\n\n# Add a file to the data catalog and lock it\ndata_add(\"inputs/raw/penguins.csv\", locked = TRUE)\n\n# Reading records the file's hash\npenguins <- data_read(\"inputs.raw.penguins\")\n\n# Later, check that the file has not changed\ndata_verify(\"inputs.raw.penguins\")\ndata_info(\"inputs.raw.penguins\")", 'python' => "import framework as fw\nfw.scaffold()\n\n# Add a file to the data catalog and lock it\nfw.data_add(\"inputs/raw/penguins.csv\", locked=True)\n\n# Reading records the file's hash\npenguins = fw.data_read(\"inputs.raw.penguins\")\n\n# Later, check that the file has not changed\nfw.data_verify(\"inputs.raw.penguins\")\nfw.data_info(\"inputs.raw.penguins\")", 'cli' => 'fw project status', 'stata' => "fw_project\n\n// fw_use computes the file's SHA-256 checksum\n// and compares it with the last record\nfw_use inputs.raw.penguins, clear\n\n// After loading, it compares Stata's datasignature\ndescribe, short\n\n// fw_save records new checksums for the\n// analysis dataset\nfw_save inputs.final.analysis"],
    ];
    $highlight = fn (string $code) => \App\Support\ExampleCode::highlight($code);
    // Stata joins the tabs once its docs are listed, like the hero text.
    $languages = ['r' => 'R', 'python' => 'Python'] + (isset(\App\Support\Docs\Products::listed()['stata']) ? ['stata' => 'Stata'] : []);
    // The first example covers the daily workflow; the carousel fills in each feature.
    $examples[0] = [
        'title' => 'Load your project and publish a model',
        'description' => 'Project packages and functions load through scaffold().',
        'r' => "library(framework)\nscaffold()\n\n# Read cataloged data\npenguins <- data_read(\"inputs.raw.penguins\")\n\n# Fit a model\nmodel <- lm(body_mass_g ~ flipper_length_mm + species,\n            data = penguins)\n\n# Publish it\npublish(model, slug = \"penguin-body-mass\")",
        'python' => "import framework as fw\nimport statsmodels.formula.api as smf\nfw.scaffold()\n\n# Read cataloged data\npenguins = fw.data_read(\"inputs.raw.penguins\")\n\n# Fit a model\nformula = \"body_mass_g ~ flipper_length_mm + species\"\nmodel = smf.ols(formula, data=penguins).fit()\n\n# Publish it\nfw.publish(model, slug=\"penguin-body-mass\")",
        'stata' => "fw_project\n\n// Read cataloged data\nfw_use inputs.raw.penguins, clear\ndestring body_mass_g flipper_length_mm, replace force\nencode species, generate(species_id)\n\n// Fit a model\nregress body_mass_g flipper_length_mm i.species_id\n\n// Publish it\nfw_publish, estimates slug(penguin-body-mass)",
        'cli' => 'fw project bootstrap',
    ];
@endphp
<div data-landing-demo role="region" aria-roledescription="carousel" aria-label="Framework examples" class="min-w-0 overflow-hidden rounded-xl bg-[#0c1526] text-slate-100 ring-1 ring-slate-400/20">
    <div class="border-b border-white/10 px-6 pt-5 pb-4"><strong data-demo-title class="text-sm font-semibold">Load your project and publish a model</strong></div>
    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-6 py-3">
        <div class="flex gap-1" aria-label="Example view">
            <button type="button" data-demo-view="code" aria-pressed="true" class="rounded-md px-2.5 py-1.5 text-xs text-slate-400 aria-pressed:bg-white/10 aria-pressed:text-white">Code</button>
            <button type="button" data-demo-view="result" aria-pressed="false" class="rounded-md px-2.5 py-1.5 text-xs text-slate-400 aria-pressed:bg-white/10 aria-pressed:text-white">Project files</button>
        </div>
        <div role="tablist" aria-label="Example language" class="flex gap-1 rounded-lg bg-white/5 p-0.5">
            @foreach ($languages as $language => $label)
                <button data-demo-language="{{ $language }}" id="demo-language-{{ $language }}" role="tab" aria-controls="demo-code" aria-selected="{{ $language === 'r' ? 'true' : 'false' }}" tabindex="{{ $language === 'r' ? '0' : '-1' }}" type="button" class="rounded-md px-3 py-1 text-xs font-medium text-slate-400 hover:text-white aria-selected:bg-slate-700 aria-selected:text-white">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div data-demo-view-panel="code" id="demo-code" role="tabpanel" aria-labelledby="demo-language-r" class="grid">
        @foreach ($examples as $example)
            <section data-demo-slide="{{ $loop->index }}" aria-roledescription="slide" aria-label="{{ $example['title'] }}" @unless ($loop->first) hidden @endunless class="min-w-0 [grid-area:1/1]">
                @foreach (array_keys($languages) as $language)
                    <pre data-demo-code="{{ $language }}" @unless ($language === 'r') hidden @endunless class="h-[320px] overflow-auto px-6 py-6 font-mono text-xs leading-[1.6] sm:text-[13px] lg:text-xs xl:text-[13px]"><code>{!! $highlight($example[$language]) !!}</code></pre>
                @endforeach
            </section>
        @endforeach
    </div>
    <div data-demo-view-panel="result" hidden class="h-[320px] overflow-y-auto">
        @php
            $projectKeys = ['r' => 'R', 'py' => 'Python'] + (isset($languages['stata']) ? ['stata' => 'Stata'] : []);
            $projectFiles = [];
            $projectPanels = [];
            foreach ($projectKeys as $key => $label) {
                $files = \App\Support\LandingProject::files($key);
                $projectPanels[$key] = ['label' => $label, 'path' => 'AGENTS.md', 'code' => $files['AGENTS.md']];
                $shown = $key === 'stata' ? ['settings.yml', 'settings/data.yml', 'settings.do', 'master.do'] : ['settings.yml', 'settings/data.yml', 'notebooks/report.qmd', 'functions/data_prep.'.($key === 'py' ? 'py' : 'R')];
                $projectFiles[$key] = array_intersect_key($files, array_flip($shown));
            }
        @endphp
        <x-landing-editor :panels="$projectPanels" :files="$projectFiles" :embedded="true" title="Hero project" />
    </div>
    <div class="flex items-center justify-between gap-4 border-t border-white/10 px-6 py-3.5">
        <span class="text-xs text-slate-500">{{ isset($languages['stata']) ? 'R, Python, and Stata examples' : 'R and Python examples' }}</span>
        <div class="flex shrink-0 items-center gap-2">
            <button type="button" data-demo-previous aria-label="Previous example" class="rounded-md p-1 text-slate-500 hover:bg-white/10 hover:text-white"><x-heroicon-m-chevron-left class="size-4" /></button>
            @foreach ($examples as $example)
                <button type="button" data-demo-select="{{ $loop->index }}" aria-label="{{ $example['title'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="h-1.5 w-1.5 rounded-full bg-slate-600 transition-all hover:bg-slate-400 aria-pressed:w-5 aria-pressed:bg-sky-400"></button>
            @endforeach
            <button type="button" data-demo-next aria-label="Next example" class="rounded-md p-1 text-slate-500 hover:bg-white/10 hover:text-white"><x-heroicon-m-chevron-right class="size-4" /></button>
            <button type="button" data-demo-pause aria-label="Pause examples" aria-pressed="false" class="group ml-1 rounded-md p-1 text-slate-500 hover:bg-white/10 hover:text-white"><x-heroicon-m-pause class="size-4 group-aria-pressed:hidden" /><x-heroicon-m-play class="hidden size-4 group-aria-pressed:block" /></button>
        </div>
    </div>
    <p class="sr-only" data-demo-status aria-live="off">Scaffolding and analysis example</p>
</div>
