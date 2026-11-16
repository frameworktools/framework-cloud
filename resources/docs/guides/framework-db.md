---
id: framework-db
title: framework.db
section: features
position: 2
description: 'Local SQLite database for dataset checksums, cache entries, and saved results'
---
## Overview

Every Framework R project maintains a local `framework.db` SQLite database at the project root. Framework updates `framework.db` automatically during `data_read()`, `data_save()`, `cache_remember()`, and `save_*()` calls.

## Database schema

| Table | Purpose |
|---|---|
| `data` | Dataset catalog paths, formats, and SHA-256 checksums |
| `cache` | Cached computation keys, hashes, and expiration timestamps |
| `results` | Saved output metadata for tables, figures, models, reports, and notebooks |
| `connections` | Connection profile metadata |
| `meta` | Project key-value metadata |

## Data integrity

`data_read()` computes a SHA-256 hash on first read and compares subsequent reads against the stored hash:

```yaml
data:
  inputs.raw.patients:
    path: inputs/raw/patients.csv
    locked: true
```

```r
patients <- data_read("inputs.raw.patients")
```

When `locked: true` is set in the catalog entry, `data_read()` stops with an error if the file hash changes. Unlocked entries emit a warning.

## Cache tracking

The `cache` table records creation times, expiration timestamps, and integrity hashes for `.rds` files in the project cache directory:

```r
result <- cache_remember("expensive_model", {
  fit_complex_model(data)
}, expire_after = "7 days")
```

`expire_after` accepts numeric hours (such as `4`) or duration strings (such as `"1 day"` or `"7 days"`).

## Results registry

`save_*()` functions record each saved output in the `results` table:

```r
save_table(results, "analysis_results")
save_figure(plot, "summary_chart")
save_model(model, "regression_v1")
```

Each row records the output name, artifact type (`table`, `figure`, `model`, `report`, or `notebook`), visibility (`public` or `private`), SHA-256 file hash, and creation and modification timestamps.

Query tracked outputs with `result_list()`:

```r
result_list()                               # All saved results
result_list(type = "figure")                # Figures only
result_list(type = "table", public = TRUE)  # Public tables only
```

See [Saving work](/docs/saving-work) and [Viewing work](/docs/viewing-work) for output helpers.

## SQL queries

Query `framework.db` directly with `db_query()` using the built-in `"framework"` connection:

```r
cache_entries <- db_query("SELECT name, expire_at FROM cache", "framework")
data_registry <- db_query("SELECT name, path, hash, locked FROM data", "framework")
results <- db_query("SELECT name, type, created_at FROM results", "framework")
```

## File location

`framework.db` resides at the project root:

```
my-project/
├── framework.db          # Local metadata database
├── settings.yml
├── notebooks/
└── ...
```

Default `.gitignore` templates ignore `framework.db` so local cache paths and machine-specific timestamps stay out of version control.
