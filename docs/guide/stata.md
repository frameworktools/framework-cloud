# Framework for Stata

The `framework` Stata package brings Framework project conventions to Stata:

- Project root discovery and directory path globals.
- Cataloged datasets with SHA-256 and `datasignature` verification.
- Publishing for figures, tables, documents, and estimation results.
- Integration with Framework Cloud.

Stata projects share `settings.yml`, the data catalog, and publishing destinations with R and Python.

## Installation

The package requires Stata 14 or later and `curl` for Framework Cloud requests. macOS, Linux, and Windows 10 or later include `curl`. Python is optional.

Install the package from a local checkout or package URL and verify your environment:

```stata
net install framework, from("/path/to/framework-stata") replace
fw_setup
```

`fw_setup` checks Stata, system utilities, optional backends, and the Cloud connection. It prints remediation steps for any missing tool. On Framework Cloud servers, `FW_STATA_PACKAGE=false` hides public Stata documentation and switcher links until Stata docs have been imported.

| Command | Purpose |
|---|---|
| `fw_setup` | Check Stata version, system tools, backends, and Cloud authentication |
| `fw_project` | Locate the project root and set directory globals |
| `fw_packages` | Inspect or install Stata packages listed in `settings.yml` |
| `fw_use` | Load a cataloged dataset and verify its checksum |
| `fw_save` | Save a cataloged dataset and record its checksum |
| `fw_publish` | Publish a file or active estimation results |
| `fw_login` | Authenticate this computer with Framework Cloud |
| `fw_logout` | Remove this computer's Framework Cloud token |
| `fw_set` | Configure the active backend or `fw` binary path |

## Projects

Create a Stata project in Framework Cloud or run `fw new` in a terminal with **Stata** selected as the language. Stata projects organize code in `.do` files.

Call `fw_project` at the top of a do-file to locate the project root and define path globals:

```stata title="Do-file"
fw_project
use "$fw_inputs_raw/penguins.dta", clear
graph export "$fw_outputs_figures/flipper-and-mass.svg", replace
```

`fw_project` searches upward from the working directory for `.framework/`, `settings.yml`, `config.yml`, or `settings.do`. It sets `$fw_root` and one global macro per configured directory.

Stata parses `settings.yml` directly, including split files such as `settings/data.yml` and environment blocks. Standard YAML mappings, sequences, and scalars are supported. YAML anchors, aliases, and nested flow collections halt execution with a descriptive error.

## Packages

Stata blueprints and projects can select community Stata package sets (**Stata regression tables**, **Stata graphs**, **Stata fixed effects**, and **Stata data tools**) alongside individual packages from SSC, GitHub, or net URLs. When `FW_SPEC_PARITY=true` is enabled on the server, Framework writes structured Stata package entries under `packages:` in `settings.yml`. When `FW_SPEC_PARITY` is `false` (the default for older `fw` clients), Framework writes package names to `.framework/packages/stata.txt`.

Inside Stata, `fw_packages` checks structured `settings.yml` package entries and installs missing packages:

```stata
fw_packages
fw_packages install
```

When **Project packages** (`packages.project_ado`) is enabled, packages install into `ado/plus` inside the project directory so exact versions can be committed to Git. Disable **Project packages** on the blueprint or project **Packages** tab to use Stata's shared `PLUS` directory instead.

## Data

`fw_use` loads datasets from the project data catalog and verifies integrity, while `fw_save` writes cataloged datasets and records checksums:

```stata
fw_use inputs.raw.penguins, clear
fw_save inputs.final.analysis, replace
```

- `fw_save` records both the file's SHA-256 hash and Stata's `datasignature` and `fw_use` checks both values.
- Mismatches on locked datasets stop execution with error 459, while unlocked datasets emit a warning. Datasets default to locked unless saved with the `unlocked` option.
- SHA-256 hashing uses `sha256sum` or `shasum` on Linux and macOS and PowerShell on Windows.
- Stata stores local checksum records in `.framework/stata-integrity.tsv` because Stata does not read SQLite `framework.db` files directly.
- When the Cloud dataset ledger is enabled, `fw_save` sends the dataset name, checksum, byte size, and machine name to Framework Cloud without uploading dataset rows.

