<?php

namespace App\Support;

/** Synthetic project files for the public, read-only walkthrough. */
final class LandingProject
{
    private static array $sources = [];

    public static function registerSource(string $source): string
    {
        $key = hash('sha256', $source);
        self::$sources[$key] ??= ExampleCode::highlight($source);

        return $key;
    }

    public static function sources(): array
    {
        return self::$sources;
    }

    public static function files(string $language): array
    {
        if ($language === 'stata') {
            return self::stataFiles();
        }
        $python = $language === 'py';
        $extension = $python ? 'py' : 'R';
        $files = [
            'AGENTS.md' => "# Penguin study\n\nUse Framework's project conventions.\nStart each analysis with scaffold().\nRead cataloged data with data_read().\nKeep helpers in functions/.\nVerify inputs before publishing results.\n\n## Skills\n\nRead the matching skill in .claude/skills/\nbefore data, package, or publishing tasks.\nReview outputs for private data before sharing.\n",
            'settings.yml' => $python ? "default:\n  scaffold:\n    seed_on_scaffold: true\n    seed: 123\n" : "default:\n  scaffold:\n    source_all_functions: true\n    set_theme_on_scaffold: true\n    ggplot_theme: theme_minimal\n    seed_on_scaffold: true\n    seed: 123\n",
            'settings/quarto.yml' => "default:\n  quarto:\n    html:\n      theme: cosmo\n      toc: true\n      toc_depth: 3\n      code_fold: true\n      code_tools: true\n      highlight_style: github\n      embed_resources: true\n    revealjs:\n      theme: simple\n      slide_number: true\n      transition: fade\n",
            'settings/data.yml' => "default:\n  data:\n    inputs:\n      raw:\n        penguins:\n          path: inputs/raw/penguins.csv\n          type: csv\n          locked: true\n",
            'settings/connections.yml' => "default:\n  connections:\n    databases:\n      study_db:\n        driver: postgresql\n        host: env(\"STUDY_DB_HOST\")\n        database: env(\"STUDY_DB_NAME\")\n        user: env(\"STUDY_DB_USER\")\n        password: env(\"STUDY_DB_PASSWORD\")\n        port: 5432\n",
            'inputs/raw/penguins.csv' => "species,island,flipper_length_mm,body_mass_g\nAdelie,Torgersen,181,3750\nAdelie,Torgersen,186,3800\nAdelie,Torgersen,195,3250\nGentoo,Biscoe,209,4400\nGentoo,Biscoe,215,5150\nGentoo,Biscoe,214,4650\nChinstrap,Dream,197,4150\nChinstrap,Dream,200,3400\nChinstrap,Dream,200,3800\n",
            "functions/data_prep.$extension" => $python ? "# Importable after fw.scaffold()\ndef drop_incomplete(data):\n    # Keep penguins with every measurement\n    return data.dropna()" : "# Sourced by scaffold()\ndrop_incomplete <- function(data) {\n  # Keep penguins with every measurement\n  data[complete.cases(data), ]\n}",
            'notebooks/report.qmd' => $python ? "---\ntitle: Penguin study\nformat: html\njupyter: python3\n---\n\n## Analysis\n\n```{python}\nimport framework as fw\nfw.scaffold()\npenguins = fw.data_read(\"inputs.raw.penguins\")\npenguins.describe()\n```" : "---\ntitle: Penguin study\nformat: html\n---\n\n## Analysis\n\n```{r}\nlibrary(framework)\nscaffold()\npenguins <- data_read(\"inputs.raw.penguins\")\nsummary(penguins)\n```",
            'outputs/README.md' => "# Analysis outputs\n\nSave figures, tables, and model summaries here.\nReview each output before publishing it.\nFramework Cloud keeps published versions.\n",
            '.gitignore' => "# Local state and credentials\n.env\nframework.db\nframework.db-wal\nframework.db-shm\n.venv/\n",
        ];
        if ($python) {
            $files['pyproject.toml'] = "# Install dependencies with: uv sync\n[project]\nname = \"penguin-study\"\nversion = \"0.1.0\"\nrequires-python = \">=3.11\"\ndependencies = [\n    \"framework-py\",\n    \"pandas\",\n    \"matplotlib\",\n    \"statsmodels\",\n]";
        } else {
            $files['settings/packages.yml'] = "# Installed and attached by scaffold()\ndefault:\n  packages:\n    ggplot2:\n      source: cran\n      auto_attach: true\n";
            $files['penguin-study.Rproj'] = "Version: 1.0\n\nRestoreWorkspace: No\nSaveWorkspace: No\nAlwaysSaveHistory: No\n\nEncoding: UTF-8\n";
        }
        foreach (['figure.svg', 'model.json', 'report.html'] as $output) {
            $files['outputs/'.$output] = file_get_contents(resource_path('examples/landing/outputs/'.$output));
        }
        foreach (glob(resource_path("examples/landing/skills/$language/*/SKILL.md")) as $path) {
            $files['.claude/skills/'.basename(dirname($path)).'/SKILL.md'] = file_get_contents($path);
        }

        return $files;
    }

