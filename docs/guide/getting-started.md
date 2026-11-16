# Getting started

This guide uses Framework Public Cloud at framework.pub. On a self-hosted server, substitute your server address for framework.pub.

## Account registration

Open [framework.pub/register](https://framework.pub/register) and sign up with GitHub or an email address. Accounts require no password at registration. Email registration sends a one-time confirmation link that signs you in.

Your sign-in email address remains private. Public author email addresses are configured separately during setup. See [Signing in](signing-in.md).

## Guided setup

Guided setup configures your author profile and default blueprint. Enter your author name, an optional public email, and up to five affiliations. Website and ORCID fields are optional. Framework writes these details into new projects, notebooks, reports, and slides. Update them later in **Settings > Personal data**.

Select your default language and, optionally, a **Projects folder**: the local directory R, Python, and `fw` suggest for new projects. Leave it blank to use the current working directory. Change it later in **Settings > Project defaults**. Then select a starting blueprint. Configure its notebook format, project structure, packages, environment tools, editor files, AI agents, skills, Git settings, and publishing destination. Stata displays the settings that apply to `.do` scripts.

Review your choices and select **Finish setup**. Framework saves a private blueprint for that language and sets it as your default. New projects start from this blueprint and accept individual adjustments. Other languages keep their existing defaults. Each completed step saves automatically, so returning to setup resumes at your last section. **Skip setup** opens the dashboard and retains any saved author details and language choices.

## R and Python connections

Connect each computer once. Install the Framework package and run `cloud_login()` with no arguments. In Python, install `framework-py` (`pip install --pre framework-py` during pre-release).

```r
framework::cloud_login()
```

```python
import framework

framework.cloud_login()
```

`cloud_login()` summarizes what connecting shares and asks for confirmation. It then displays a verification code and opens the approval page in your browser. Sign in and approve the computer. Framework stores the token in your system credential store, such as macOS Keychain. R, Python, Stata, and `fw` share that stored token. Without a credential store, Framework writes the token to a private file in `~/.config/framework`. Subsequent sessions and new projects reuse this connection.

In Stata, run `fw_login`. In a terminal, run `fw login`. Both commands use the same approval flow. See [Framework for Stata](stata.md).

On a self-hosted server, pass the server URL:

```r
framework::cloud_login(url = "https://framework.example.edu")
```

```python
import framework

framework.cloud_login(url="https://framework.example.edu")
```

Framework saves the server URL for future commands. Run `cloud_status()` to check the active server.

On a machine without a browser, create a token in **Account > Computers & API tokens** and pass it directly as `cloud_login("<your token>")`. That page displays the full command and shows each token once.

## Project setup

Open **Projects > New project**.

1. Select a language and a blueprint.
2. Name the project and adjust any project tabs as needed.
3. Under **Destination**, choose where to set up the project:
   - **Local setup.** Framework issues a project token and prints the setup command. Run `framework::setup("<project token>")` in R, `framework.new(key="<project token>")` in Python, or `fw new` in a terminal. On a self-hosted server, the command includes `url =` (R) or `url=` (Python) with your server address.
   - **GitHub repository.** Framework Cloud creates the repository on GitHub and builds the project files there. Clone the repository to start working. See [GitHub integration](github.md). On a self-hosted server, this option requires the server's GitHub App.
   - **ZIP download.** Framework builds the project archive and downloads it in your browser. Unzip the folder to start working without Git or local Framework packages. On a self-hosted server, this option requires the scaffolder service.

You can also create projects directly from R or Python. After connecting with `cloud_login()`, `new_project()` applies your Cloud defaults and registers the project in your account. Its project token stays in the local gitignored `.env` file. Framework syncs the project definition and leaves datasets on your computer. Pass `offline = TRUE` in R or `offline=True` in Python to create a project locally without contacting Cloud.

## Git ignore rules

Every Framework project structure includes a `.gitignore` template that excludes private data, credentials, and local state from Git. The sensitive-data structure adds rules for private inputs, references, and outputs. Course structures also exclude grades, submissions, and student records.

Open **Project structures**, select **Copy and edit**, and edit the rules in the **.gitignore** tab. Save the structure and select that version in a blueprint. New projects combine those rules with package defaults. Prefix a pattern with `!` to override a default rule. Existing projects retain their original structure version until updated.

Ignore rules do not untrack files already committed to Git. Optional pre-commit hooks detect staged secrets and missing private-folder ignore rules. Check both `.gitignore` and tracked files before sharing a repository.

## Artifact publishing

Publish rendered notebooks or outputs from inside the project:

```r
framework::publish("notebooks/analysis.qmd")
```

```python
import framework

framework.publish("notebooks/analysis.qmd")
```

Framework renders the document, uploads it, and returns a shareable URL. See [Publishing artifacts](publishing.md).

## Local files and Cloud storage

Framework packages operate with or without a Cloud account. Framework Cloud also operates without local packages.

### Project files

| Path | File location | Cloud connection |
|---|---|---|
| ZIP download | Local folder extracted from the downloaded archive. `.env` records the server URL without a token. | Optional. Add a project token to `.env` to publish to Cloud. |
| GitHub repository | Repository on your GitHub account populated by a one-time GitHub Action. | Optional. |
| R or Python package | Local folder created on your machine. | Optional. Signed-in sessions register the project on Cloud and write a project token to `.env`. `offline = TRUE` skips Cloud. |

Every path generates the same project files: directories, `settings.yml`, data catalog entries, notebook configuration, and `.gitignore` rules. Collaborators rerunning the project need the project files, the input data, and Framework for R, Python, or Stata. Collaborators do not need a Cloud account.

### Cloud data

- Account details, author metadata, and default settings.
- Saved blueprints, project structures, package sets, and skills.
- Project definitions, including blueprint versions and custom choices.
- Published artifacts, collections, and reports.
- Dataset ledger records for connected projects: dataset name, SHA-256 fingerprint, file size, timestamp, and machine name. Source data rows are not uploaded to the ledger.

### Local files

- Input datasets, unless explicitly published. Publishing a data frame uploads its rows as a table artifact.
- Local `framework.db` or `.framework/stata-integrity.tsv` read and checksum records.
- `.env` files and tokens, which `.gitignore` excludes from version control.

Standard project `.gitignore` rules do not exclude `inputs/`. Select the privacy-sensitive structure or add custom rules to keep input data out of Git.

### Cloud-dependent actions

- Creating a project from a Cloud blueprint.
- Publishing artifacts to Framework Cloud and assembling collections or reports.
- Recording dataset ledger entries and running skill sync.

Reading cataloged data, verifying input checksums, and publishing to GitHub Pages or S3 run without Cloud.
