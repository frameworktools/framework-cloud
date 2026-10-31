# Blueprints, layouts, package sets, skills, and settings

Status: draft for review, 2026-10-31.

## Problem

Project types, blueprints, and facets overlap. A project type (project,
project_sensitive, course, presentation, bare) is defined in the R package as a
folder layout, an AGENTS.md, and default skills. The cloud wraps each type in a
blueprint and lets facets add to it. A facet can set anything at once
(folders, packages, files, skills, and settings), so facets can overlap, and the
result of combining them is hard to predict.

## Model

There are two kinds of thing.

**Components** have content. People write, version, share, and copy them.

- **Layout:** the folder structure, render directories, and starter files such
  as AGENTS.md.
- **Package set:** a named group of packages, such as "tidyverse analysis" or
  "survey statistics".
- **Skill:** one SKILL.md. A **skill set** is a named group of skills.

**Settings** are switches with values. Each user has defaults (Settings >
Defaults), and each project has its own values.

- **AI assistants:** on or off, and which tools to set up (Claude Code, Codex,
  Copilot). When AI is off, no skills are installed.
- **Sensitive data:** on or off.
- **Quarto:** notebook format and render directories.
- **Reproducibility:** git, renv, and seed on scaffold.
- **Languages:** the main language, and whether Python runs alongside R.

A **blueprint** is a saved, shareable set of choices: one layout, any number
of package sets, skills and skill sets, and settings. Settings in a blueprint
override the user's defaults. Blueprints are versioned snapshots, and a project
records the blueprint version it came from.

Each component controls only its own part of a project, so no component
overrides another. This removes the merge rules that facets needed.

## Creating a project

People either start from a blueprint or pick the pieces.

1. **Start:** choose a blueprint, or "Start from scratch".
2. **Layout:** the blueprint's layout is preselected. Change it if needed.
3. **Packages:** add or remove package sets and single packages.
4. **AI:** turn AI on or off. Pick skill sets and add single skills.
5. **Settings:** filled from the blueprint, then the user's defaults. Change any of them.
6. **Create.** Optionally, "Save these choices as a blueprint".

## Rules between components and settings

- **Sensitive data and layouts.** Turning sensitive data on splits data and
  output folders by a fixed convention: `inputs/raw` becomes
  `inputs/private/raw` and `inputs/public/raw`, and the same for intermediate,
  final, and outputs. A layout may instead declare its own sensitive variant.
  Sensitive data also adds the git ignores, the private-path warnings, and the
  sensitive-data skill when AI is on.
- **Skills that need packages.** A skill or skill set may list the packages it
  needs. The launch page adds them, and the person can remove them.
- **AI off.** No skills, no AGENTS.md skill table, and no assistant stub files.

## What happens to today's pieces

| Today | Becomes |
|---|---|
| Project types (5) | Five Framework blueprints, each with a Framework layout |
| Base facets (5) | Five Framework layouts |
| `restricted-data` facet | Sensitive data setting |
| `claude-code`, `codex`, `copilot` facets | AI assistants setting |
| `renv` facet | Reproducibility setting (renv already exists in Defaults) |
| `python` facet | Languages setting |
| `targets-pipeline` facet | Package set with a starter `_targets.R` (see open questions) |
| `manuscript`, `slides` facets | Layouts, or folders added to a layout (see open questions) |
| User facets | Migrated to the nearest component. Anything that does not fit becomes a layout |
| Skills | Unchanged, plus skill sets |

The Facets page, facet import from GitHub, and facet publishing are removed.
The sidebar's Framework section lists Blueprints, Layouts, Package sets, and
Skills.

## Behavior keyed on settings, not type names

The R package, Python package, and fw branch on `project_type` today. For
example, `save_figure()` checks for `project_sensitive`. They should check
settings and the layout instead:

- `security.sensitive` decides private and public folders and warnings.
- The directories in `settings.yml` decide where outputs go.
- `type` stays as a label and an alias. `new(type = "project")` keeps working
  and means "Framework's standard blueprint".

## Spec and API

- The project spec (the layers that R, Python, and fw read) keeps its shape.
  The cloud resolves a blueprint into the same layer list: layout, package sets,
  skills, then settings. The clients need only the type-name changes above.
- v1 clients (R 1.1.0) keep receiving the flattened blueprint as they do now.
- New endpoints list layouts, package sets, and skill sets for `fw` and agents.

## Order of work

1. Cloud data model: layouts, package sets, skill sets, and blueprint versions
   that reference them. Migrate facets and types. The spec output is unchanged.
2. Cloud pages: the library pages and the new launch flow.
3. R package: key behavior on settings rather than type names, and add skill
   sets and package sets to `new()`. Python and fw follow the same change.
4. Remove facets from the cloud once nothing reads them.

## Open questions

1. Can a package set include starter files, such as `_targets.R` for targets? If
   not, targets becomes a layout or a setting.
2. Are manuscript and slides layouts of their own, or folders a person adds to
   any layout?
3. Should a blueprint pin component versions, or follow the latest? Pinning
   matches how blueprints work now and keeps projects reproducible.
4. Do package sets belong to one language, or can they mix R and Python?
