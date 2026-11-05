# Framework for Stata

The framework package for Stata gives a Stata project the same workflow as R
and Python:

- project paths;
- named datasets with SHA-256 checks;
- publishing figures, tables, documents, and estimation results;
- a connection to Framework Cloud.

All of them share one set of project settings, one data catalog, and the same
publishing destinations.

## Installation

The package needs Stata 14 or newer, and curl for Framework Cloud. curl ships
with macOS, Linux, and Windows 10 or later. Python is not needed.

Install from a checkout of the repository:

```stata
net install framework, from("/path/to/framework-stata") replace
fw_setup
```

`fw_setup` lists what works and the exact steps for anything missing.

| Command | Purpose |
|---|---|
| `fw_setup` | Check Stata, system tools, backends, and the Cloud connection |
| `fw_project` | Find the project and set path globals |
| `fw_use` | Load a named dataset and check its integrity |
| `fw_save` | Save a named dataset and record its integrity |
| `fw_publish` | Publish a figure, table, document, or estimation results |
| `fw_login` | Connect this computer to Framework Cloud |
| `fw_logout` | Remove this computer's Cloud token |
| `fw_set` | Set the backend and the location of `fw` |

## Projects

Create a Stata project in Framework Cloud, or with `fw new` in a terminal.
Choose **Stata** as the project's language. Stata projects use do-files.
Package sets don't apply to Stata.

In a do-file, `fw_project` finds the project and sets path globals:

```stata
fw_project
use "$fw_inputs_raw/penguins.dta", clear
graph export "$fw_outputs_figures/flipper-and-mass.svg", replace
```

`fw_project` walks up from the current folder to the first folder with
`.framework/`, `settings.yml`, `config.yml`, or `settings.do`. It sets
`$fw_root` and one global per project folder.

Stata reads `settings.yml` directly, including split files such as
`settings/data.yml` and environment sections. Plain YAML mappings, lists, and
scalars work. Anchors, aliases, and nested flow collections stop with an
error that names them.

## Data

Named datasets come from the project's data catalog. `fw_use` loads one and
checks that it hasn't changed. `fw_save` saves one and records its checksum.

```stata
fw_use inputs.raw.penguins, clear
fw_save inputs.final.analysis, replace
```

- `fw_save` records the file's SHA-256 checksum and Stata's `datasignature`.
  `fw_use` compares both.
- A changed locked dataset stops with error 459. An unlocked one warns.
  Datasets are locked unless you save them with the `unlocked` option.
- SHA-256 comes from `sha256sum` or `shasum` on Linux and macOS, and from
  PowerShell on Windows.
- Stata keeps its records in `.framework/stata-integrity.tsv`. R and Python
  keep theirs in `framework.db`, which Stata can't read.
- With the integrity ledger on, `fw_save` sends the checksum, the file size,
  and the computer's name to Framework Cloud. The data itself is not
  uploaded.

## Publishing

`fw_publish` publishes a file or the current estimation results:

```stata
regress body_mass_g flipper_length_mm i.species
fw_publish, estimates slug(penguin-body-mass) title("Penguin body mass model")

fw_publish "$fw_outputs_figures/flipper-and-mass.svg", slug(flipper-and-mass)
```

Destinations come from the project settings, as in R and Python:

| Destination | Handled by |
|---|---|
| A folder, such as a shared drive | Stata |
| Framework Cloud | Stata, with curl and the project token in `.env` |
| GitHub Pages | `fw` or framework-py |
| S3 | `fw` or framework-py |

Published estimates show coefficients, a forest plot, and fit statistics,
like a model published from R or Python. `regress`, `logit`, `logistic`,
`probit`, `poisson`, `nbreg`, `glm`, `mixed`, `stcox`, and `ivregress` get
full model details. Other commands with `e(b)` and `e(V)` publish a
coefficient table. See [Publishing artifacts](publishing.md) for versions and
sharing.

A file whose full path contains "private" needs confirmation. Interactive
Stata asks. Batch mode stops until you add the `private` option.

## Framework Cloud connection

Connect each computer once:

```stata
fw_login
```

Stata shows a code and opens the approval page. Sign in, or create a free
account, and approve the computer. Add `nobrowser` to print the link instead,
and `url()` for your own server.

The token is stored in the system keyring, where R, Python, and `fw` find it
too. Without a keyring, it goes in a private file in `~/.config/framework`.
The token never appears in Stata's Results window, macros, or logs.
`fw_logout` removes it.

Publishing a project to Cloud uses the project token in the project's `.env`
file. `fw_login` connects only the computer.

## Backends

GitHub Pages and S3 publishing need one of two backends. Everything else runs
in Stata.

### CLI backend

Install `fw` from the [CLI documentation](https://cli.framework.pub/guides/install).
Stata finds it on the PATH. On macOS, the Stata application starts with a
short PATH. Point Stata at `fw` when it isn't found:

```stata
fw_set fw_path "/opt/homebrew/bin/fw"
```

### Python backend

The Python backend needs Stata 16 or newer, with framework-py installed in
Stata's Python. `python query` shows which Python Stata uses. Install the
package with that Python:

```sh
/path/from/python/query -m pip install framework-py
```

Stata needs a Python built with a shared `libpython`. Most installers provide
one. Some cluster modules don't. `uv python install 3.11` gives a shared
build. Point Stata at the interpreter's real path instead of a symbolic link.
Add a virtual environment's packages with `python set userpath`:

```stata
python set exec "/home/me/.local/share/uv/python/cpython-3.11-linux-x86_64-gnu/bin/python3.11"
python set userpath "/home/me/venvs/framework/lib/python3.11/site-packages", prepend
```

### Backend choice

With `auto`, the default, Stata handles folders, Cloud, and login itself. For
GitHub Pages and S3 it uses `fw`, then framework-py. Force one backend:

```stata
fw_set backend core
fw_set backend cli
fw_set backend python
fw_set backend auto
```

`global FW_BACKEND cli` forces one for the current session. A forced backend
that isn't available stops and shows the fix. It does not fall back to
another backend.
