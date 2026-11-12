# Project creation end-to-end checks

All 44 supported built-in project combinations pass creation and promised-file checks. All 41 R and Python projects complete a synthetic analysis. All 31 notebook combinations execute and render. The four app types start and return successful HTTP responses.

Run directory: `/tmp/framework-e2e-20261111.rt9ett`.

## Coverage

| Blueprint | R | Python | Stata | Total |
| --- | ---: | ---: | ---: | ---: |
| Standard | 4 | 3 | 1 | 8 |
| Privacy sensitive | 4 | 3 | 1 | 8 |
| Bare | 4 | 3 | 1 | 8 |
| Presentation | 2 | 1 | 0 | 3 |
| Course | 2 | 1 | 0 | 3 |
| R Shiny | 4 | 0 | 0 | 4 |
| Plumber | 4 | 0 | 0 | 4 |
| Python Shiny | 0 | 3 | 0 | 3 |
| FastAPI | 0 | 3 | 0 | 3 |
| Total | 24 | 17 | 3 | 44 |

R formats: Quarto, R Markdown, Jupyter, and scripts where supported. Python formats: Quarto, Jupyter, and scripts where supported. Stata uses scripts. Presentation and course support a smaller format selection.

The Cloud matrix test uses real preview and creation controllers with an isolated test database. It exports each project's layered definition. The runtime matrix passes those exports to the actual language scaffolders. One additional project was created through the browser at https://framework-cloud.pequod.dev and pulled through the real token API with `framework::setup()`.

## Checks

- Every initially promised path in the Cloud preview exists after generation. Deferred paths are excluded from the initial-file assertion.
- Starter files exist and project-name template placeholders are expanded.
- Settings, resolved specifications, selected workflows, editor files, agent instructions, and skill directories exist.
- Git initializes, creates its first commit, and installs hooks. Environment files and sensitive input paths are ignored.
- Shared functions load from the configured folder.
- Five synthetic observations round-trip through Framework data save/read.
- A regression recovers the known slope of 2.
- Tables, models, and figures are saved. Python models also reload.
- Cached results round-trip and the local Framework database exists.
- All 31 notebook cases execute and produce HTML. Quarto presentations render as slides. Jupyter uses actual R and Python kernels.
- R Shiny and Python Shiny return HTTP 200. Plumber and FastAPI health and echo endpoints return the expected content.
- The headless CLI creates one project per language without a Git directory. ZIP assembly preserves files and empty structure folders for all three languages.
- Python's generated uv project resolves and installs its dependencies. The analysis passes using that project's own virtual environment and the published Framework package.
- An R project with renv enabled initializes its project library. Current Framework source and dependencies install into it. The analysis passes with that library active and a lockfile is written.
- R auto-attach respects enabled and disabled package choices.
- Browser creation reaches Computers & tokens. Token-based local setup creates the actual project, records its Cloud connection, and completes the analysis. Test tokens were revoked and their local copies removed.

## Failure and fix

R `scaffold()` ignored `directories.functions` and read only the older `options.functions_dir`. R Shiny and Plumber map shared functions to `R/`, so their functions were not loaded.

Fixed `/Users/erik/Projects/framework/R/scaffold.R` to prefer the configured Framework location and retain the legacy fallback. Added `tests/testthat/test-scaffold-function-location.R` in the R repository. Both the mapped-folder precedence and legacy fallback pass. The full runtime matrix passes against a fresh build of this source.

The initially installed R build also omitted `.Rproj` files for the VS Code editor choice. Current source already includes them. Rebuilding the current source resolved the preview mismatch. No additional product change was needed for that discrepancy.

The R fix remains a local source change. It has not been committed, pushed, or released.

## Automated checks

- Cloud suite: **714 passed**, 6,885 assertions, serial execution.
- New Cloud creation matrix: **44 combinations**, 309 assertions.
- R config-path and function-location regression tests: passed.
- Runtime creation matrix: **44/44** passed.
- R/Python analysis: **41/41** passed.
- Notebook execution and rendering: **31/31** passed.
- App startup: **4/4** passed.
- Headless creation and ZIP checks: **3/3 languages** passed.
- PHP formatting and syntax checks: passed.

The reusable harness is in `tests/e2e/project-matrix/`. Its README records prerequisites and commands. Runtime logs and JSON results remain in the run directory.

## Limits

Stata is not installed on this Mac. Its three project cases pass generation, preview/file parity, and content checks; do-files were not executed.

GitHub repository creation, remote artifact publication, and the deployed production scaffolder were not exercised live. Their existing Cloud tests pass, but this run does not establish a live external-service result. The ZIP check uses the actual ZIP assembler against locally generated projects.

The matrix exercises all built-in combinations through Cloud controllers. The browser check covers one complete creation flow, rather than 44 separate browser walkthroughs. R Markdown presentation cases execute an R Markdown analysis; the separate Quarto presentation cases exercise the generated slide starter.

## Test data

The 44 matrix projects were created in the isolated test database. Runtime folders and isolated package environments are under the run directory. The additional browser project is named `E2E browser project 20261111`, at https://framework-cloud.pequod.dev/projects/e2e-browser-project-20261111. Its temporary tokens were revoked.
