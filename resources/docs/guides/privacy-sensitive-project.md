---
id: privacy-sensitive-project
title: Privacy-sensitive project
section: project_types
position: 12
description: 'Separated private and public directories with gitignore safeguards'
---
## Overview

The **Privacy Sensitive Project** isolates restricted data and outputs from shareable artifacts using separate `private/` and `public/` directories alongside stricter `.gitignore` rules.

## Directory structure

```
my-study/
├── functions/           # Custom R functions (sourced by scaffold())
│   └── helpers.R
├── inputs/
│   ├── private/         # Restricted data (gitignored)
│   │   ├── raw/
│   │   ├── intermediate/
│   │   └── final/
│   └── public/          # Shareable data
│       ├── raw/
│       ├── intermediate/
│       └── final/
├── notebooks/           # Analysis notebooks
├── outputs/
│   └── private/
│       └── notebooks/   # Rendered notebooks (gitignored)
├── scripts/             # Standalone R scripts
├── framework.db         # Local metadata database
└── settings.yml         # Project configuration
```

Additional directories are created on first use: `outputs/private/cache`, `outputs/private/figures`, `outputs/private/tables`, and `outputs/public/`.

## Privacy controls

### Git exclusions

The privacy-sensitive `.gitignore` template excludes:

- `inputs/private/`
- `outputs/private/`
- `.env`
- `framework.db`

### Public and private outputs

`save_*()` functions write to `outputs/private/` by default. Pass `public = TRUE` to write aggregated or de-identified outputs to `outputs/public/`:

```r
# Writes to outputs/private/tables/
save_table(results, "analysis_results")

# Writes to outputs/public/tables/
save_table(summary_stats, "summary", public = TRUE)
```

## Workflow

### Environment initialization

```r
library(framework)
scaffold()
```

### Data classification

Catalog restricted and public datasets under their respective paths in `settings/data.yml`:

```yaml
data:
  # Restricted: contains PHI
  inputs.private.raw.patients:
    path: inputs/private/raw/patients.csv
    type: csv
    locked: true

  # Shareable: aggregated summary
  inputs.public.final.demographics:
    path: inputs/public/final/demographics.csv
    type: csv
```

### De-identification pipeline

Read restricted inputs, aggregate records, and write shareable summaries to `inputs/public/`:

```r
patients <- data_read("inputs.private.raw.patients")

demographics <- patients |>
  group_by(age_group, region) |>
  summarize(n = n())

data_save(demographics, "inputs.public.final.demographics")
```

### Output export

```r
# Private figure
save_figure(detailed_plot, "patient_outcomes")

# Public figure
save_figure(summary_plot, "figure_1", public = TRUE)
```

## Configuration

Example `settings.yml`:

```yaml
default:
  project_type: project_sensitive

  directories:
    notebooks: notebooks
    scripts: scripts
    functions: functions
    inputs_private_raw: inputs/private/raw
    inputs_private_intermediate: inputs/private/intermediate
    inputs_private_final: inputs/private/final
    inputs_public_raw: inputs/public/raw
    inputs_public_intermediate: inputs/public/intermediate
    inputs_public_final: inputs/public/final
    cache: outputs/private/cache

  packages:
    - name: dplyr
      auto_attach: true
    - name: ggplot2
      auto_attach: true

  data:
    inputs.private.raw.patients:
      path: inputs/private/raw/patients.csv
      type: csv
      locked: true
```

## Safeguards

### Locked source files

Set `locked: true` on raw restricted datasets so `data_read()` stops with an error if the underlying file hash changes:

```yaml
data:
  inputs.private.raw.patients:
    path: inputs/private/raw/patients.csv
    type: csv
    locked: true
```

### Aggregation before public export

Aggregate and de-identify records before passing `public = TRUE`:

```r
# Avoid writing row-level records to public outputs
# save_table(raw_patient_data, "patients", public = TRUE)

public_summary <- raw_patient_data |>
  group_by(category) |>
  summarize(mean_value = mean(value), n = n())

save_table(public_summary, "summary", public = TRUE)
```

### Pre-commit inspection

Inspect staged paths with `git_status()` before committing:

```r
git_status()
```
