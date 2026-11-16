# Docker installation

Framework Cloud releases are published as `ghcr.io/frameworktools/framework-cloud`. The standard `compose.yml` stack runs five services:

- **app**: FrankenPHP web server that serves the application and provisions Let's Encrypt TLS certificates for `SERVER_NAME`.
- **worker**: background queue worker (`php artisan queue:work`) for GitHub repository creation and updates.
- **scheduler**: cron worker (`php artisan schedule:work`) that purges expired Trash items and stale agent sign-ins and records finished GitHub scaffold runs.
- **scaffolder**: read-only internal service (`ghcr.io/frameworktools/framework-scaffolder`) running `fw`, the `framework` R package, and `framework-py` to build `.zip` project downloads.
- **postgres**: PostgreSQL 17 database.

Published artifacts, TLS certificates, and database files persist in named Docker volumes across container restarts and image upgrades.

## Requirements

- Docker Engine with the Compose plugin.
- A domain name resolving to the host with ports 80 and 443 open.

For a private single-user setup on `localhost`, see [Local installation](running-at-home.md).

## Installation

Create a directory for the deployment and download `compose.yml` and `.env.example`:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
```

Generate an application encryption key:

```sh
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
```

Set `APP_KEY` in `.env` to the printed value and store a backup copy. Credentials saved in **Server administration** are encrypted with `APP_KEY`.

Configure the required settings in `.env`:

- `APP_URL`: external HTTPS URL, such as `https://framework.example.edu`.
- `SERVER_NAME`: bare hostname for TLS provisioning, such as `framework.example.edu`.
- `FW_ADMIN_EMAIL`: email address for the initial administrator account.
- `DB_PASSWORD`: random database password, such as `openssl rand -hex 24`.
- `FRAMEWORK_CLOUD_VERSION`: optional version tag such as `1.2.0` (`latest` when omitted).

Start the stack:

```sh
docker compose up -d
```

On first boot, `app` runs database migrations, seeds built-in blueprints and structures, and creates the administrator account before `worker` and `scheduler` start. Inspect container status with `docker compose ps` and `docker compose logs -f app`.

## Reverse proxy configuration

When an existing reverse proxy (such as nginx, Caddy, or Traefik) terminates HTTPS on the host, configure Framework Cloud to listen on plain HTTP behind it:

```sh
SERVER_NAME=:80
HTTP_PORT=8080
HTTP_BIND=127.0.0.1
TRUSTED_PROXIES=*
```

Forward traffic from the proxy to port 8080. `TRUSTED_PROXIES` instructs Laravel to honor `X-Forwarded-Proto` and `X-Forwarded-For` headers when generating HTTPS URLs. Set `TRUSTED_PROXIES=*` only when firewall or binding rules prevent direct external connections to `HTTP_PORT`. Otherwise specify the proxy IP address or CIDR range.

## Sign-in

Before configuring outbound email, generate a one-time administrator sign-in link:

```sh
docker compose exec app php artisan fw:login-link you@example.edu
```

Open the printed URL in your browser. Administrator accounts display **Server administration** in the sidebar. Continue with [Server setup](server-setup.md).

You can also assign a password directly from the CLI:

```sh
docker compose exec app php artisan fw:password you@example.edu
```

## Account registration

Open registration (`FW_REGISTRATION=open`) is enabled by default so anyone who can reach the server can register. Set `FW_REGISTRATION=closed` in `.env` and run `docker compose up -d` to restrict sign-ups. Administrators can still provision accounts manually:

```sh
docker compose exec app php artisan fw:login-link someone@example.edu --create
```

## Next steps

- [Server setup](server-setup.md): configure email, GitHub, artifact storage, and connected agents.
- [Maintenance](maintenance.md): run upgrades, backups, and CLI commands.
- [Configuration reference](configuration.md): review environment variables.

## Custom Docker images

To deploy local modifications from a repository checkout, layer `compose.build.yml` on top of `compose.yml`. It builds the app image and the scaffolder from source, and `app`, `worker`, and `scheduler` all run the local app image:

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud/docker/selfhost
cp .env.example .env    # populate required variables
docker compose -f compose.yml -f compose.build.yml up -d --build
```
