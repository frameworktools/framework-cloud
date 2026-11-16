---
id: data-management
title: Data management
section: features
position: 4
description: 'Catalog datasets, resolve formats, and verify SHA-256 checksums'
---
## Overview

Framework manages datasets through a declarative catalog in `settings.yml`. Reference datasets by dot-separated keys such as `inputs.raw.sales`, and Framework resolves file paths, selects the file reader, and verifies SHA-256 checksums.

## Data catalog

Register datasets under `data:` in `settings.yml`:

```yaml
data:
  inputs.raw.sales:
    path: inputs/raw/sales_2024.csv
    type: csv

  inputs.raw.customers:
    path: inputs/raw/customers.parquet
    type: parquet

  inputs.final.merged:
    path: inputs/final/merged_data.rds
    type: rds
```

## Supported formats

| Type | Extensions | Reader | Package |
|---|---|---|---|
| `csv` | `.csv` | `readr::read_delim()` | `readr` |
| `tsv` | `.tsv`, `.txt`, `.dat` | `readr::read_delim()` | `readr` |
| `rds` | `.rds` | `readRDS()` | `base` |
| `excel` | `.xlsx`, `.xls` | `readxl::read_excel()` | `readxl` |
| `stata` | `.dta` | `haven::read_dta()` | `haven` |
| `spss` | `.sav`, `.zsav` | `haven::read_sav()` | `haven` |
| `spss_por` | `.por` | `haven::read_por()` | `haven` |
| `sas` | `.sas7bdat`, `.sas7bcat` | `haven::read_sas()` | `haven` |
| `sas_xpt` | `.xpt` | `haven::read_xpt()` | `haven` |
| `parquet` | `.parquet` | `arrow::read_parquet()` | `arrow` |

For Stata, SPSS, and SAS files, `data_read()` strips variable labels and format attributes by default. Pass `keep_attributes = TRUE` to retain them. Reading `.parquet` files requires the `arrow` package.

## Data reading

Load cataloged datasets with `data_read()`:

```r
sales <- data_read("inputs.raw.sales")
customers <- data_read("inputs.raw.customers")
```

## Data saving

Write derived datasets and register them in the catalog with `data_save()`:

```r
merged <- sales |>
  left_join(customers, by = "customer_id")

data_save(merged, "inputs.final.merged")
```

## Data integrity

`data_read()` and `data_save()` compute a SHA-256 hash of each file and store it in `framework.db`:

1. First read records the file's SHA-256 hash.
2. Subsequent reads compare the current file hash against the recorded hash.
3. Mismatches emit a warning unless the entry is locked. Entries are unlocked unless `data_add()` or `data_save()` gets `locked = TRUE`.

Set `locked: true` on immutable source files so hash mismatches raise an error rather than a warning:

```yaml
data:
  inputs.raw.survey:
    path: inputs/raw/survey_results.csv
    type: csv
    locked: true
```

`data_save()` does not change locked data. Saving the same contents again works and keeps the lock. New contents stop with an error before the file is written. Save derived data under another name, or pass `unlock = TRUE` to replace locked data on purpose. The new file is unlocked unless you also pass `locked = TRUE`:

```r
data_save(final, "inputs.final.analysis", locked = TRUE)
data_save(corrected, "inputs.raw.survey.csv", unlock = TRUE, locked = TRUE)
data_save(cleaned, "inputs.intermediate.cleaned", unlock = TRUE)
```

## Catalog inspection

Inspect a catalog entry's path, type, lock flag, and privacy status with `data_info()`:

```r
data_info("inputs.raw.sales")
```

## Key and directory conventions

### Directory stages

```
inputs/
├── raw/          # Unmodified external data
├── intermediate/ # Cleaned intermediate files
├── final/        # Analysis-ready datasets
└── reference/    # Lookup tables and crosswalks
```

### Dot-notation keys

Match catalog keys to directory stages (`inputs.<stage>.<name>`):

```yaml
data:
  inputs.raw.sales_2024:
    path: inputs/raw/sales_2024.csv

  inputs.intermediate.sales_cleaned:
    path: inputs/intermediate/sales_cleaned.rds

  inputs.final.sales_ready:
    path: inputs/final/sales_ready.rds
```
