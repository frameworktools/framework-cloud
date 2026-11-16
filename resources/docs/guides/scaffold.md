---
id: scaffold
title: scaffold()
section: features
position: 1
description: 'Initialize the R session, packages, and helper functions for a project'
---
## Overview

`scaffold()` initializes your R session at the top of every notebook and script in a Framework project.

Package auto-attachment (`auto_attach: true`) applies only to R. Python's `scaffold()` configures paths and seeds without auto-importing packages into the caller's namespace. Stata has no auto-attach setting.

```r
library(framework)
scaffold()
```

Or call `scaffold()` with an explicit package prefix:

```r
framework::scaffold()
```

## Initialization sequence

`scaffold()` executes these steps in order:

1. **Project root resolution**: walks upward from the working directory to find the project root.
2. **Environment loading**: reads variables from `.env`.
3. **Configuration parsing**: loads `settings.yml` and any split files.
4. **Random seed**: calls `set.seed()` when `seed_on_scaffold: true` is enabled.
5. **Plot theme**: applies the configured `ggplot2` theme when enabled.
6. **Package installation**: installs any missing packages declared in `settings.yml`.
7. **Package attachment**: calls `library()` on packages marked `auto_attach: true`.
8. **Function sourcing**: sources every `.R` file in configured `functions/` directories.

## Session environment

After `scaffold()` completes, attached packages, sourced functions, and `settings()` queries are available in your session:

```r
# Attached package verbs
df <- read_csv("data.csv") |>
  filter(x > 0) |>
  mutate(y = x * 2)

# Helper function from functions/
result <- my_custom_function(df)

# Configuration lookups
settings("author.name")
settings("directories.notebooks")
```

## Project discovery

`scaffold()` locates the project root by checking the working directory and its ancestors for:

- `settings.yml`
- `.Rproj` or `.code-workspace` files adjacent to Framework settings
- Standard Framework directories (`notebooks/`, `scripts/`, and `functions/`)

Scripts and notebooks in nested subdirectories resolve the same project root:

```
my-project/
├── settings.yml
├── notebooks/
│   └── 01-analysis.qmd    # Resolves my-project/
├── scripts/
│   └── process.R          # Resolves my-project/
└── functions/
    └── helpers.R          # Resolves my-project/
```

## Configuration

Configure `scaffold()` options in `settings.yml`:

```yaml
seed: 123
seed_on_scaffold: true

packages:
  - name: dplyr
    auto_attach: true
  - name: ggplot2
    auto_attach: true

directories:
  functions: functions
```

### Random seed

Seed initialization is disabled by default. Set both `seed` and `seed_on_scaffold: true` to set a deterministic seed during `scaffold()`:

```yaml
seed: 123
seed_on_scaffold: true
```

### Packages

Packages with `auto_attach: true` attach via `library()`. Packages with `auto_attach: false` install without attaching:

```yaml
packages:
  - name: dplyr
    auto_attach: true    # Installed and attached
  - name: jsonlite
    auto_attach: false   # Installed only
```

### Functions directory

`scaffold()` sources `.R` files from `directories.functions`:

```yaml
directories:
  functions: functions
```

Pass a list to source multiple directories in order:

```yaml
directories:
  functions:
    - functions
    - helpers
```

## Custom scaffold.R

When `scaffold.R` exists at the project root, `scaffold()` sources it after standard initialization for project-specific setup:

```r
# scaffold.R
reference_data <- data_read("reference.lookup")
options(scipen = 999)
```
