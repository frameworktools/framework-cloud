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
  list) with renv or uv, what `scaffold()` does, git, and agent files with preferred skill
  sets.
- **Data safety.** The Git hooks that run before each commit. Whether a
  project holds sensitive data comes from its blueprint, such as Privacy
  Sensitive.
- **Documents.** The notebook format, Jupyter, and Quarto.

The pages share one form, so changes on several pages are saved together.

The Blueprint page previews what each blueprint creates as your default:
its folders and files, packages, agent skills, tools, and settings, for R,
Python, or Stata. The preview is resolved the same way a new project is,
with the values in the form, including changes you haven't saved.

#### How defaults apply

Your default blueprint is used when a new project doesn't name one, such as
`fw new NAME --defaults`. An account that never chose one gets the Standard
Project Structure.

Without a named blueprint, a new project gets:

- the default blueprint's structure, tools, package sets, and skill sets,
- your default packages and skill sets, added to the blueprint's,
- your tool defaults on top: a tool you turned on is added, and one you
  turned off is left out, even if the blueprint has it.

Choosing a blueprint replaces your default blueprint, packages, skill sets,
and tools, including ones the blueprint doesn't mention. Your
Git, agent setup, and Git hooks defaults still apply where the blueprint
doesn't set them itself. Sensitive data has no default: the blueprint and
its project structure decide, and Privacy Sensitive turns it on. When you
change a blueprint's pieces on the New project page, the sensitive data
switch on that page decides.

- **Agent setup.** Off, a project gets no context file and no skill sets.
- **Git hooks.** They need Git, so they're skipped when Git is off. A
  blueprint's own choice for a hook wins.

Some defaults sync to the computers where you've run `cloud_login()` in R
or Python: the default blueprint, editor, notebook format, Git, renv, the
random seed, and agent setup with its context file and skills. Package sets,
skill sets, the other tools, and Git hooks stay in your
account. They apply when Framework Cloud resolves a new project: on the New
project page, or with `fw new` and a cloud choice such as `--defaults` or
`--blueprint`. Plain `fw new NAME` builds the project with R's or Python's
own `new()`, which reads only the synced defaults.

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
