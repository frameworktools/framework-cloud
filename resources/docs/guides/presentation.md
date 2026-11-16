---
id: presentation
title: Presentation
section: project_types
position: 14
description: 'Single-deck project structure for Quarto RevealJS slides and standalone reports'
---
## Overview

The **Presentation** project type provides a compact layout centered on `presentation.qmd` for conference talks, seminars, and standalone reports.

## Directory structure

```
my-presentation/
├── cache/               # Cached computations
├── data/                # Datasets
├── functions/           # Helper functions
├── outputs/             # Saved figures and tables
├── scripts/             # Data preparation scripts
├── presentation.qmd     # Primary Quarto document
└── settings.yml         # Project configuration
```

## Project creation

Create a presentation project with `new_presentation()`:

```r
new_presentation("quarterly-report", "~/presentations/q4")
```

## Configuration

Example `settings.yml`:

```yaml
default:
  project_type: presentation

  directories:
    data: data

  packages:
    - name: dplyr
      auto_attach: true
    - name: ggplot2
      auto_attach: true
```

## Workflow

### Environment initialization

Load project packages and settings inside your setup chunk:

```r
library(framework)
scaffold()
```

### Slide authoring

Edit `presentation.qmd`:

````qmd
---
title: "Q4 Sales Analysis"
author: "Your Name"
format: revealjs
---

## Overview

Key findings from Q4 sales data.

## Revenue Growth

```{r}
sales_plot
```
````

### Document rendering

Render from the terminal:

```bash
quarto render presentation.qmd
```

Or render from R:

```r
quarto::quarto_render("presentation.qmd")
```

## Output formats

Set `format` in the document YAML frontmatter:

### RevealJS slides
```yaml
format: revealjs
```

### HTML document
```yaml
format: html
```

### PDF report
```yaml
format: pdf
```

### Word document
```yaml
format: docx
```

## Conventions

### Scope

Use **Presentation** for single-deliverable slide decks or reports. Use **Standard Project** when an analysis spans multiple notebooks, staged datasets, and saved model artifacts.

### Helper functions

Use `functions/` to move plotting and formatting helpers out of slide chunks:

```r
# functions/plots.R
create_revenue_chart <- function(data) {
  ggplot(data, aes(x = month, y = revenue)) +
    geom_col(fill = "steelblue") +
    theme_minimal()
}
```

### Cached chunks

Wrap slow computations in `cache_remember()` to keep slide renders responsive:

```r
summary_stats <- cache_remember("summary", {
  expensive_calculations()
})
```