    /** A Stata project as fw scaffolds it: settings.do, master.do, do/, and logs/. */
    private static function stataFiles(): array
    {
        $files = [
            'AGENTS.md' => "# Penguin study\n\nUse Framework's project conventions.\nStart each do-file with fw_project.\nLoad cataloged data with fw_use.\nRun every step from master.do.\nVerify inputs before publishing results.\n",
            'settings.yml' => "default:\n  language: stata\n  directories:\n    do: do\n    logs: logs\n",
            'settings/data.yml' => "default:\n  data:\n    inputs:\n      raw:\n        penguins:\n          path: inputs/raw/penguins.dta\n          type: stata\n          locked: true\n",
            'settings.do' => "* settings.do: project paths for penguin-study\n* Generated by fw. fw_project sets the same paths as \$fw_* globals.\n\nglobal inputs_raw \"inputs/raw\"\nglobal outputs_figures \"outputs/figures\"\nglobal logs \"logs\"\n",
            'master.do' => "* master.do: runs every step of penguin-study in order\n\nversion 16\nclear all\nset more off\n\ndo \"settings.do\"\n\ndo \"do/analysis.do\"\ndo \"do/publish.do\"\n",
            'outputs/README.md' => "# Analysis outputs\n\nSave figures, tables, and estimates here.\nReview each output before publishing it.\nFramework Cloud keeps published versions.\n",
            '.gitignore' => "# Local state and credentials\n.env\nframework.db\nlogs/\n",
        ];
        foreach (['figure.svg', 'model.json', 'report.html'] as $output) {
            $files['outputs/'.$output] = file_get_contents(resource_path('examples/landing/outputs/'.$output));
        }
        // Copied from framework-cli's internal/stata/skills, which fw installs in .agents/skills
        foreach (glob(resource_path('examples/landing/skills/stata/*/SKILL.md')) as $path) {
            $files['.agents/skills/'.basename(dirname($path)).'/SKILL.md'] = file_get_contents($path);
        }

        return $files;
    }

    public static function securityFiles(string $language): array
    {
        return [
            'settings/data.yml' => "default:\n  data:\n    inputs:\n      private:\n        penguins:\n          path: inputs/private/raw/penguins.csv\n          type: csv\n          locked: true\n        working:\n          path: inputs/private/intermediate/working.csv\n          type: csv\n          locked: false\n",
            '.gitignore' => "# Sensitive project defaults\n/inputs/private/\n/reference/private/\n/outputs/private/\n**/private/**\n**/*_private/**\n**/sensitive/**\n\n# Secrets and local records\n.env\n.Renviron\n**/credentials/**\n**/secrets/**\nframework.db\nframework.db-wal\nframework.db-shm\n",
            'settings/git.yml' => "default:\n  git:\n    hooks:\n      data_security: true\n      check_sensitive_dirs: true\n      check_notebook_outputs: true\n",
            'inputs/private/README.md' => "# Private inputs\n\nraw/           Original identifiable data\nintermediate/  Working data\nfinal/         Analysis-ready private data\n\nPrivate folders are ignored by Git.\nReview de-identified or aggregate results\nbefore placing them in public folders.\n",
            'outputs/private/README.md' => "# Private outputs\n\nKeep unreviewed figures, tables, models, and\nrendered notebooks here. Notebook output\ncan contain identifiers or individual rows.\n",
            'outputs/public/README.md' => "# Public outputs\n\nOnly reviewed results belong here.\nCheck identifiers, small cells, and embedded\ndata before committing or publishing.\n",
            'records.sql' => "-- Query the local framework.db database\n-- Catalog reads update hash and last_read_at.\nSELECT name, path, hash, locked, last_read_at\nFROM data;\n\n-- This is the latest record per dataset,\n-- not a complete history of every read.\n",
            'commit-check.txt' => "Illustrative pre-commit result\n\n$ git commit -m \"Add study data\"\n...\n✗ Data security check failed\n\nThe enabled audit found a failed check.\nThe commit stops. Run git_security_audit()\nfor the affected files and ignore rules.\n",
            'changed-data.txt' => "Illustrative messages\n\nUnlocked file changed:\nFile hash changed for 'inputs.working'\n\nLocked file changed:\nHash mismatch for locked data 'inputs.private.penguins'\n\nA changed unlocked file warns and updates\nits local record. A changed locked file\nstops the read with an error.\n",
        ];
    }
}
