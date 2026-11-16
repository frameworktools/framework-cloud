# Projects and blueprints

## Blueprints

A blueprint is a reusable set of configuration choices for new projects. Each blueprint combines these components:

- **Project structure.** Directories and core files, including `settings.yml`, `.gitignore`, and agent context files.
- **Tools.** Optional integrations that add files, directories, and packages: Quarto, Quarto manuscript, Quarto slides, Jupyter, targets, renv, and uv. Stata projects have no tools.
- **Package sets.** Named package lists for R, Python, or Stata. Multiple sets can be combined and shared packages install once.
- **Skills.** Coding agent instructions selected individually or in skill sets.
- **Settings.** Sensitive-data handling, notebook format, Quarto HTML and slide options, scaffold options, templates, editor support, AI agents, Git hooks, connections, and publishing destinations.

Framework includes five general blueprints: Standard, Privacy sensitive, Presentation, Course, and Bare. It also includes four application blueprints:

| Blueprint | Language | Starter files |
|---|---|---|
| Shiny app | R | `app.R`, `R/`, `www/`, `tests/` |
| Plumber API | R | `plumber.R`, `run.R`, `R/`, `tests/` |
| Shiny for Python | Python | `app.py`, `www/`, `tests/` |
| FastAPI app | Python | `app/main.py`, `tests/test_main.py` |

Each application blueprint includes the required package set and runnable starter files.

**Blueprints** lists built-in blueprints, public community blueprints, and your own blueprints. **Project structures**, **Package sets**, **Skill sets**, and **Skills** list individual components that you can copy or create.

### Defaults

**Settings > Project defaults** sets three account-wide defaults for new projects:

- **Default language.** Selected when a project is created without an explicit language, such as from an AI agent.
- **Projects folder.** Default local parent directory suggested by R, Python, and `fw` when creating projects. Leaving it blank uses the current working directory.
- **Blueprints by language.** Default blueprints for R, Python, and Stata projects. Choose from built-in blueprints and your own compatible blueprints.

Project structure, notebook format, Quarto settings, reproducibility tools, packages, scaffold behavior, templates, editor files, AI agents, skills, Git hooks, connections, and publishing live on blueprints and on the **Projects > New project** tabs. To change those defaults for future projects, create or edit a blueprint at **Blueprints** (`/blueprints`) and select it under **Blueprints by language**. You can also open **Guided setup** (`/welcome`) from **Settings > Project defaults** to generate a personal blueprint from six questions.

#### Default precedence

Every creation path resolves projects with the same rules: **Projects > New project**, `new_project()` in R and Python, and `fw new`. Connected R, Python, and `fw` clients request the resolved layer envelope from Framework Cloud.

A new project starts from the requested blueprint or from your default blueprint for that language. Settings merge from highest to lowest priority:

1. **Blueprint requirements**, such as Quarto for the Course blueprint. Conflicting selections are rejected before project creation.
2. **Project choices** set on the **Projects > New project** tabs or passed as creation options.
3. **Blueprint values** stored on the selected blueprint version.
4. **Workflow defaults**, **project structure defaults**, and **Framework package defaults**.

Sensitive-data mode comes from the project structure, such as Privacy sensitive. Turning AI agents off omits agent context files and skill sets. Disabling Git omits pre-commit safety hooks.

Existing projects keep the resolved configuration stored when they were created. Updating **Settings > Project defaults** or saving a new blueprint version affects only future projects.

Connected R, Python, and `fw` clients cache your resolved defaults locally. When offline, they build from the cached copy and report its date. Without a cached copy, interactive sessions prompt before continuing and non-interactive scripts stop unless `offline = TRUE` (R), `offline=True` (Python), or `--offline` (`fw`) is set.

#### Notebook workflows

Blueprints and **Projects > New project** configure the notebook format on the **Notebook format** tab:

- **Quarto:** `make_notebook()` creates Quarto documents (`.qmd`).
- **R Markdown** (R only): `make_notebook()` creates R Markdown documents (`.Rmd`) rendered with Quarto.
- **Jupyter:** `make_notebook()` creates Jupyter notebooks (`.ipynb`) using `IRkernel` in R or `ipykernel` in Python, with Quarto included for rendering and publishing.
- **Scripts only:** disables notebooks so `make_notebook()` creates scripts. Stata projects always use scripts.

