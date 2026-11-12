# Project runtime matrix

These checks use real R, Python, Quarto, and CLI runtimes. They create temporary projects and execute synthetic analyses. Stata generation is checked; executing do-files requires a separate Stata installation.

## Runtime directory

Set `FW_E2E_ROOT` to a dedicated temporary directory. Provide:

- `python/bin/python`: a virtual environment with Framework for Python, NumPy, pandas, matplotlib, nbformat, nbclient, ipykernel, Shiny, FastAPI, and uvicorn.
- `r-library/`: an R library with the current Framework source, its dependencies, IRkernel, and the blueprint packages.
- `fw`: the current Framework CLI binary.
- `jupyter/share/jupyter/`: registered `ir` and `python3` kernels.

Rscript and Quarto must be installed. The render runner currently uses the Mac Quarto path `/Applications/quarto/bin/quarto`. The scripts use isolated `FW_CONFIG_HOME` directories. Git commits and hooks stay inside the generated projects.

Copy `create.R`, `smoke.R`, and `smoke.py` from this directory to the runtime directory. Then export the matrix through the real Cloud controllers using the isolated test database:

```sh
FW_E2E_EXPORT_DIR="$FW_E2E_ROOT" php artisan test --compact tests/Feature/ProjectEndToEndMatrixTest.php
```

Run these commands in order from the repository root:

```sh
"$FW_E2E_ROOT/python/bin/python" tests/e2e/project-matrix/project_matrix.py
"$FW_E2E_ROOT/python/bin/python" tests/e2e/project-matrix/render_matrix.py
"$FW_E2E_ROOT/python/bin/python" tests/e2e/project-matrix/apps.py
"$FW_E2E_ROOT/python/bin/python" tests/e2e/project-matrix/headless.py
```

The creation runner checks all 44 supported combinations. It verifies promised files, templates, agent files, skills, Git, ignore rules, hooks, function loading, data round trips, a known regression coefficient, models, tables, figures, caching, and the local database. `FW_E2E_LANGUAGES=r,stata` limits a rerun and preserves other results.

The render runner executes and renders 31 notebook combinations. The app runner checks one instance of each of the four app types. The headless runner checks one project per language. JSON results and per-project logs are written under the runtime directory. Failures return a nonzero exit status.

The environment checks for uv and renv, the R auto-attach check, the ZIP checks, and the browser-to-local token setup were additional checks during the 2026-11-11 run. They are recorded in `docs/work/2026-11-11-project-creation-e2e.md`.
