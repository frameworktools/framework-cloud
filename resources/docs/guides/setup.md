---
id: setup
title: Setup
section: getting_started
position: 2
description: 'Configure global Framework defaults on Framework Cloud'
---
## Global defaults

The local setup interface is retired. Configure global defaults on Framework Cloud under **Settings > Project defaults** after signing in from R:

```r
framework::cloud_login()
```

Settings saved there become your global defaults when creating projects with `new_project()`. Individual projects can override any global default in their own `settings.yml`.

## Settings sections

- **Basics**: default projects directory, editor workspace files, notebook format, and author details.
- **Project structure**: default directories for notebooks, scripts, inputs, and outputs.
- **Packages**: default R packages and `renv` options.
- **Quarto**: HTML document and RevealJS slide defaults.
- **Git and hooks**: Git initialization and pre-commit hooks.
- **AI assistants**: context files for Claude Code, GitHub Copilot, and multi-agent tools.
- **Scaffold behavior**: random seed, `ggplot2` theme, and function sourcing.
- **Templates**: starter templates for notebooks, scripts, and slides.
- **Connections**: database and S3 object-storage profiles.
- **Environment defaults**: starter `.env` variables.

## Settings storage

Framework stores local global settings in the folder returned by `fw_config_dir()` (by default `tools::R_user_dir("framework", "config")`):

```
<config folder>/
├── templates/             # Starter template files
├── settings.yml           # Global defaults
└── projects.yml           # Local project registry
```

- **templates/**: customized starter templates.
- **settings.yml**: global default settings.
- **projects.yml**: local project paths used by `project_list()`.

Framework creates `settings.yml` and `projects.yml` on first use. You can edit these files directly.
