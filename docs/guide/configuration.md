# Configuration reference

Framework Cloud reads runtime configuration from environment variables, typically defined in `.env`. In Docker deployments, `.env` sits alongside `compose.yml`. Apply Docker edits with `docker compose up -d`. In manual deployments, apply edits with `php artisan config:cache` and `php artisan queue:restart`. Manual installs start from `.env.server.example`, which runs in production mode.

## Server

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | none | Required application encryption key. Generate once and store in backups. |
| `APP_PREVIOUS_KEYS` | none | Comma-separated prior `APP_KEY` values retained during key rotation. |
| `APP_URL` | none | Canonical public URL, such as `https://framework.example.edu`. |
| `FW_CLIENT_URL` | `APP_URL` | Server URL printed in R, Python, and `fw` setup commands when client routing differs from `APP_URL`. |
| `FW_MODE` | `self` | `self` runs a self-hosted server with unlimited publishing. `hosted` runs Framework Public Cloud (`framework.pub`). |
| `FW_ADMIN_EMAIL` | none | Email address of the initial administrator account seeded at startup. |
| `FW_ADMIN_NAME` | derived from email | Display name for the seeded administrator account. |
| `FW_REGISTRATION` | `open` | `open` allows public sign-up. `closed` restricts account creation to `fw:login-link --create` and `fw:password --create`. |
| `FW_DOCUMENTS_DISK` | `local` | Filesystem disk for document storage. Switches to `bucket` when a bucket is saved in **Server administration**. |
| `FW_ARTIFACTS_DISK` | `local` | Private filesystem disk for artifacts. Must have no public bucket or web-root access. Run `fw:move-artifacts` after changing it. |
| `FW_PUBLISH_STORAGE_LIMIT_BYTES` | `1073741824` when `FW_MODE=hosted`, none when `self` | Per-account artifact storage limit in bytes, across all versions and Trash. Applies in both modes when set. `0` removes the limit. |
| `FW_AGENTS` | `true` | `false` disables `/mcp`, OAuth agent sign-in, and the sidebar agent pages, overriding **Server administration**. See [AI apps](ai-apps.md). |
| `FW_AGENT_PLUGIN` | unset | Displays `frameworktools/plugins` installation steps on the agent setup page (`framework.pub` only when unset). |
| `FW_TRASH_DAYS` | `30` | Retention window in days before the `scheduler` service permanently deletes trashed items and artifact files (`0` disables automatic purge). |
| `FW_REVISIONS_KEPT` | `200` | Revisions kept of each account's settings document. Older revisions are deleted on save. |
| `FW_CONTENT_URL` | none | Separate origin for serving signed published file URLs without session cookies, such as `https://content.example.org`. |
| `FW_CONTENT_SIGNING_KEY` | none | Key shared with a Cloudflare Worker on `FW_CONTENT_URL` that serves files directly from the bucket through short-lived signed links. Unset, the app serves files itself. |
| `FW_CONTENT_WORKER_DISK` | `r2` | Disk the content Worker's bucket binding reads. Only files on this disk go through the Worker. |
| `FW_STATA_PACKAGE` | `true` | Displays Framework for Stata in public navigation and homepage tabs. `false` hides it until Stata docs are imported. |
| `FW_SPEC_PARITY` | `false` | Emits structured Stata packages, Stata graph schemes, and Matplotlib styles in resolved project layers for newer `fw` and `framework-py` clients. |
| `FW_SPEC_TOOLS` | `true` | Emits the `tools`, `settings_layout`, and `core_files` keys in resolved project layers. Set `false` while users run clients older than `fw` 0.3, such as `fw` 0.2, which reject these keys. |
| `FW_SPEC_WORKFLOWS` | `true` | Emits `scaffold.workflow` and the `script` notebook format in resolved project layers. Set `false` while users run clients that predate workflows. |
| `FW_HOMEPAGE_EXAMPLE_URL` | none | Public artifact shown as the homepage's worked example, published by `fw:homepage-example`. Unset, the homepage shows a preview image. |
| `FW_STARTER_REPOSITORY` | none | Public starter repository (`owner/name`) shown on the homepage's GitHub card. |
| `TRUSTED_PROXIES` | loopback | Reverse proxies trusted for `X-Forwarded-*` headers: `cloudflare`, `*`, or comma-separated IPs and CIDRs. |
| `MONITORING_METRICS_TOKEN` | none | Bearer token required by `GET /api/metrics` for Prometheus scraping. Unset, the endpoint returns 401. |

