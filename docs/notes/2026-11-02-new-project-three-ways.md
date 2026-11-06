# New projects three ways: download, GitHub, or local

Where things stood at the end of 2026-11-02. Committed (framework-cloud
dc237b8, framework-cli d83f1b4) and live on framework.pub and the homelab's
stable and staging instances.

## What a person can do

| | Web | `fw new` |
|---|---|---|
| Download a zip | "Download a zip" on New project, and Download zip on the project page | `--zip`, or the walkthrough's second choice |
| On GitHub | "Create it on GitHub" (R, Python, and Stata) | `--github OWNER[/REPO]`, `--public`, `--no-clone` |
| On a computer | "Set it up on your computer" (token) | `--local`, the default |

Plain `fw new` at a terminal asks for the name, language, blueprint (logged
in), and how to get the project. Flags answer their questions. Without a
terminal fw never asks.

## framework-cloud

- **Scaffolder container** (`docker/scaffolder/`): Python stdlib HTTP service.
  `POST /build {layers, folder}` runs `fw new --from layers.json --headless`
  and answers with a zip. It pins fw, framework (R), and framework-py by
  commit SHA in the Dockerfile's ARGs. A build takes under a second.
- **Cloud never writes project files.** `App\Services\Scaffolder\ProjectZip`
  posts the layers (through `Layer::toJsonValue`, so empty maps stay `{}`) and
  only adds connection lines to `.env`: `FW_CLOUD_URL`, a commented
  `# FW_PROJECT_TOKEN=`, and the URL of the project's token page. It adds
  `.env` to `.gitignore` if the last line about `.env` doesn't ignore it.
  The zip has no token, because zips get passed around.
- **Routes**: `GET /projects/{id}/download` (web), `GET /api/v1/destinations`,
  `GET /api/v1/projects/{slug}/download`. `POST /api/v1/projects` takes
  `github: {owner, name?, private}`. Project show reports `scaffold_error`.
- **Python on GitHub**: `resources/github/framework-scaffold-python.yml`,
  pinned by `GITHUB_FRAMEWORK_PY_SHA` (default d10c19f).
- `RepositoryTarget` holds the GitHub owner and privacy checks for both the
  form and the API.
- `ThrottleRequestsPerRoute` now keys project tokens itself. Before this, a
  project token on any throttled route was a 500 (`Project` has no
  `getAuthIdentifier`).
- **Self-hosting**: compose gains a `scaffolder` service with
  `read_only`, `cap_drop: ALL`, and no published port. `SCAFFOLDER_URL`
  defaults to `http://scaffolder:8000`, and setting it empty turns downloads
  off. `.github/workflows/scaffolder-image.yml` publishes
  `ghcr.io/frameworktools/framework-scaffolder` with the app's version tags.
  The docs (install-docker, install-manual, configuration) cover it.
- Landing page: "Beginner or expert, start a project in minutes" after the hero, with a card for each way.

Tests: `ProjectDownloadTest`, `Api/V2/NewProjectDestinationsTest`, and new
Python cases in `GitHubTest`. The full suite passed (376).

## framework-cli

`internal/cli/newdest.go` (walkthrough, destination question, zip
extraction, GitHub wait and clone), `internal/cloud/destinations.go`, and
`doRaw` in the client. Guide: `docs/site/guides/projects.md`. Tests and
golangci-lint pass.

## Verified on the homelab

- Every Framework blueprint in R, Python, and Stata builds in the container,
  including renv (`.Rprofile` and `renv/activate.R`, with no library).
- Web: created projects with Download and fetched the zips. A downloaded R
  project ran `framework::scaffold()`. A downloaded Python project, with a
  token pasted into `.env`, reached the server through framework-py.
- fw: `fw new --zip` and the walkthrough through a pty, against the test
  instance.
- GitHub was not run end to end. The test instance has no GitHub App, and
  it would create real repositories. The Python workflow's command was run
  in a fresh checkout inside the container.

## Production (framework.pub)

- Docker (Ubuntu's `docker.io`) is installed on the Forge box, which it
  wasn't before. The `framework-scaffolder` container runs
  `framework-scaffolder:dc237b8`, built on the box from `git archive` of
  `docker/scaffolder` (source in `/opt/scaffolder-src`). It listens on
  127.0.0.1:8100 only, with `--read-only`, `--cap-drop ALL`, and
  `--restart unless-stopped`.
- `.env` has `SCAFFOLDER_URL=http://127.0.0.1:8100` and a random
  `SCAFFOLDER_TOKEN`. Root access is `ssh framework-root`.
- To move the pins: rebuild on the box the same way, or pull
  `ghcr.io/frameworktools/framework-scaffolder` (the `main` tag was
  published by a manual run; `latest` comes with the first v* tag).

## Open

1. **fw release**: `--zip` and `--github` need a new fw tag (the server's
   scaffolder pin, 6fe39d5, doesn't need them).
2. **Not built**: a "Put this on GitHub" button for projects that started as
   a download or on a computer, and R or Python functions to link an
   existing folder (fw has `fw project link`).
3. GitHub creation hasn't been run end to end, and the pages haven't been
   checked by eye in a browser.
4. Pins: `GITHUB_FRAMEWORK_SHA` defaults to bdb1115, the scaffolder's R pin.
   Move them together.
