---
id: environment
title: Environment
section: features
position: 14
description: 'Manage .env secrets and inspect or reset R session state'
---
## Overview

Framework loads secret credentials from a gitignored `.env` file and provides R session utilities to inspect or reset memory and attached packages.

## Environment variables

### Project .env file

Store database passwords and API keys in `.env` at the project root:

```bash
# .env (gitignored)
DB_HOST=db.example.com
DB_USER=analyst
DB_PASSWORD=secret123

OPENAI_API_KEY=sk-...
S3_ACCESS_KEY=AKIA...
S3_SECRET_KEY=...
```

Built-in `.gitignore` templates exclude `.env` automatically.

### Settings references

Read `.env` variables inside `settings.yml` with `env()`:

```yaml
connections:
  analytics:
    driver: postgres
    host: env("DB_HOST")
    user: env("DB_USER")
    password: env("DB_PASSWORD")

  storage_buckets:
    default:
      bucket: my-reports-bucket
      region: us-east-1
      access_key: env("S3_ACCESS_KEY")
      secret_key: env("S3_SECRET_KEY")
  default_storage_bucket: default
```

### Starter .env template

New projects include a commented `.env` template for common services:

```bash
# PostgreSQL connection
POSTGRES_HOST=
POSTGRES_PORT=5432
POSTGRES_DB=
POSTGRES_SCHEMA=public
POSTGRES_USER=
POSTGRES_PASSWORD=

# S3-compatible object storage
S3_ENDPOINT=
S3_BUCKET=
S3_REGION=
S3_ACCESS_KEY=
S3_SECRET_KEY=
S3_SESSION_TOKEN=
```

## R session utilities

### Session reset

Clear global environment objects, close open graphics devices, detach attached packages other than `framework`, and run garbage collection with `env_clear()`:

```r
# Reset entire session
env_clear(envir = globalenv())

# Retain named objects
env_clear(keep = c("config", "raw_data"), envir = globalenv())
```

### Session summary

Print the active R version, platform, attached package versions, largest global objects, and memory footprint with `env_summary()`:

```r
env_summary(envir = globalenv())
```

Example output:

```
=== Environment Summary ===

R Version: R version 4.3.2 (2023-10-31)
Platform: aarch64-apple-darwin20

Loaded Packages (5):
  - framework (0.1.0)
  - dplyr (1.1.4)
  - ggplot2 (3.4.4)
  - readr (2.1.4)
  - tibble (3.2.1)

Objects in Global Environment: 12
  Top 10 by size:
    large_dataset [tbl_df] - 45.2 MB
    model_results [list] - 12.3 MB
    summary_stats [data.frame] - 0.8 KB
    ...

Memory in use: 234.5 MB
```

## Security conventions

### Externalized secrets

Reference secrets with `env()` rather than committing literals in `settings.yml`:

```yaml
# Avoid literal credentials
# password: "my_secret_password"

# Read from .env
password: env("DB_PASSWORD")
```

### Gitignore verification

Framework warns if `.env` is missing from `.gitignore`:

```
Warning: .env is NOT in .gitignore!
  Add it to prevent accidentally committing secrets.
```

### Specific variable names

Prefix variable names with the target service and environment:

```bash
PROD_DB_PASSWORD=...
STAGING_DB_PASSWORD=...
ANALYTICS_API_KEY=...
```