## Sign-in

| Variable | Default | Description |
|---|---|---|
| `FW_PASSWORDS` | `true` when `FW_MODE=self` | Enables email-and-password sign-in alongside email links, passkeys, and GitHub. |
| `FW_SINGLE_USER` | `false` | Automatically signs local requests in as the administrator. See [Local installation](running-at-home.md). |
| `FW_SINGLE_USER_NETWORKS` | `127.0.0.1/32,::1/128` | Comma-separated CIDRs permitted in single-user mode (Docker appends its bridge gateway when unset). |
| `SESSION_LIFETIME` | `120` | Idle minutes before a sign-in marked **This is a shared computer** ends. Other sign-ins last one year. |
| `GITHUB_REDIRECT_URI` | `/auth/github/callback` | OAuth callback for the GitHub App's account connection. |
| `GITHUB_LOGIN_REDIRECT_URI` | `/auth/github/login/callback` | OAuth callback for the GitHub sign-in OAuth App. |
| `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` | none | PEM keys that sign OAuth tokens for connected AI apps. Unset, the app creates `storage/oauth-private.key` (mode 0600) and `storage/oauth-public.key` the first time an agent signs in. Docker creates them on first start. |

## Docker

These variables configure `docker/selfhost/compose.yml` across its five services (`app`, `worker`, `scheduler`, `scaffolder`, and `postgres`):

| Variable | Default | Description |
|---|---|---|
| `FRAMEWORK_CLOUD_VERSION` | `latest` | Image tag for `framework-cloud` and `framework-scaffolder`, such as `1.2.0`. |
| `SERVER_NAME` | none | Caddy site hostname for automatic HTTPS or `:80` for plain HTTP behind an external proxy. |
| `HTTP_PORT` | `80` | Host port mapped to container port 80. |
| `HTTPS_PORT` | `443` | Host TCP and UDP port mapped to container port 443. |
| `HTTP_BIND` | `0.0.0.0` | Host bind IP (`127.0.0.1` restricts access to the local machine). |
| `DB_PASSWORD` | none | Required PostgreSQL password. |
| `FW_PREPARE_DATABASE` | `true` | Runs migrations and production seeding during `app` container startup (`false` on `worker` and `scheduler`). |

## Database

Configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Framework Cloud targets PostgreSQL. `compose.yml` wires the `postgres` service automatically. In Docker, `.env.example` sets `DB_DATABASE` and `DB_USERNAME` to `framework_cloud`, and the `postgres` service uses the same names when they are unset. The [backup commands](maintenance.md#backups) assume these names. Outside Docker, unset values fall back to `laravel` and `root`, so set both explicitly.

## Server credentials

Credentials listed below can be set in `.env` or in **Server administration**. Non-empty environment variables take precedence. See [Server setup](server-setup.md#credential-storage).

| Integration | Environment variables |
|---|---|
| GitHub App | `GITHUB_APP_ID`, `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_WEBHOOK_SECRET`, `GITHUB_APP_PRIVATE_KEY` |
| GitHub sign-in OAuth App | `GITHUB_LOGIN_CLIENT_ID`, `GITHUB_LOGIN_CLIENT_SECRET` (optional email-only OAuth App with callback `/auth/github/login/callback` for login and registration, while repository linking always uses the GitHub App) |
| Artifact storage | `FW_BUCKET_NAME`, `FW_BUCKET_ENDPOINT`, `FW_BUCKET_REGION`, `FW_BUCKET_KEY`, `FW_BUCKET_SECRET`, `FW_DOCUMENTS_BASE_URL` |
| Email | `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` |

## GitHub scaffolding

GitHub repository scaffolding pins exact tool versions so projects created on a server build deterministically:

| Variable | Default | Description |
|---|---|---|
| `GITHUB_FRAMEWORK_SHA` | tested commit | Full 40-character commit SHA of `frameworktools/framework` for R repositories. Empty or invalid disables GitHub scaffolding. |
| `GITHUB_FRAMEWORK_PY_SHA` | tested commit | Full 40-character commit SHA of `frameworktools/framework-py` for Python repositories. Empty or invalid disables Python GitHub scaffolding. |
| `GITHUB_FW_VERSION` | tested release | Release version of `fw` (such as `0.3.0`) for Stata repositories. Empty or invalid disables Stata GitHub scaffolding. |
| `GITHUB_FW_SHA256` | release checksums | Optional 64-character SHA-256 hash of the Linux `fw` archive. Empty verifies against the release checksums file. |

When scaffolding is off, **GitHub repository** on **Projects > New project** appears disabled with the reason. Administrators also see the variable to change.

## Private storage

`FW_LOCAL_STORAGE_ROOT` sets the root path of the private `local` disk (`storage/app/private` by default). Zero-downtime release deployments must point `FW_LOCAL_STORAGE_ROOT` to a shared persistent directory outside individual release folders. Migrate existing files before switching, exclude the path from the web root, and include it in backups.

`FW_ARTIFACTS_DISK` and `FW_DOCUMENTS_DISK` accept these disks:

| Disk | Storage | Variables |
|---|---|---|
| `local` | Private local directory | `FW_LOCAL_STORAGE_ROOT` |
| `bucket` | S3-compatible bucket from **Server administration** | `FW_BUCKET_NAME`, `FW_BUCKET_ENDPOINT`, `FW_BUCKET_REGION`, `FW_BUCKET_KEY`, `FW_BUCKET_SECRET` |
| `r2` | Cloudflare R2 bucket | `R2_BUCKET` (default `framework-pub`), `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY` |
| `s3` | Amazon S3 bucket | `AWS_BUCKET`, `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` |

After changing `FW_ARTIFACTS_DISK`, run `php artisan fw:move-artifacts` to copy existing artifact files to the new disk. See [Maintenance](maintenance.md#administration-commands).

## Downloads

The `scaffolder` service generates `.zip` archives for **ZIP download** on **Projects > New project** and runs automatically in `compose.yml`.

| Variable | Default | Description |
|---|---|---|
| `SCAFFOLDER_URL` | `http://scaffolder:8000` in Docker, empty otherwise | Internal HTTP URL of the scaffolder service. Empty disables `.zip` downloads, and **ZIP download** appears disabled with the reason. |
| `SCAFFOLDER_TOKEN` | empty | Shared bearer secret between `app` and `scaffolder`. |
| `SCAFFOLDER_TIMEOUT` | `200` | Maximum seconds `app` waits for a `.zip` build. |

## Documentation sites

Framework Cloud can serve product documentation for R, Python, Stata, and the `fw` CLI on dedicated virtual hosts while serving the Cloud web app on `APP_URL`. Import documentation archives (`docs-site.tar.gz`) from local files, directories, or GitHub releases with `php artisan fw:docs-import {product} --release`. Before import, the R host serves the bundled fallback guides and reference, while Python, Stata, and CLI hosts display an unavailable notice.

| Variable | Default | Description |
|---|---|---|
| `FW_DOCS_HOST_R` | `r.framework.test` | Hostname for Framework for R documentation (falls back to `FW_DOCS_HOST` when unset). |
| `FW_DOCS_HOST_PY` | `py.framework.test` | Hostname for Framework for Python documentation. |
| `FW_DOCS_HOST_CLI` | `cli.framework.test` | Hostname for `fw` CLI documentation. |
| `FW_DOCS_HOST_STATA` | `stata.framework.test` | Hostname for Framework for Stata documentation. |
| `FW_DOCS_REPO_R`, `FW_DOCS_REPO_PY`, `FW_DOCS_REPO_CLI`, `FW_DOCS_REPO_STATA` | `frameworktools/*` | GitHub repositories queried by `fw:docs-import --release`. |
| `FW_DOCS_PRERELEASE_R`, `FW_DOCS_PRERELEASE_PY`, `FW_DOCS_PRERELEASE_CLI`, `FW_DOCS_PRERELEASE_STATA` | `fallback` | Release filter: `fallback` prefers stable releases and falls back to pre-releases, `always` picks newest overall, and `never` ignores pre-releases. |
| `FW_DOCS_PATH` | `storage/app/docs` | Storage directory for imported documentation releases. |

Configure DNS records and TLS certificates for each documentation hostname you expose.

## Package server selection

R and Python packages resolve the target Framework Cloud server in this order:

1. Explicit `url` argument, such as `cloud_login("<token>", url = "https://framework.example.edu")`.
2. Project `.env` `FW_CLOUD_URL` entry for project-scoped commands such as `publish()`.
3. Process `FW_CLOUD_URL` environment variable.
4. Last server authenticated via `cloud_login()` for account-scoped commands such as `cloud_status()` and `new_project()`.
5. `https://framework.pub`.

Credentials are stored per server URL. Run `cloud_logout(url = ...)` to remove the token for one server.
