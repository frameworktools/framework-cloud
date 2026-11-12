# Getting started

This page uses Framework Public Cloud at framework.pub. On your own server,
replace framework.pub with your server's address.

## 1. Account registration

Open [framework.pub/register](https://framework.pub/register) and sign up with
GitHub or your email address. There is no password to set. If you sign up by
email, you get a link that confirms the address and signs you in.

You can sign up with a personal email address. The address you sign in with
stays private. The email shown on your work comes from your profile, which
you set in the next step. See [Signing in](signing-in.md).

## 2. Guided setup

Setup introduces blueprints, projects, and published results. Enter your author
name, an optional public email, and up to five affiliations. Website and ORCID
fields are optional. Your sign-in email stays separate. These details are used
in new projects, notebooks, reports, and slides. Edit them later in **Settings >
Personal data**.

Choose your default language, then a starting blueprint. Customize its notebook
format, project structure, packages, environment, editor files, AI agents,
skills, Git, and publishing settings. Stata shows the settings that apply to
.do scripts.

Review the choices and select **Finish setup**. Framework saves a private,
single-language blueprint and makes it your default. New projects start from
it and can be adjusted individually. Other languages keep their existing
defaults. Setup saves nothing until this final step. **Skip setup** opens the
dashboard without saving the draft.

You can then create a project or connect your computer.

## 3. R and Python connections

Connect each computer once. Install the Framework package, then run
`cloud_login()` with no arguments. In Python the package is `framework-py`
(`pip install --pre framework-py` while it is a pre-release).

```r
framework::cloud_login()
```

```python
import framework

framework.cloud_login()
```

`cloud_login()` shows what connecting shares and asks you to confirm. Then it
shows a code and opens the approval page in your browser. Sign in, or create a
free account, and approve the computer. The token is stored in your system's
credential store, such as the macOS Keychain. R, Python, Stata, and `fw` all
use it. Without a credential store, the token goes in a private file in
`~/.config/framework`. Later sessions and new projects reuse the
connection.

In Stata, run `fw_login`. In a terminal, run `fw login`. Both use the same
approval page. See [Framework for Stata](stata.md).

On your own server, add the server's address:

```r
framework::cloud_login(url = "https://framework.example.edu")
```

```python
import framework

framework.cloud_login(url="https://framework.example.edu")
```

Framework remembers the server, so later commands use it. Run
`cloud_status()` to see which server is in use.

On a computer without a browser, create a token on the **API tokens** page
and pass it instead: `cloud_login("<your token>")`. The page shows the full
command. A token is shown only once.

## 4. Project setup

Open **Projects > New project**.

1. Choose a blueprint. Open one to see the folders, packages, and templates it
   sets up.
2. Name the project.
3. Choose where to set it up:
   - **On your computer.** You get a project token and the command to run
     with it. In R it is `framework::setup("<project token>")`, and in
     Python it is `framework.new(key="<project token>")`. Framework builds the
     project. On a self-hosted server, the page adds `url =` (R) or `url=`
     (Python) with the server's address.
   - **On GitHub.** Framework Cloud creates the repository and builds the
     project there. Clone it and start working. See
     [GitHub integration](github.md).

You can also start on your machine. After signing in with `cloud_login()`,
`new_project()` in R or Python uses your Cloud settings and registers the new
project in your account. Its project token stays in the local, ignored `.env`
file. The project definition is shared. Your datasets are not uploaded. Use
`offline = TRUE` in R or `offline=True` in Python to create locally without
contacting Cloud.

## Git ignore rules

Every Framework project structure has a `.gitignore` template. It keeps private
data, credentials, and local files out of Git. The sensitive-data structure adds
rules for private inputs, references, and outputs. Course structures also exclude
grades, submissions, and student data.

Open **Project structures**, choose **Copy and edit**, and use the **.gitignore**
editor to change the rules. Save the structure and choose that version in your
blueprint. New projects receive those rules alongside the package's defaults.
Use `!` patterns to override a default when needed. Saved projects keep their
chosen version until you update them.

Ignore rules do not remove files already tracked by Git. Optional pre-commit
checks can catch private data staged for a commit and missing ignore rules.
Review both the rules and the files before sharing a repository.

## 5. Notebook publishing

From inside the project:

```r
framework::publish("notebooks/analysis.qmd")
```

```python
import framework

framework.publish("notebooks/analysis.qmd")
```

Framework renders the notebook and uploads it. The result is a link you can
send to collaborators. See [Publishing artifacts](publishing.md).

## Local files and Cloud storage

Framework's packages work without a Cloud account. Framework Cloud works
without the packages.

### Project files

| Path | File location | Cloud connection |
|---|---|---|
| ZIP download | Your computer. The ZIP has the project's folders, settings, and `.gitignore`. Its `.env` names the server but holds no token. | Not needed to run the project. Add a project token to `.env` to publish to Cloud. |
| GitHub repository | A repository on your GitHub account. A one-time GitHub Action builds the project files in it. | Not needed to run the project. |
| R or Python package | Your computer. | Optional. When you are signed in, the package registers the project on Cloud and saves a project token in the ignored `.env` file. `offline = TRUE` skips Cloud. |

The files are the same kind in every path: folders, `settings.yml`, the data
catalog, notebook settings, and the `.gitignore` rules. A colleague who reruns
the project needs the project files, the input data, and Framework for R or
Python. They don't need a Cloud account.

### Cloud data

- Your account, author details, and default settings.
- The blueprints and project structures you save.
- Each project's definition: its blueprint, structure, and choices.
- The artifacts, collections, and reports you publish.
- For a connected project, a ledger of saved datasets: each dataset's name,
  fingerprint, and size. The data itself is not uploaded.

### Local files

- Your input data, unless you publish it. Publishing a data frame uploads its
  rows as a table.
- The local `framework.db` record of reads and fingerprints.
- The `.env` file and its tokens. The project's `.gitignore` keeps them out of
  Git.

A standard project's `.gitignore` doesn't exclude `inputs/`. Use the
sensitive-data structure, or add rules, to keep data out of a repository.

### Cloud-dependent actions

- Starting a project from a saved blueprint.
- Publishing to Framework Cloud, and collections and reports.
- The dataset ledger and skill sync.

Reading cataloged data, verifying inputs, and publishing to GitHub Pages work
without Cloud.
