---
id: standard-project
title: Standard project
section: project_types
position: 11
description: 'Default project structure for data analysis, pipelines, and reporting'
---
## Overview

The **Standard Project** is Framework's default structure for data analysis, statistical modeling, and reporting.

## Directory structure

```
my-project/
├── functions/           # Custom R functions (sourced by scaffold())
│   └── helpers.R
├── inputs/              # Read-only and staged datasets
│   ├── raw/             # Unmodified external files
│   ├── intermediate/    # Cleaned intermediate datasets
│   └── final/           # Analysis-ready datasets
├── notebooks/           # Quarto or R Markdown documents
│   ├── 01-exploration.qmd
│   └── 02-modeling.qmd
├── outputs/
│   └── notebooks/       # Rendered HTML and PDF notebooks
├── scripts/             # Standalone R scripts
│   └── data-pipeline.R
├── framework.db         # Local metadata database
└── settings.yml         # Project configuration
```

Additional output directories (`outputs/cache`, `outputs/tables`, and `outputs/figures`) are created automatically on first save.

## Workflow

### Environment initialization

Start each notebook or script with `scaffold()`:

```r
library(framework)
scaffold()
```

`scaffold()` attaches configured packages from `settings.yml`, sources `.R` files in `functions/`, and loads project configuration.

### Notebooks

Create numbered or subdirectory notebooks with `make_notebook()`:

```r
make_notebook("exploration")
make_notebook("modeling", subdir = "analysis")
```

### Data catalog

Register datasets in `settings/data.yml`:

```yaml
data:
  inputs.raw.sales:
    path: inputs/raw/sales_2024.csv
    type: csv
```

Load datasets by key in R:

```r
sales <- data_read("inputs.raw.sales")
```

### Computation cache

Cache long-running steps with `cache_remember()`:

```r
model_results <- cache_remember("sales_model", {
  fit_complex_model(sales)
})
```

## Organization conventions

### Notebook numbering

Prefix notebook filenames with two-digit numbers to record execution order:

```
notebooks/
├── 01-data-import.qmd
├── 02-cleaning.qmd
├── 03-exploration.qmd
├── 04-modeling.qmd
└── 05-reporting.qmd
```

### Subdirectories

Group large notebook collections into topic subdirectories:

```r
make_notebook("eda", subdir = "exploration")
make_notebook("regression", subdir = "modeling")
```

### Data stages

Keep `inputs/raw/` read-only and write cleaned or joined datasets to `inputs/intermediate/` and `inputs/final/`:

```
inputs/
├── raw/          # Unmodified source files
├── intermediate/ # Cleaned intermediate files
└── final/        # Final analysis datasets
```
