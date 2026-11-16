---
id: publishing
title: S3 publishing
section: features
position: 15
description: 'Publish rendered notebooks, datasets, and directories to S3-compatible object storage'
---
## Overview

Framework's S3 publishing helpers render and upload Quarto notebooks, data frames, and output directories to S3-compatible object storage (Amazon S3, Cloudflare R2, MinIO, and DigitalOcean Spaces).

## S3 configuration

Define storage buckets under `connections.storage_buckets` in `settings.yml`:

```yaml
connections:
  storage_buckets:
    default:
      bucket: my-reports-bucket
      region: us-east-1
      prefix: projects/analysis
      access_key: env("S3_ACCESS_KEY")
      secret_key: env("S3_SECRET_KEY")
      static_hosting: true
    backup:
      bucket: backup-bucket
      endpoint: https://minio.internal:9000
      access_key: env("MINIO_ACCESS_KEY")
      secret_key: env("MINIO_SECRET_KEY")
  default_storage_bucket: default
```

Store access keys in `.env`:

```bash
S3_ACCESS_KEY=AKIA...
S3_SECRET_KEY=...
```

### Bucket options

| Option | Description |
|---|---|
| `bucket` | Target bucket name |
| `region` | Bucket region (`us-east-1` by default) |
| `prefix` | Key prefix prepended to all uploads |
| `endpoint` | Custom HTTPS endpoint for S3-compatible services |
| `access_key` | Access key ID referenced via `env()` |
| `secret_key` | Secret access key referenced via `env()` |
| `static_hosting` | Uploads rendered notebooks as `<dest>/index.html` when `true` |
| `default` | Marks a bucket entry as default when `default_storage_bucket` is omitted |

When no default bucket is marked, Framework selects the first defined bucket.

## Notebook publishing

Render and upload a Quarto notebook with `publish_notebook()`:

```r
# Default bucket and destination
publish_notebook("notebooks/analysis.qmd")

# Explicit destination path
publish_notebook("notebooks/analysis.qmd", dest = "reports/q4-2024")

# Named bucket connection
publish_notebook("notebooks/analysis.qmd", connection = "backup")
```

### Static hosting paths

With `static_hosting: true`, `publish_notebook()` writes `<dest>/index.html` for directory-style URLs:

```r
publish_notebook("analysis.qmd", dest = "reports/q4")
# Uploads to: reports/q4/index.html
```

With `static_hosting: false`, `publish_notebook()` preserves the `.html` extension:

```r
publish_notebook("analysis.qmd", dest = "reports/q4")
# Uploads to: reports/q4.html
```

### Resource embedding

`self_contained = TRUE` (the default) embeds scripts, stylesheets, and images into a single HTML file. `self_contained = FALSE` uploads companion asset directories alongside `index.html` and requires `static_hosting: true`:

```r
publish_notebook("analysis.qmd", self_contained = TRUE)
publish_notebook("analysis.qmd", self_contained = FALSE)
```

## Data publishing

Upload data frames or saved data files with `publish_data()`:

```r
# Serialize data frame as CSV
publish_data(my_results, "datasets/results.csv")

# Parquet or RDS formats
publish_data(my_results, "datasets/results.parquet", format = "parquet")
publish_data(my_results, "datasets/results.rds", format = "rds")

# Gzipped CSV (results.csv.gz)
publish_data(my_results, "datasets/results.csv", compress = TRUE)

# Existing local file
publish_data("outputs/model.rds", "models/v2/model.rds")
```

## Directory publishing

Upload local directories recursively with `publish_dir()`:

```r
publish_dir("outputs/dashboard/")
publish_dir("outputs/dashboard/", dest = "dashboards/v2/")
publish_dir("outputs/", pattern = "\\.html$")
```

## Generic file publishing

Upload single files or directories with `publish()`:

```r
publish("outputs/report.html")
publish("outputs/report.html", dest = "reports/final.html")
publish("outputs/charts/", dest = "visualizations/")
```

## Example workflow

```r
library(framework)
scaffold()

results <- analyze_data(my_data)
summary_plot <- create_visualization(results)

save_table(results, "final_results")
save_figure(summary_plot, "summary")
save_notebook("notebooks/analysis.qmd")

publish_notebook("notebooks/analysis.qmd", dest = "reports/q4-analysis")
publish_data(results, "data/q4-results.csv")
```

## Storage conventions

### Key prefixes

Set `prefix` per project so shared buckets stay partitioned by team and project:

```yaml
connections:
  storage_buckets:
    default:
      prefix: team-name/project-name
  default_storage_bucket: default
```

### Versioned paths

Include release tags or ISO dates in `dest` paths when preserving historical snapshots in S3:

```r
publish_notebook("analysis.qmd", dest = "reports/analysis-2024-01-15")
publish_data(results, "data/results-v2.csv")
```
