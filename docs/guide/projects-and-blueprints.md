# Projects and blueprints

## Blueprints

A blueprint is a saved set of choices for new projects. It combines these
pieces:

- **Project structure.** The folders and core files a project starts with,
  such as `settings.yml` and `AGENTS.md`.
- **Tools.** Optional tools that add their own files, folders, and packages:
  Quarto, Quarto manuscript, Quarto slides, targets, and renv. Stata projects
  have no tools.
- **Package sets.** Named lists of packages for one language.
- **Skills.** Instructions for coding agents, chosen one at a time or as a
  skill set.
- **Settings.** Whether the project holds sensitive data, whether it is set
  up for coding agents, and whether it uses git.

**Blueprints** lists the built-in blueprints, the ones other people have made
public, and your own. Open one to see what it sets up. **Project
structures**, **Package sets**, **Skill sets**, and **Skills** list the
pieces, and you can make your own of each.

### Defaults

**Project defaults** sets what a new project starts with. Its pages are
grouped by subject:

- **New projects.** The default language and blueprint, editor support,
  default R and Python packages (a package set loads its packages into the
  list) with renv or uv, what `scaffold()` does, git, the data safety hooks
  that run before each commit, and agent files with preferred skill sets.
  Whether a project holds sensitive data comes from its blueprint, such as
  Privacy Sensitive.
- **Notebooks.** The default format for new notebooks (Quarto, Jupyter, or R Markdown for R), Quarto's HTML, slide, and project settings, and Jupyter setup.

The pages share one form, so changes on several pages are saved together.

The Blueprint page previews what each blueprint creates as your default:
its folders and files, packages, agent skills, tools, and settings, for R,
Python, or Stata. The preview is resolved the same way a new project is,
with the values in the form, including changes you haven't saved.

#### How defaults apply

Every way of creating a project resolves it the same way: the New project
page, R's and Python's `new()`, and `fw new`. When you're signed in, R,
Python, and `fw` ask Framework Cloud to resolve the project, so they all
build what the Blueprint page previews.

A new project starts from the blueprint you name, or from your default
blueprint when you don't name one. Its workflow comes from your default for
the project's language: Quarto for R, Jupyter for Python, and Scripts only
for Stata, unless you chose otherwise. When a blueprint doesn't support your
workflow, it uses its own and says so.

Settings combine in one order, highest first:

1. **What the blueprint requires**, such as Quarto for the Course blueprint.
   Nothing overrides a requirement. Choosing something that conflicts with
   one, such as turning Quarto off in a course, is refused before the
   project is created.
2. **What you choose for this project.**
3. **Your defaults.** Only the ones you changed from Framework's own count,
   so a blueprint's values show through everywhere else.
4. **The blueprint's own values.**
5. **The workflow**, then the **project structure**, then Framework's
   built-in defaults.

Pieces from your defaults combine with a blueprint, including one you chose
by name:

- **Packages, package sets, and skill sets** from your defaults are added to
  the blueprint's. A project that lists its own package sets or skill sets
  uses those instead of yours, alongside the blueprint's.
- **Tools, including starter documents** such as a Quarto manuscript: a tool
  you turned on is added, and one you turned off is left out, even when the
  blueprint has it. A tool the workflow or the blueprint requires stays. A
  tool chosen for the project wins over your default.

Your editor files and AI agents are yours alone, so no blueprint sets them.
Sensitive data comes from the blueprint and its project structure, and
Privacy Sensitive turns it on.

- **Agent setup.** Off, a project gets no agent files and no skill sets.
- **Data safety hooks.** They need Git, so they're skipped when Git is off.

A project keeps the configuration it was created with. Changing your
defaults affects only projects you create afterward.

R, Python, and `fw` save your resolved defaults on each computer when they
connect. Offline, they build from that copy and say which date it's from.
With no copy, they ask first, or stop in a script unless you pass
`offline = TRUE` (R), `offline=True` (Python), or `--offline` (`fw`). They
never switch to a different configuration without saying so.

#### Notebook workflows

Each language has a notebook workflow, set under **Project defaults >
Notebook workflow**:

- **Quarto:** `make_notebook()` creates Quarto documents (`.qmd`).
- **R Markdown** (R only): R Markdown documents (`.Rmd`), rendered by Quarto.
- **Jupyter:** Jupyter notebooks (`.ipynb`) with the language's kernel,
  IRkernel for R and ipykernel for Python. The project keeps Quarto, which
  renders and publishes the notebooks.
