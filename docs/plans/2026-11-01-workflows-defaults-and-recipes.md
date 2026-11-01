# Workflows, language defaults, and blueprints as recipes

Status: agreed 2026-11-01, in progress. Supersedes the precedence rules in
`2026-10-31-blueprints-structures-settings.md` ("settings in a blueprint
override the user's defaults"). The piece model in that plan stands.

## Goals

- New people answer six questions and get a default blueprint that matches
  how they work: language, typical project, workflow, editors, Git, and AI
  agents.
- Cloud, R, Python, and `fw` resolve a project the same way, online and
  offline, and the preview shows exactly what creation writes.
- A project keeps the configuration it was created with.
- Blueprints are versioned, publishable recipes. System blueprints use the
  same model as future community ones, so a marketplace can come later
  without a second model.

We are not launched. Development data is disposable, and there is no
compatibility layer for unreleased behavior.

## Model

A project is resolved from these parts.

- **Project structure** (facet kind `structure`): folders, render folders,
  the settings layout, and whether the project is sensitive. Unchanged.
- **Workflow** (facet kind `workflow`, new): how notebooks are written. One
  of `quarto`, `jupyter`, or `scripts`. A workflow turns tools on and sets
  the notebook format. Its `language_layers` add per-language packages, such
  as IRkernel or ipykernel for Jupyter.
- **Pieces**: tools, package sets, and skill sets. Unchanged.
- **Blueprint**: a recipe that pins one structure version and any pieces, and
  declares which languages and workflows it supports.
- **Personal defaults**: the account's choices, stored sparsely (only what
  the person set).
- **Creation choices**: what the person picks when creating a project.

### Workflows and notebook formats

| Workflow | Tools it turns on | Notebook formats | Languages |
|---|---|---|---|
| Quarto | quarto | quarto, or rmarkdown for R | R, Python |
| Jupyter | jupyter, quarto (rendering and publishing) | jupyter | R, Python |
| Scripts only | none | script | R, Python, Stata |

The notebook format is derived from the workflow and cannot contradict it.
R Markdown is a variant of the Quarto workflow for R: the person picks the
Quarto workflow and, under it, "Write R Markdown (.Rmd)". It is stored as
`notebook_format: rmarkdown` beside `workflow: quarto`, and it is rejected
with any other workflow or language.

A Jupyter project keeps Quarto, so `save_notebook()` and publishing work. The
workflow decides the authoring format, and Quarto rendering stays available.
A Scripts-only project has no notebook tools. Its starter scripts come from
the blueprint (see Blueprints), so Framework does not impose numbered scripts
on every project.

Stata supports only Scripts only, which matches what `fw` writes.

Initial personal defaults: Quarto for R, Jupyter for Python, Scripts only for
Stata. Workflow preferences are stored per language, so choosing Jupyter for
Python leaves R's Quarto alone.

### Blueprint compatibility

Each blueprint version declares structured, validated fields:

- `languages`: the languages it supports.
- `workflows`: the workflows it supports, and `default_workflow` per
  language when the person's preference isn't supported.
- `requires_tools`: tools the blueprint needs, such as `quarto_slides` for a
  Quarto course.

Compatibility comes from these fields only. A Quarto course declares
`workflows: [quarto]`; a Jupyter course can be published later with
`workflows: [jupyter]`. Framework's structures stay workflow-neutral, and
the system blueprints declare what they support:

| Blueprint | Languages | Workflows |
|---|---|---|
| Standard | R, Python, Stata | Quarto, Jupyter, Scripts only |
| Privacy Sensitive | R, Python, Stata | Quarto, Jupyter, Scripts only |
| Presentation | R, Python | Quarto (requires quarto_slides) |
| Course | R, Python | Quarto |
| Bare | R, Python, Stata | Quarto, Jupyter, Scripts only |

### One precedence policy

Every setting resolves with the same order, highest first:

1. **Blueprint requirement.** A blueprint can mark values as required, such
   as a course's Quarto. Nothing overrides a requirement. A creation choice
   that conflicts with one is an error shown before anything is created. A
   personal default that conflicts is set aside, and the preview says
   "Required by the blueprint".