## Artifact publishing

`fw_publish` uploads saved files or active estimation results:

```stata
regress body_mass_g flipper_length_mm i.species
fw_publish, estimates slug(penguin-body-mass) title("Penguin body mass model")

fw_publish "$fw_outputs_figures/flipper-and-mass.svg", slug(flipper-and-mass)
```

Publishing destinations come from `settings.yml`:

| Destination | Handled by |
|---|---|
| Local or shared folder | Stata |
| Framework Cloud | Stata using `curl` and `FW_PROJECT_TOKEN` from `.env` |
| GitHub Pages | `fw` CLI or `framework-py` |
| S3 object storage | `fw` CLI or `framework-py` |

Published estimation results display coefficient tables, forest plots, and fit statistics matching R and Python model artifacts. Full metadata is extracted for `regress`, `logit`, `logistic`, `probit`, `poisson`, `nbreg`, `glm`, `mixed`, `stcox`, and `ivregress`. Other estimation commands exposing `e(b)` and `e(V)` publish coefficient tables. See [Publishing artifacts](publishing.md).

Publishing any path containing `private` requires confirmation. Interactive Stata prompts before uploading and batch mode stops unless the `includeprivate` option is passed.

## Framework Cloud connection

Authenticate each computer once:

```stata
fw_login
```

Stata displays a device code and opens the browser approval page. Add `nobrowser` to print the approval URL without launching a browser. Pass `url()` to connect to a self-hosted server.

Approved tokens are stored in the system keyring shared by R, Python, and `fw`. When no keyring is available, Stata writes a private file under `~/.config/framework`. Tokens are omitted from Stata's Results window, macros, and log files. Run `fw_logout` to delete the stored token.

`fw_login` authenticates your personal account on the machine, whereas project artifact publishing authenticates with `FW_PROJECT_TOKEN` in the project's `.env` file.

## Backends

Folder publishing, Cloud publishing, data integrity checks, and account login run natively in Stata. Publishing to GitHub Pages or S3 requires either the `fw` CLI backend or the Python backend.

### CLI backend

Install `fw` following the [CLI documentation](https://cli.framework.pub/guides/install). Stata searches `PATH` automatically. Because macOS GUI applications launch with a minimal `PATH`, set the binary location explicitly if Stata does not find `fw`:

```stata
fw_set fw_path "/opt/homebrew/bin/fw"
```

### Python backend

The Python backend requires Stata 16 or later and `framework-py` installed in Stata's configured Python environment. Run `python query` in Stata to inspect the active interpreter path, then install `framework-py`:

```sh
/path/from/python/query -m pip install framework-py
```

Stata requires a Python installation compiled with a shared `libpython` library. If a system or cluster Python lacks a shared library, `uv python install 3.11` provides a compatible build. Configure Stata with the resolved interpreter binary path rather than a symlink. Prepend virtual-environment site-packages with `python set userpath`:

```stata
python set exec "/home/me/.local/share/uv/python/cpython-3.11-linux-x86_64-gnu/bin/python3.11"
python set userpath "/home/me/venvs/framework/lib/python3.11/site-packages", prepend
```

### Backend selection

In the default `auto` mode, Stata handles native operations directly and delegates GitHub Pages or S3 publishing to `fw` first and `framework-py` second. Pin a specific backend persistently with `fw_set`:

```stata
fw_set backend core
fw_set backend cli
fw_set backend python
fw_set backend auto
```

Set `global FW_BACKEND cli` to override the backend for the current session only. If an explicitly pinned backend is unavailable, Stata halts with setup instructions rather than falling back to another backend.
