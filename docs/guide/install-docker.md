# Install with Docker

Framework Cloud releases are published as a Docker image,
`ghcr.io/frameworktools/framework-cloud`. A Compose file runs it as three
containers:

- **app**: the web server. FrankenPHP serves the app and gets an HTTPS
  certificate from Let's Encrypt for your domain.
- **worker**: runs background jobs, such as creating GitHub repositories and
  sending email.
- **postgres**: the database.

Published notebooks, logs, certificates, and the database are kept in Docker
volumes, so they survive restarts and upgrades.

## Requirements

- Docker with the Compose plugin
- A domain whose DNS points at the machine, with ports 80 and 443 open

To run it only on your own computer instead, see
[Run it on your own computer](running-at-home.md).

## Install

Make a folder for the server and download the Compose file and the settings
template:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
```

Generate an application key:

```sh
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
```

Put the printed key in `.env` as `APP_KEY`. Keep a copy with your backups,
because values saved on the Server setup page are encrypted with it.

Then set these in `.env`:

- `APP_URL`: the address people use, such as `https://framework.example.edu`
- `SERVER_NAME`: the domain alone, such as `framework.example.edu`
- `FW_ADMIN_EMAIL`: your email address
- `DB_PASSWORD`: a long random string, such as the output of
  `openssl rand -hex 24`
- `FRAMEWORK_CLOUD_VERSION`: optional, a release such as `1.2.0`. Without it,
  the server uses the newest release.

Start the server:

```sh
docker compose up -d
```

The first start downloads the images, creates the database tables, and adds
the built-in blueprints and your admin account. Check progress with
`docker compose ps` and `docker compose logs -f app`.

## Behind a reverse proxy

If another web server already handles HTTPS on the machine, such as nginx,
Traefik, or Nginx Proxy Manager, let it pass requests to Framework Cloud over
plain HTTP. Set these in `.env`:

```sh
SERVER_NAME=:80
HTTP_PORT=8080
TRUSTED_PROXIES=*
```

Point the proxy at port 8080. `TRUSTED_PROXIES` lets the app read the
proxy's forwarded host and scheme, so it builds `https://` links. Use `*` only
when nothing but the proxy can reach the port. Otherwise list the proxy's
address, such as `TRUSTED_PROXIES=192.168.1.10`.

## Sign in

Email isn't set up yet, so print a sign-in link on the server:

```sh
docker compose exec app php artisan fw:login-link you@example.edu
```

Open the link. As an admin you see **Server setup** in the sidebar. Continue
with [Server setup](server-setup.md).

You can also set a password and sign in with it:

```sh
docker compose exec app php artisan fw:password you@example.edu
```

## Who can sign up

By default anyone who can reach the server can create an account. To stop new
sign-ups, set `FW_REGISTRATION=closed` and restart. Admins can still create
accounts:

```sh
docker compose exec app php artisan fw:login-link someone@example.edu --create
```

## Next steps

- [Server setup](server-setup.md): email, GitHub, and storage
- [Maintenance](maintenance.md): upgrades and backups
- [Configuration reference](configuration.md)

## Build the image yourself

To run code that isn't in a release, such as your own changes, build the image
from a clone of the repository:

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud/docker/selfhost
cp .env.example .env    # then fill it in as above
docker compose -f compose.yml -f compose.build.yml up -d --build
```
