---
id: saving-work
title: Saved outputs
section: features
position: 9
description: 'Save tables, figures, models, reports, and notebooks with metadata tracking'
---
## Overview

Framework's `save_*()` functions write tables, figures, models, reports, and rendered notebooks to configured output directories and record each file in `framework.db`.

## Output functions

### Tables

Write data frames to `outputs/tables/`:

```r
# CSV (default)
save_table(results, "analysis_results")

# Explicit formats
save_table(results, "analysis_results", format = "rds")
save_table(results, "analysis_results", format = "parquet")
save_table(results, "analysis_results", format = "xlsx")

# Public output in privacy-sensitive projects
save_table(summary_stats, "public_summary", public = TRUE)
```

### Figures

Write `ggplot2` or base R plots to `outputs/figures/`:

```r
# Default dimensions and PNG format
save_figure(my_plot, "revenue_chart")

# Explicit width, height, and resolution
save_figure(my_plot, "revenue_chart", width = 10, height = 6, dpi = 300)

# Vector formats
save_figure(my_plot, "revenue_chart", format = "pdf")
save_figure(my_plot, "revenue_chart", format = "svg")
```

### Models

Serialize fitted model objects to `outputs/models/`:

```r
model <- lm(y ~ x, data = df)
save_model(model, "regression_v1")
```

### Reports and notebooks

Copy, move, or render documents into output directories:

```r
# Copy a rendered HTML report
save_report("outputs/analysis.html", "quarterly_report")

# Move instead of copying
save_report("outputs/analysis.html", "quarterly_report", move = TRUE)

# Render a Quarto document and save to outputs/notebooks/
save_notebook("notebooks/analysis.qmd", "final_analysis")
```

## Metadata tracking

Every `save_*()` call updates the `results` table in `framework.db`:

| Field | Description |
|---|---|
| `name` | Project-relative path of the saved file |
| `type` | Artifact type (`table`, `figure`, `model`, `report`, or `notebook`) |
| `public` | Public or private destination flag |
| `hash` | SHA-256 checksum of the saved file |
| `created_at` | Initial creation timestamp |
| `updated_at` | Latest modification timestamp |

## Public and private outputs

In privacy-sensitive projects, `public` selects between gitignored `outputs/private/` and shareable `outputs/public/`:

```r
# Writes to outputs/private/tables/
save_table(detailed_results, "patient_outcomes")

# Writes to outputs/public/tables/
save_table(aggregated_stats, "summary", public = TRUE)
```

In standard projects, outputs write directly under `outputs/` regardless of `public`.

## Output directory layout

Standard projects:

```
outputs/
├── tables/           # save_table()
├── figures/          # save_figure()
├── models/           # save_model()
├── reports/          # save_report()
└── notebooks/        # save_notebook()
```

Privacy-sensitive projects:

```
outputs/
├── private/
│   ├── tables/
│   ├── figures/
│   └── ...
└── public/
    ├── tables/
    ├── figures/
    └── ...
```
