---
id: settings-yml
title: settings.yml
section: getting_started
position: 4
description: 'Structure and keys of the settings.yml project configuration file'
---
## Overview

Every Framework project stores its configuration in `settings.yml` at the project root. Edit `settings.yml` directly in a text editor.

`settings.yml` follows the `config` package layout with a top-level `default:` block and optional named environment blocks such as `production:`.

## Split files

Large projects can split sections of `settings.yml` into dedicated files under `settings/` by referencing file paths:

```yaml
default:
  connections: settings/connections.yml
  data: settings/data.yml
```

Create each referenced YAML file:

**settings/connections.yml:**
```yaml
connections:
  db:
    driver: postgresql
    host: env("DB_HOST")
    database: mydb
```

**settings/data.yml:**
```yaml
data:
  raw.survey:
    path: inputs/raw/survey.csv
    type: csv
```

Inline keys in root `settings.yml` take precedence over split files and emit a warning on conflict, except `options` entries which merge.

## Sections

### Basics

Project type and author metadata:

```yaml
project_type: project  # project, project_sensitive, course, or presentation

author:
  name: "Your Name"
  email: "your.email@example.com"
  affiliation: "Your Institution"
```

### Project structure

Maps logical directory keys to project-relative paths:

```yaml
directories:
  # Source code and notebooks
  notebooks: notebooks
  scripts: scripts
  functions: functions

  # Input data (read-only)
  inputs_raw: inputs/raw
  inputs_intermediate: inputs/intermediate
  inputs_final: inputs/final
  docs: docs

  # Derived outputs (write-only)
  outputs_tables: outputs/tables
  outputs_figures: outputs/figures
  outputs_models: outputs/models
  outputs_reports: outputs/reports

  # Cache and scratch space
  cache: outputs/cache
  scratch: scratch
  logs: logs
```

Query configured paths in R with `settings("notebooks")` or `settings("directories.notebooks")`.

### Packages

R packages installed and optionally attached by `scaffold()`:

```yaml
packages:
  - name: dplyr
    auto_attach: true   # Load automatically
  - name: tidyr
    auto_attach: true
  - name: ggplot2
    auto_attach: true
  - name: readr
    auto_attach: false  # Install only, namespace explicitly
```

See [Packages](/docs/packages) for version pinning and GitHub remotes.

### Data catalog

Maps dataset names to paths and file types using nested or dot-notation keys:

**Nested YAML:**
```yaml
data:
  raw:
    survey:
      path: inputs/raw/survey.csv
      type: csv
  intermediate:
    cleaned:
      path: inputs/intermediate/cleaned.rds
      type: rds
  final:
    analysis:
      path: inputs/final/analysis.rds
      type: rds
```

**Dot-notation keys:**
```yaml
data:
  raw.survey:
    path: inputs/raw/survey.csv
    type: csv
  final.analysis:
    path: inputs/final/analysis.rds
    type: rds
```

`data_read()` resolves both forms identically:

```r
data_read("raw.survey")
data_read("final.analysis")
```

See [Data management](/docs/data-management) for supported formats and locked datasets.

### Git and hooks

Pre-commit hook settings:

```yaml
git:
  hooks:
    data_security: false  # Check for secrets before commit
```

See [Git integration](/docs/git) for hook behavior.

### AI assistants

Canonical instruction file selection:

```yaml
ai:
  canonical_file: "AGENTS.md"  # AGENTS.md, CLAUDE.md, or .github/copilot-instructions.md
```

Framework 2.0 removed the `ai_sync` hook; `CLAUDE.md` references `@AGENTS.md` instead. See [AI assistants](/docs/ai-assistants).

### Connections

Database profiles read credentials from [environment variables](#environment-variables) with `env()`:

```yaml
connections:
  db:
    driver: postgresql
    host: env("DB_HOST")
    database: env("DB_NAME")
    user: env("DB_USER")
    password: env("DB_PASS")
```

See [Connections](/docs/connections) for database and S3 configuration.

### Random seeds

Deterministic seed settings for `scaffold()`:

```yaml
seed: 123                 # Default seed value
seed_on_scaffold: false   # Set seed automatically when scaffold() runs
```

Setting `seed_on_scaffold: true` calls `set.seed(seed)` inside `scaffold()`.

## Environment variables

Reference environment variables in `settings.yml` with `env()` and optional fallback defaults:

```yaml
connections:
  db:
    host: env("DB_HOST")
    password: env("DB_PASS", "default_value")  # With default
```

Store local values in the gitignored `.env` file:

```bash
DB_HOST=localhost
DB_PASS=secret123
```

## Environment-specific settings

Add top-level environment blocks to override `default:` values per deployment environment:

```yaml
default:
  connections:
    db:
      database: dev_database

production:
  connections:
    db:
      database: prod_database
```

Select an active environment in R with `R_CONFIG_ACTIVE`:

```r
Sys.setenv(R_CONFIG_ACTIVE = "production")
```

## Settings lookup in R

Read configuration keys from R with `settings()`:

```r
settings("author.name")
settings("directories")
settings("notebooks")           # Shorthand for directories.notebooks
settings("missing.key", default = "fallback")
```

## Complete example

```yaml
default:
  project_type: project

  author:
    name: "Your Name"
    email: "your.email@example.com"
    affiliation: "Your Institution"

  packages:
    - name: dplyr
      auto_attach: true
    - name: tidyr
      auto_attach: true
    - name: ggplot2
      auto_attach: true
    - name: readr
      auto_attach: false

  directories:
    notebooks: notebooks
    scripts: scripts
    functions: functions
    inputs_raw: inputs/raw
    inputs_intermediate: inputs/intermediate
    inputs_final: inputs/final
    docs: docs
    outputs_tables: outputs/tables
    outputs_figures: outputs/figures
    outputs_models: outputs/models
    outputs_reports: outputs/reports
    cache: outputs/cache
    scratch: scratch
    logs: logs

  seed: 123
  seed_on_scaffold: false

  ai:
    canonical_file: "AGENTS.md"

  git:
    hooks:
      data_security: false

  data:
    # Data catalog entries

  connections:
    # Database connections
```
