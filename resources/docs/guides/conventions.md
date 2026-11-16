---
id: conventions
title: Conventions
section: getting_started
position: 3
description: 'Project layout, package attachment, and data conventions'
---
## Design principles

Framework favors **convention over configuration** and supplies standard directory layouts and defaults for research projects. Every directory path, package list, and template remains configurable globally or per project.

Configured defaults apply automatically whenever you create a new project, keeping directory structures and scripts consistent across analyses.

## scaffold()

Start each notebook or script by calling `scaffold()`:

```r
library(framework)
scaffold()
```

You can also call `framework::scaffold()` directly without attaching `framework`.

`scaffold()` locates the project root, loads `.env`, parses `settings.yml`, applies optional seed and `ggplot2` theme settings, installs missing project packages, attaches configured packages, sources every `.R` file in `functions/`, and runs `scaffold.R`.

## Package loading

`scaffold()` calls `library()` on packages configured with `auto_attach: true`. Packages configured with `auto_attach: false` are installed without being attached. Call their functions with `pkg::fn()` or attach them explicitly with `library()`.

Example `settings.yml`:

```yaml
packages:
  - name: dplyr
    auto_attach: true
  - name: readr
    auto_attach: false
```

Corresponding R script:

```r
library(framework)
scaffold()

df <- readr::read_csv("data.csv") |>
  filter(x > 0) |>
  mutate(y = x * 2)
```

Here `dplyr` verbs (`filter` and `mutate`) are attached directly while `readr::read_csv()` uses explicit namespacing.

## Function auto-loading

`scaffold()` sources every `.R` file in `functions/` automatically:

```
functions/
├── text_processing.R
├── model_prep.R
└── plotting_helpers.R
```

Keep only function definitions inside `functions/*.R`. Top-level statements outside `function()` blocks execute every time `scaffold()` runs.

Auto-loading removes manual `source()` calls from scripts and notebooks:

```r
source("helpers/cleaning.R")
source("helpers/plotting.R")
source("utils/text_processing.R")
```

You can rename files or reorganize functions across files in `functions/` without editing downstream notebooks. Disable function auto-loading in settings when manual `source()` control is required. See [Functions](/docs/functions) for configuration details.

## Inputs and outputs

Framework separates read-only input data in `inputs/` from generated artifacts in `outputs/`.

### Inputs

Input datasets live under `inputs/` by processing stage:

```
inputs/
├── raw/           # Unmodified external source data
├── intermediate/  # Cleaned or transformed intermediate files
└── final/         # Analysis-ready datasets
```

- **raw/**: original external files kept unmodified.
- **intermediate/**: intermediate pipeline datasets.
- **final/**: final datasets consumed by models, tables, and figures.

### Data catalog

Framework records datasets in the `data` section of `settings.yml`. Register existing files with `data_add()`:

```r
data_add("inputs/raw/survey_2024.csv", name = "raw.survey")
data_add("inputs/final/analysis.rds", name = "final.analysis")
```

Write and register derived datasets in one call with `data_save()`:

```r
data_save(clean_df, "inputs.intermediate.survey_clean")
```

Read cataloged datasets by dot-separated key with `data_read()`:

```r
survey <- data_read("raw.survey")
```

See [Data management](/docs/data-management) for format options and integrity locks.

### Outputs

Generated artifacts live under `outputs/` by artifact type:

```
outputs/
├── tables/    # Exported tables and CSVs
├── figures/   # Saved plots and charts
├── models/    # Serialized model objects
└── notebooks/ # Rendered HTML and PDF notebooks
```

- **tables/**: target directory for `save_table()`.
- **figures/**: target directory for `save_figure()`.
- **models/**: target directory for `save_model()`.
- **notebooks/**: target directory for `save_notebook()`.

`save_notebook("notebooks/analysis.qmd")` renders the source `.qmd` document into `outputs/notebooks/`, keeping generated HTML and PDF files separate from source files.

## Work directories

- **notebooks/**: Quarto and R Markdown source documents.
- **scripts/**: standalone R scripts.
- **functions/**: reusable R helper functions sourced by `scaffold()`.
- **scratch/**: exploratory scripts excluded from production pipelines.

## Sensitive data projects

Privacy-sensitive projects divide `inputs/` and `outputs/` into `private/` and `public/` subdirectories:

```
inputs/
├── private/    # Restricted data (gitignored)
└── public/     # Shareable data
```

`.gitignore` rules block `inputs/private/` and `outputs/private/` from version control.

## Data integrity

`data_read()` and `data_save()` store SHA-256 file hashes in `framework.db`. When a file changes between runs, `data_read()` stops with an error for locked entries (saved with `locked = TRUE` or marked `locked: true`) and warns for unlocked entries, the default:

```
Warning: File hash changed for 'inputs/raw/survey.csv'
Previous hash: a1b2c3...
Current hash:  d4e5f6...
```

## Customization

Framework provides four customizable project types:

- **Standard project**: general-purpose data analysis and pipelines.
- **Privacy sensitive project**: isolated `private/` and `public/` directories.
- **Course**: directories for slides, assignments, readings, and course documents.
- **Presentation**: minimal layout for Quarto RevealJS slide decks.

Configure global defaults on Framework Cloud or project overrides in `settings.yml`.