2. **Creation choice.** What the person chose for this project.
3. **Personal default.** Only values the person set. Values they never set
   are absent and fall through.
4. **Blueprint default.** The blueprint's optional values.
5. **Workflow.**
6. **Project structure.**
7. **Package built-ins.** R's, Python's, or `fw`'s own defaults.

Some settings belong to one level only, which keeps the policy honest:

- **Structure-owned:** folders, render folders, the settings layout, and
  sensitive data. Only the structure and blueprint requirements set them. A
  creation choice may turn sensitive data on, never off when the blueprint
  requires it.
- **Person-owned:** editor files and AI agents (which agents, and whether
  agent files are added). Blueprints cannot set them, so a blueprint can
  never grant agent permissions.
- **Everything else** follows the full order: workflow and notebook format,
  tools, packages, Git and hooks, scaffold behavior (seed, theme, functions),
  and Quarto output settings.

Packages are additive: the blueprint's packages, plus the person's default
packages, minus packages removed at creation. Required packages cannot be
removed.

The preview labels each value with its source: required, you chose, your
default, blueprint, or Framework.

### Resolution in every client

When connected, R `new()`, Python `new()`, and `fw new` send their choices
to `POST /api/v2/resolve` and build from the returned envelope. The cloud
is the only resolver of choices; clients merge layers with the shared merge
rules they already implement.

Offline creation stays supported:

- On each successful connection, clients cache the resolved default envelope
  for each language, with its settings revision and time, in the Framework
  config folder.
- Offline, clients build from the cache and say so: "Using your Framework
  Cloud defaults from 2026-11-01".
- With no cache, they build from package built-ins only after saying so. In
  an interactive session they ask first. In a non-interactive session they
  stop unless the caller passes `offline = TRUE` (R), `offline=True`
  (Python), or `--offline` (`fw`).

Clients never silently fall back to a different configuration.

The contract, shared by R, Python, and `fw`:

- **Cache file:** `<Framework config folder>/cache/defaults/<host[:port]>/<language>.json`,
  holding `{"fetched_at": RFC 3339, "defaults_revision": "...", "envelope": {...}}`.
  Only the default envelope is cached (choices are the language only).
  Projects with explicit pieces need a connection.
- **Revision:** `POST /api/v2/resolve` and `GET /api/v2/defaults` send a
  `Framework-Defaults-Revision` header. It is opaque; compare for equality.
- **Defaults request:** `POST /api/v2/resolve` with only `{"language": ...}`
  resolves from the person's defaults. When their default blueprint doesn't
  support the language, the cloud uses `framework/project` and says so in
  `notices`.
- **Offline** means network errors, timeouts, and 5xx responses. 401 and 403
  are authentication errors.
- **Not signed in:** build from package built-ins with no prompt.
- **Paths:** `:` in `host[:port]` becomes `_` on every platform, such as
  `cache/defaults/localhost_8000/stata.json`. Each client writes its own
  config folder and reads the newest cache among the folders it knows.
- **Names:** the envelope always has a valid name ("Untitled project" when
  the request has none), and clients put the project's name in before
  validating.
- **Notices:** one `Framework-Notice` response header per notice, in plain
  text. The body is always exactly a layers envelope.
- **Handoff:** `fw new` passes choices (and `--offline`) to R's or Python's
  `new()`, so each language has one resolving client. Layers files remain
  for explicit envelopes.

### Frozen projects

A project stores its resolved envelope (`projects.resolved`) at creation,
including the personal layer. Later requests for the project's layers return
the stored envelope. Changing defaults affects future projects only. A
project created offline registers its resolved spec when it next connects,
as it does now.

### Shared fixtures

The R package's `inst/conformance/` gains two folders beside `merge/`:

- `resolve/`: account defaults and creation choices in, envelope out. The
  cloud's resolver is checked against these.
- `create/`: a resolved spec in, the files and folders a project gets out,
  per language. R, Python, and `fw` check their creation against these, and
  the cloud checks its preview tree against them.

R generates the expected outputs; Python, `fw`, and the cloud pin copies.

## Blueprints as publishable recipes

Fields needed now, so a marketplace can come later without a new model:

