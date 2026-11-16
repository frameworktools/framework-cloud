---
id: quarto
title: Quarto integration
section: features
position: 7
description: 'Quarto configuration, render directories, and document authoring'
---
## Overview

Framework integrates with [Quarto](https://quarto.org) to configure HTML documents and RevealJS presentations, separate `.qmd` source files from rendered outputs, and provide starter templates.

## Render directories

Framework keeps `.qmd` source files in authoring directories and writes rendered HTML or PDF outputs to dedicated render directories that `.gitignore` can exclude.

### Directory mappings

| Project type | Source directory | Render directory |
|---|---|---|
| Standard Project | `notebooks/` | `outputs/notebooks/` |
| Privacy Sensitive | `notebooks/` | `outputs/private/notebooks/` |
| Course/Teaching | `slides/` | `rendered/slides/` |

The root `_quarto.yml` specifies `output-dir`, and Quarto keeps each source directory's path beneath it:

```yaml
project:
  type: default
  output-dir: outputs

format:
  html:
    theme: default
    embed-resources: true
    toc: true
```

## Quarto configuration

Framework generates `_quarto.yml` files once at project creation and leaves subsequent edits untouched.

### Global defaults

Configure global Quarto defaults under `quarto` in Framework's global settings file, which `framework::setup()` creates:

```r
framework::setup()
```

Configurable options cover:

- **HTML documents**: theme, `embed-resources`, table of contents and depth, code folding, code tools, and syntax highlighting style.
- **RevealJS presentations**: theme, incremental bullets, slide numbers, and slide transitions.

### Generated files

```
my-project/
├── _quarto.yml              # Root project config
├── notebooks/
│   └── analysis.qmd
└── outputs/
    └── notebooks/           # Rendered HTML and PDF files
```

### _quarto.yml overrides

Edit `_quarto.yml` directly in any directory:

```yaml
# _quarto.yml
project:
  type: default
  output-dir: outputs

format:
  html:
    theme: cosmo
    toc: true
    toc-depth: 4
    code-fold: true
    code-tools: true
    embed-resources: true
    highlight-style: github
```

## File creation

### Notebooks

Create `.qmd` notebooks from your template with `make_notebook()`:

```r
make_notebook("exploration")
make_notebook("modeling", subdir = "analysis")
```

### Presentations

Create RevealJS slide decks with `make_presentation()`:

```r
make_presentation("quarterly-review")
```

## Document rendering

### R console

```r
# Render a single document with Quarto
quarto::quarto_render("notebooks/analysis.qmd")

# Render and record in framework.db
save_notebook("notebooks/analysis.qmd")
```

### Terminal

```bash
# Render a single file
quarto render notebooks/analysis.qmd

# Render the entire Quarto project
quarto render
```

## Format defaults

### HTML documents

```yaml
format:
  html:
    theme: default
    embed-resources: true
    toc: true
    toc-depth: 3
    code-fold: false
    code-tools: false
    highlight-style: github
```

### RevealJS presentations

```yaml
format:
  revealjs:
    theme: default
    incremental: false
    slide-number: true
    transition: slide
    background-transition: fade
    controls: true
    progress: true
    center: true
    highlight-style: github
```

## Configuration regeneration

Run `quarto_regenerate(".", overwrite = TRUE)` to rewrite `_quarto.yml` files from current settings. Existing `_quarto.yml` files are backed up to `.framework/backups/<timestamp>/` before replacement.
