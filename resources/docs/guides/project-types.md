---
id: project-types
title: Project types
section: project_types
position: 10
description: 'Built-in Framework project layouts for research, sensitive data, courses, and slides'
---
## Overview

Framework includes four built-in project types tailored to distinct workflows:

| Type | Target workflow |
|---|---|
| **Standard Project** | General data analysis, pipelines, and reporting |
| **Privacy Sensitive Project** | Studies requiring separated `private/` and `public/` directories |
| **Course/Teaching** | Academic courses with lecture slides, readings, and assignments |
| **Presentation** | Standalone RevealJS slide decks and reports |

## Project layouts

### Standard project

General layout with dedicated input, notebook, script, function, and output directories:

```
my-analysis/
├── docs/
├── functions/
├── inputs/
│   ├── raw/
│   ├── intermediate/
│   └── final/
├── logs/
├── notebooks/
├── outputs/
│   └── notebooks/
├── scratch/
├── scripts/
└── settings.yml
```

### Privacy sensitive project

Isolated `private/` and `public/` subdirectories with `.gitignore` exclusions on private paths:

```
my-study/
├── docs/
├── functions/
├── inputs/
│   ├── private/
│   │   ├── raw/
│   │   ├── intermediate/
│   │   └── final/
│   └── public/
│       ├── raw/
│       ├── intermediate/
│       └── final/
├── logs/
├── notebooks/
├── outputs/
│   └── private/
│       └── notebooks/
├── scratch/
├── scripts/
└── settings.yml
```

### Course

Academic teaching layout for lecture decks, assignments, readings, and syllabi:

```
my-course/
├── assignments/
├── cache/
├── course_docs/
├── data/
├── functions/
├── modules/
├── readings/
├── scripts/
├── slides/
└── settings.yml
```

### Presentation

Single-document layout centered on `presentation.qmd`:

```
my-presentation/
├── cache/
├── data/
├── functions/
├── outputs/
├── scripts/
├── presentation.qmd
└── settings.yml
```

## Project creation

### Graphical interface

Open the setup application and select **New Project**:

```r
framework::setup()
```

### R console

```r
# Standard project
new_project("sales-analysis", "~/projects/sales-analysis")

# Privacy-sensitive project
new_project_sensitive("medical-study", "~/projects/medical")

# Course
new_course("data-science-101", "~/teaching/ds101")

# Presentation
new_presentation("q4-report", "~/presentations/q4")
```

## Shared components

Every project type includes:

- `settings.yml` configuration.
- Git initialization and type-specific `.gitignore` templates.
- Quarto configuration files.
- Optional AI assistant context files (`CLAUDE.md` and `AGENTS.md`).