| Field | Where | Notes |
|---|---|---|
| Owner and namespace | `blueprints.user_id`, `key`; handle `owner/key` | System blueprints use `framework/`. |
| Name, description | `blueprints` | |
| Attribution | `forked_from_id` | Shown as "based on". |
| License | `blueprints.license` | SPDX id; required to publish publicly. |
| Tags | `blueprints.tags` | Discovery only. Resolution ignores them. |
| Status | `blueprints.status`: draft or published | Drafts have no published version. |
| Visibility | `blueprints.visibility`: private, unlisted, public | Exists. |
| Versions | `blueprint_versions` | Immutable once published. |
| Pins | `base_version_id`, facet version ids | Exists. |
| Compatibility | `blueprint_versions.languages`, `workflows`, `default_workflow`, `requires_tools` | Validated at publish and resolve. |
| Requirements and defaults | `blueprint_versions.layer` split into `requires` and `defaults` | Precedence levels 1 and 4. |

System blueprints are ordinary rows with `user_id` null and the `framework`
namespace.

### Trust

Blueprints from other people are untrusted content. Using one never runs
code and never grants permissions:

- Blueprints can add text files and templates, packages, folders, tools from
  Framework's catalog, and settings. They cannot add scripts that run on
  their own. Files at these paths are rejected for blueprints the person
  doesn't own: `.Rprofile`, `scaffold.R`, `scaffold.py`, `sitecustomize.py`,
  `.envrc`, `.github/workflows/`, `.vscode/tasks.json`, `.claude/`,
  `.agents/`, and `.git/`.
- Agents and editor files are person-owned (see the precedence policy).
- Git hooks come from Framework's own named hooks only.
- Packages install when the person runs `scaffold()`, and the preview lists
  every package and its source before creation.

## Guided setup

`/welcome` after sign-up, and "Run guided setup" on Project defaults. It can
be skipped. Six questions, each explained in plain language:

1. Language: R, Python, or Stata.
2. Typical project: Standard, Privacy Sensitive, Presentation, Course, or
   Bare, filtered by the language.
3. Workflow, filtered by the language and blueprint, preselected from the
   language. Skipped for Stata.
4. Editors, several allowed, preselected from the language. Skipped for
   Stata.
5. Git, on by default.
6. AI agents, yes or no; only on yes, which ones.

A "Your project defaults" summary follows, built from the same preview as
the Blueprint page, with a way back to each question. Saving writes real
personal defaults, so every creation path honors them. The account records
when setup was completed or skipped.

The Blueprint page follows the same order: language, compatible workflows,
structures, then the summary.

## Disposable data and migration

- Development databases are reseeded. Settings documents and
  `users.project_defaults` are rewritten into the new sparse shape; values a
  person explicitly saved are kept where they map cleanly (notebook format
  becomes R's workflow; Python and Stata start from the new defaults).
- Existing development projects get `resolved` filled from their current
  layers once, then freeze.
- The synced settings document gains `defaults.workflows` (per language) and
  keeps `defaults.notebook_format` as R's derived value for older clients
  until the packages ship.

## Implementation order

1. **Cloud foundations:** workflow facets; blueprint compatibility, license,
   tags, and status fields; sparse personal defaults with per-language
   workflows; the precedence policy in the resolver with sources in the
   preview; frozen `resolved` envelopes; trust checks.
2. **Packages:** the `script` notebook format and Scripts-only workflow in R
   and Python; `resolve/` and `create/` fixtures; `new()` through the cloud
   resolver with the cached envelope and explicit offline mode; `fw new`
   the same.
3. **Cloud interface:** the Blueprint page in language, workflow, structure
   order; the Default format page becomes per-language workflows; guided
   setup.
4. **Verification:** fixtures pass in all four repos; the preview tree
   matches `create/` for every system blueprint, language, and workflow.

## Decisions taken

- Workflow is a piece composed with a structure.
- Editor and agents are person-owned.
- Scripts only is a workflow; blueprints supply starter scripts.
- Signed-in creation goes through the cloud; offline uses a cache or an
  explicit opt-in.
- Projects freeze their resolved envelope at creation.