Projects can still contain other formats alongside the default, such as `make_notebook("analysis.ipynb")` inside a Quarto project. Required workflow tools cannot be disabled.

`save_notebook()` renders a Quarto or Jupyter notebook into the structure's render directory, such as `outputs/notebooks/`, using the outputs saved in the notebook. Run the notebook before rendering. The **Notebook output checks** Git hook blocks commits of `.ipynb` files that still contain cell outputs.

Root `_quarto.yml` holds shared HTML and RevealJS slide settings. Directories with distinct rendering rules, such as course slides, define their own `_metadata.yml`.

#### Scaffold behavior

`scaffold()` runs at the start of every notebook and script:

- **Random seed:** R calls `set.seed()` and Python seeds `random` and NumPy's global generator.
- **Plot theme:** R calls `ggplot2::theme_set()`, Python applies the configured Matplotlib style, and Stata sets the configured graph scheme.
- **Functions:** R sources every `.R` file in `functions/` and Python adds `functions/` to `sys.path` for direct module imports.

### Forks and customization

Select **Copy and edit** on any blueprint, structure, package set, skill set, or skill to create your own editable copy. Saving an edit creates a new version while leaving existing projects on their original version.

Each item supports three visibility levels:

- **Private.** Visible only to your account.
- **Unlisted.** Accessible and copyable by direct link and omitted from public listings.
- **Public.** Listed for everyone on the server to view, use, and copy.

Skills can also be imported from a GitHub repository and published back to GitHub. Imported skills start private and record their upstream repository.

### Sensitive data

Projects built with a sensitive-data structure split inputs and outputs into `private/` and `public/` directories and gitignore private paths. Sensitive projects require private GitHub repositories and omit blueprint provenance metadata by default.

## Projects

Open **Projects > New project** to configure and create a project:

1. **Start.** Select a language (R, Python, or Stata) and a blueprint.
2. **Project.** Set the project name and description on **Basics**. Adjust any choice tabs (**Notebook format**, **Quarto**, **Jupyter**, **Project structure**, **Reproducibility**, **Packages**, **Scaffold**, **Templates**, **Editor support**, **AI Agents**, **AI Skills**, **Git**, **Connections**, and **Publishing**).
3. **Review.** Inspect the resolved settings and file tree, optionally toggle **Save as blueprint**, and choose a **Destination**:
   - **Local setup.** Framework issues a project token. Pass it to `framework::setup("<project token>")` in R, `framework.new(key="<project token>")` in Python, or `fw new` in a terminal. For Stata, run `fw login` once and then `fw new` with the project name, or pass a token with `fw new --pull-token`. Self-hosted servers include `url =` or `url=` in the displayed R and Python commands.
   - **GitHub repository.** Framework Cloud creates the repository and runs a one-time GitHub Action to generate the project files. See [GitHub integration](github.md).
   - **ZIP download.** Framework builds and downloads a `.zip` archive of the project folder.

   A destination the server does not support appears disabled with the reason. Self-hosted servers need a GitHub App for **GitHub repository** and the scaffolder service for **ZIP download**. Administrators also see the setting to change.

### Languages

Each project targets R, Python, or Stata. The R package scaffolds R projects, `framework-py` scaffolds Python projects, and `fw` scaffolds Stata projects. Inside Stata, the `framework` Stata package configures project globals, verifies dataset checksums, installs project packages, and publishes outputs. See [Framework for Stata](stata.md).

Stata projects support the Standard, Privacy sensitive, and Bare structures. Course and Presentation structures support R and Python only. Stata projects omit R and Python directories such as `notebooks/` and `functions/` and add `do/` and `logs/`.

Open a project and select **Computers & tokens** to view token usage dates, issue a new project token, or revoke an existing token. Project tokens are stored in the gitignored `.env` file alongside `FW_CLOUD_URL` and authorize the project to pull settings and publish artifacts.

## Cloud storage

Framework Cloud stores project definitions, blueprints, structures, sets, and templates. Local project setup keeps source datasets and scripts on your machine. Publishing uploads the selected artifacts and their supporting assets, which can include tables, figures, documents, and code.

Database and object-storage connections reference local environment variables such as `env("DB_PASSWORD")`. Syncing settings preserves the `env()` reference without uploading secret values.
