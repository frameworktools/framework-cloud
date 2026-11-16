---
id: new-project
title: New projects
section: getting_started
position: 5
description: 'Create new Framework projects from R'
---
## Project setup

After configuring defaults on Framework Cloud, create projects from the R console. The local `gui()` interface is retired.

### R console

Run `new_project()` interactively to enter a project name and target path:

```r
framework::new_project()
```

Or pass the project name and directory directly:

```r
new_project("my-analysis", "~/code/my-analysis")
```

## Project types

Framework provides a constructor function for each built-in project type:

### Standard project

Default structure for research and data analysis pipelines:

```r
new_project("my-analysis")
```

### Sensitive data project

Separated `private/` and `public/` input and output trees with stricter `.gitignore` rules:

```r
new_project_sensitive("medical-study")
```

### Presentation

Single-deck structure centered on `presentation.qmd`:

```r
new_presentation("quarterly-review")
```

### Course

Teaching layout with directories for slides, assignments, readings, and course documents:

```r
new_course("stats-101")
```

## Generated files

Project creation writes these files and directories:

1. Configured project directories from your defaults.
2. `settings.yml` populated with author metadata, packages, and directory mappings.
3. `.gitignore` matched to the chosen project type.
4. Editor workspace files for VS Code, Positron, or RStudio.
5. AI assistant context files such as `CLAUDE.md` when enabled.
6. An initialized Git repository and initial commit when `git config user.name` and `git config user.email` are set (otherwise the initial commit is skipped with a notice).