- **Scripts only:** no notebooks. `make_notebook()` creates a script.
  Stata projects always use this workflow.

The workflow sets the default format. A project can still hold notebooks in
other formats: `make_notebook("analysis.ipynb")` creates a Jupyter notebook in
a Quarto project.

A project gets the tools its workflow needs: Quarto for the Quarto and R
Markdown workflows, and Jupyter and Quarto for the Jupyter workflow. **Add
Jupyter to Quarto and Scripts-only projects** and **Add Quarto to
Scripts-only projects** add them to the other workflows.

`save_notebook()` renders a notebook, Quarto or Jupyter, with Quarto, into
the project structure's render folder, such as `outputs/notebooks/`. It
renders the outputs saved in the notebook, so run the notebook first.
Notebook outputs can contain data. The **Block notebook outputs** data
safety hook stops a commit until they're cleared.

The project's `_quarto.yml` at the root holds the shared HTML and slide
settings. A folder that renders differently, such as a course's slides, has
its own `_metadata.yml`.

#### What scaffold() does

`scaffold()` runs at the top of every notebook and script:

- **Random seed:** R calls `set.seed()`. Python seeds `random` and NumPy's
  global generator.
- **ggplot2 theme** (R only): `theme_set()` with the chosen theme.
- **Functions:** R sources each file in `functions/`. Python adds the folder
  to the import path, so `functions/helpers.py` imports as `helpers`.

#### Guided setup

New accounts start with six questions: language, typical project, workflow,
editors, Git, and AI agents. The answers become your defaults, and a summary
shows what new projects will include before you save. Run it again from
**Project defaults > Blueprint > Run guided setup**.

### Borrowing and remixing

To start from someone else's blueprint, structure, or set, click **Copy and
edit**. You get your own copy, and you can change anything in it. Saving
creates a new version, so earlier projects keep the version they were made
with.

Each blueprint, structure, set, and skill has a visibility setting:

- **Private.** Only you can see it.
- **Unlisted.** Anyone with the link can see and copy it. It doesn't appear
  in lists.
- **Public.** Everyone on the server can find, use, and copy it.

Skills can also be imported from a GitHub repository and published back to
one. Imported skills start out private and record where they came from.

### Sensitive data

A sensitive project, such as one from the Privacy Sensitive blueprint,
splits its data and output folders into private and public folders and keeps
private files out of git. Projects with
sensitive data must be private repositories on GitHub, and they don't record
data provenance unless you turn it on.

## Projects

**Projects > New project** opens the project builder.

1. Choose a blueprint. It starts as your default blueprint. Click **Change
   pieces** to change its structure, tools, packages, skills, or settings for
   this project only.
2. Give the project a name and, if you like, a description.
3. Choose the language. It starts as the blueprint's language.
4. Choose where to set it up:
   - **On your computer.** Framework Cloud gives you a project token. In R,
     run `framework::setup("<project token>")`. In Python, run
     `framework.new(key="<project token>")`. Framework builds the project in
     a folder you choose. For a Stata project, run `fw login` once and
     then `fw new --from <project name>` in a terminal. `fw` asks the server
     for the project token itself. With a token you already have, run
     `fw new --pull-token` and paste the token when it asks.
   - **On GitHub.** Framework Cloud creates the repository and builds the
     project there. See [GitHub integration](github.md).

On your own server, the page shows the command with the server's address,
such as `framework::setup("<project token>", url = "https://framework.example.edu")`.
Python uses the server you connected to with `cloud_login()`.

### Languages

Each project has one language: R, Python, or Stata. The R package builds R
projects, and the Python package builds Python projects. The `fw`
command-line tool builds Stata projects. Stata support covers
scaffolding only: Framework creates the folders, files, and Git setup, and it
has no Stata commands for the analysis itself. A blueprint has a language too,
which new projects start with.

Stata projects can use the standard, privacy-sensitive, or bare structure.
The course and presentation structures are for R only. A Stata project leaves
out the folders that only R uses, such as `notebooks` and `functions`, and
`fw` adds Stata's own folders, such as `do` and `logs`.

The **Projects** page lists each project's tokens with when they were last
used. Revoke a token there when a computer no longer needs it.

The project token also lets that project publish notebooks. It is saved in
the project's `.env` file, which Git ignores, together with the server's
address.

## What Framework Cloud keeps

Framework Cloud keeps project settings, blueprints, and templates. It never
receives your data or your analysis code. Connection details for databases
and storage refer to environment variables on your computer, such as
`env("DB_PASSWORD")`. Their values are never sent.
