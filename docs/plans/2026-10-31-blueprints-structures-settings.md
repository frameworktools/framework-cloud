# Blueprints, project structures, features, package sets, skills, and settings

Status: agreed in discussion, awaiting go-ahead, 2026-10-31.

## Problem

Project types, blueprints, and facets overlap. A project type (project,
project_sensitive, course, presentation, bare) is defined in the R package as a
folder structure, an AGENTS.md, and default skills. The cloud wraps each type in a
blueprint and lets facets add to it. A facet can set anything at once
(folders, packages, files, skills, and settings), so facets can overlap, and the
result of combining them is hard to predict.

## Model

A blueprint is the only umbrella concept. Everything else is a piece with one
job.

**Components** have content. People write, version, share, and copy them.

- **Project structure:** the folders and Framework's core files, such as
  `settings.yml`, `scaffold.R`, and AGENTS.md. Nothing optional.
- **Project feature:** an optional tool that brings its own files, folders,
  packages, and settings. Examples: Quarto, Quarto manuscript, Quarto slides,
  targets, renv, uv, and Jupyter.
- **Package set:** a named list of packages for one language, such as
  "tidyverse analysis" or "survey statistics". Packages only, no files.
- **Skill:** one SKILL.md. A **skill set** is a named group of skills.

**Settings** are switches with values. Each user has defaults (Settings >
Defaults), and each project has its own values.

- **Language:** R, Python, or Stata.
- **AI assistants:** on or off, and which tools to set up (Claude Code, Codex,
  Copilot). When AI is off, no skills are installed.
- **Sensitive data:** on or off.
- **Git and seed on scaffold:** on or off.

The rule between the two: if turning something on adds files or packages, it is
a feature. If it only changes a value, it is a setting.

A **blueprint** is a saved, shareable set of choices: one project structure, any
number of features, package sets, skills and skill sets, and settings. Settings
in a blueprint override the user's defaults. Blueprints are versioned snapshots
that pin the versions of their pieces, and a project records the blueprint
version it came from.

## Features

| Feature | Brings | Language | Needs |
|---|---|---|---|
| Quarto | `_quarto.yml`, render folders | R, Python | |
| Quarto manuscript | Manuscript config and folder | R, Python | Quarto |
| Quarto slides | Slides config and folder | R, Python | Quarto |
| targets | `_targets.R`, the targets package | R | |
| renv | renv setup and `renv.lock` | R | |
| uv | `pyproject.toml` and a lock file | Python | |
| Jupyter | Notebook folder and kernel setup (IRkernel for R) | R, Python | |

Quarto stops being assumed. Framework's R blueprints include it by default, so
R users see no change, and Python projects can choose Jupyter or neither.
Stata projects have no features: fw scaffolds Stata with a project structure,
package-free settings, and skills only.

Rules that keep features from becoming facets again:

- **A feature only adds.** It adds its own files, folders, packages, and
  settings. It never renames or removes anything in the project structure.
- **A feature declares its language and its dependencies.** The launch page
  shows only features for the project's language and adds required ones.
- **Two features cannot own the same file.** Features that would are marked as
  conflicting and cannot be chosen together.
- **Framework writes the features at first.** Sharing user-written features
  waits until these rules have held up in practice.

## Creating a project

People either start from a blueprint or pick the pieces.

1. **Start:** choose a blueprint, or "Start from scratch".
2. **Project structure:** the blueprint's structure is preselected. Change it if needed.
3. **Features:** turn features on or off, filtered by language.
4. **Packages:** add or remove package sets and single packages.
5. **AI:** turn AI on or off. Pick skill sets and add single skills.
6. **Settings:** filled from the blueprint, then the user's defaults. Change any of them.
7. **Create.** Optionally, "Save these choices as a blueprint".

## Rules between pieces and settings

- **Sensitive data and project structures.** Turning sensitive data on splits
  data and output folders by a fixed convention: `inputs/raw` becomes
  `inputs/private/raw` and `inputs/public/raw`, and the same for intermediate,
  final, and outputs. A project structure may instead declare its own sensitive
  variant. Sensitive data also adds the git ignores, the private-path warnings,
  and the sensitive-data skill when AI is on.
- **Skills that need packages.** A skill or skill set may list the packages it
  needs. The launch page adds them, and the person can remove them.
- **AI off.** No skills, no AGENTS.md skill table, and no assistant stub files.

## What happens to today's pieces

| Today | Becomes |
|---|---|
| Project types (5) | Five Framework blueprints |
| Base facets (5) | Five Framework project structures, with Quarto moved out into a feature |
| `restricted-data` facet | Sensitive data setting |
| `claude-code`, `codex`, `copilot` facets | AI assistants setting |
| `renv` facet | renv feature |
| `python` facet | Language setting, plus Python features |
| `targets-pipeline` facet | targets feature |
| `manuscript`, `slides` facets | Quarto manuscript and Quarto slides features |
| User facets | Migrated to the nearest piece. Folders become a project structure, packages a package set |
| Skills | Unchanged, plus skill sets |

The Facets page, facet import from GitHub, and facet publishing are removed.
The sidebar's Framework section lists Blueprints, Structures, Features, Package
sets, and Skills.

## Behavior keyed on settings, not type names

The R package, Python package, and fw branch on `project_type` today. For
example, `save_figure()` checks for `project_sensitive`. They should check
settings, features, and the project structure instead:

- `security.sensitive` decides private and public folders and warnings.
- The directories in `settings.yml` decide where outputs go.
- Quarto behavior, such as render directories, applies only when the Quarto
  feature is on.
- `type` stays as a label and an alias. `new(type = "project")` keeps working
  and means "Framework's standard blueprint".

## Spec and API

- The project spec (the layers that R, Python, and fw read) keeps its shape.
  The cloud resolves a blueprint into the same layer list: project structure,
  features, package sets, skills, then settings. The clients need only the
  changes above.
- v1 clients (R 1.1.0) keep receiving the flattened blueprint as they do now.
- New endpoints list project structures, features, package sets, and skill sets
  for `fw` and agents.

## Order of work

1. Cloud data model: project structures, features, package sets, skill sets,
   and blueprint versions that reference them. Migrate facets and types. The
   spec output is unchanged.
2. Cloud pages: the library pages and the new launch flow.
3. R package: key behavior on settings and features rather than type names,
   make Quarto a feature, and add features, skill sets, and package sets to
   `new()`. Python and fw follow the same change.
4. Remove facets from the cloud once nothing reads them.

## Decisions

- Stata projects have no features.
- Jupyter is offered for both R (through IRkernel) and Python.
- Blueprints pin the versions of their pieces.
- Each package set belongs to one language.
- Features are written by Framework until the rules have held up.
