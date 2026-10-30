# Projects and blueprints

## Blueprints

A blueprint describes how a new project is set up: its folders, R packages,
Quarto templates, settings, and instructions for AI assistants. Each
blueprint is built from smaller pieces called facets, and skills hold
instructions for AI assistants.

**Blueprints** lists the built-in blueprints, the ones other people have made
public, and your own. Open one to see what it sets up.

### Borrowing and remixing

To start from someone else's blueprint, click **Copy and edit**. You get your
own copy, and you can change anything in it. Saving creates a new version, so
earlier projects keep the version they were made with.

Each blueprint, facet, and skill has a visibility setting:

- **Private.** Only you can see it.
- **Unlisted.** Anyone with the link can see and copy it. It doesn't appear
  in lists.
- **Public.** Everyone on the server can find, use, and copy it.

Facets and skills can also be imported from a GitHub repository and published
back to one. Imported items start out private and record where they came
from.

### Sensitive data

Some blueprints are marked for restricted data. Projects made from them must
be private repositories on GitHub, and they don't record data provenance
unless you turn it on.

## Projects

**Projects > New project** opens the project builder.

1. Choose a blueprint.
2. Give the project a name and, if you like, a description.
3. Choose the language. It starts as the blueprint's language.
4. Choose where to set it up:
   - **On your computer.** Framework Cloud gives you a project token. In R,
     run `framework::setup("<project token>")`. Framework builds the project
     in a folder you choose. For a Stata project, run `fw login` once and
     then `fw new --from <project name>` in a terminal. `fw` asks the server
     for the project token itself. With a token you already have, run
     `fw new --pull-token` and paste the token when it asks.
   - **On GitHub.** Framework Cloud creates the repository and builds the
     project there. See [GitHub integration](github.md).

On your own server, the page shows the command with the server's address,
such as `framework::setup("<project token>", url = "https://framework.example.edu")`.

### Languages

Each project has one language: R or Stata. The R package builds R projects.
The `fw` command-line tool builds Stata projects. Stata support covers
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
