---
id: templates
title: Templates
section: features
position: 6
description: 'Customize starter templates for notebooks, scripts, slides, and gitignore files'
---
## Overview

Framework generates new notebooks, scripts, and presentations from customizable templates.

## Template functions

Read, edit, or restore templates with `read_framework_template()`, `write_framework_template()`, and `reset_framework_template()`:

```r
read_framework_template("notebook")
```

## Template variables

Framework substitutes these placeholders when generating files:

| Variable | Description |
|---|---|
| `{{title}}` | Document or project title |
| `{{author}}` | Author name |
| `{{email}}` | Author email |
| `{{affiliation}}` | Author affiliation |
| `{{date}}` | Creation date |

Both spaced (`{{ title }}`) and unspaced (`{{title}}`) placeholders are supported.

## Template types

- **notebook**: Quarto notebooks (`.qmd`).
- **script**: R scripts (`.R`).
- **presentation**: Quarto RevealJS slide decks.
- **gitignore**: `.gitignore` rules per project type (kept for older settings; project creation does not read it).
- **ai_canonical**: AI assistant instruction files (`CLAUDE.md` and `AGENTS.md`) (kept for older settings; project creation does not read it).

## Template storage

Custom templates reside in `templates/` inside `fw_config_dir()`. Framework writes a template to this directory on first edit:

```
<fw_config_dir()>/
└── templates/
    ├── notebook-default.qmd
    ├── script-default.R
    ├── notebook-revealjs.qmd
    ├── gitignore-project
    ├── gitignore-sensitive
    ├── gitignore-course
    ├── gitignore-presentation
    ├── ai-context.project.md
    ├── ai-context.project_sensitive.md
    └── ai-context.course.md
```

## Template customization

### Notebook template

Edit `templates/notebook-default.qmd` inside `fw_config_dir()`:

````qmd
---
title: "{{ title }}"
author: "{{ author }}"
date: "{{ date }}"
format:
  html:
    toc: true
    code-fold: true
---

```{r}
#| label: setup
library(framework)
scaffold()
```

## Overview

Analysis notes.
````

### Script template

Edit `templates/script-default.R` inside `fw_config_dir()`:

```r
# {{ title }}
# Author: {{ author }}
# Created: {{ date }}

library(framework)
scaffold()
```

## File generation

Generator helpers apply your active templates automatically:

```r
make_notebook("01-exploration")
make_script("process-data")
make_presentation("quarterly-review")
```

## Default restoration

Delete a customized file from `templates/` inside `fw_config_dir()` or run `reset_framework_template()` to restore the built-in template.
