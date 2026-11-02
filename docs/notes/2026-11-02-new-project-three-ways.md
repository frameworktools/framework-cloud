# New projects three ways: download, GitHub, or local

Where things stood at the end of 2026-11-02. Nothing here is committed or
deployed to production. The homelab test instance
(https://framework-cloud-test.pequod.sh) runs it from the working tree.

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
- **Routes**: `GET /projects/{id}/download` (web), `GET /api/v2/destinations`,
  `GET /api/v2/projects/{slug}/download`. `POST /api/v2/projects` takes
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
- Landing page: "Three ways to start a project" after the hero.

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

## Open

1. **Commit and push**: framework-cloud and framework-cli are uncommitted.
2. **Homelab stable follows main.** Once compose.yml on main names the
   scaffolder, stable needs `ghcr.io/frameworktools/framework-scaffolder:latest`.
   The VM has that tag locally (built from `~/scaffolder-build`), and a
   release publishes it to ghcr.
3. **Production (Forge)**: run the container on the box, as in
   install-manual's Downloads section, and set `SCAFFOLDER_URL` and
   `SCAFFOLDER_TOKEN`.
4. **Pins**: `GITHUB_FRAMEWORK_SHA` now defaults to bdb1115, the
   scaffolder's R pin, so the GitHub and zip paths match. Move them together.
5. **fw release**: `--zip` and `--github` need a new fw tag, and the
   scaffolder's fw pin should move to it.
6. **Not built**: a "Put this on GitHub" button for projects that started as
   a download or on a computer, and R or Python functions to link an
   existing folder (fw has `fw project link`).
7. The New project and project pages were checked through their props and
   HTTP responses, not by looking at them in a browser.
