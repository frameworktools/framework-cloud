# Self-hosting Framework Cloud with Docker

This directory runs Framework Cloud with Docker Compose in three containers:

- **app**: the web server. FrankenPHP serves the app and gets an HTTPS
  certificate from Let's Encrypt for your domain.
- **worker**: runs queued jobs, such as creating GitHub repositories.
- **postgres**: the database.

Published documents, logs, certificates, and the database are kept in Docker
volumes, so they survive restarts and upgrades.

## Requirements

- Docker with the Compose plugin
- For HTTPS: a domain whose DNS points at this machine, with ports 80 and 443
  open

## Start

From this directory:

```sh
cp .env.example .env
docker compose run --rm --no-deps app php artisan key:generate --show
```

Put the printed key in `.env` as `APP_KEY`, and keep a copy with your backups.
Then fill in:

- `APP_URL` and `SERVER_NAME`: your address, such as
  `https://framework.example.edu` and `framework.example.edu`
- `FW_ADMIN_EMAIL`: your email address
- `DB_PASSWORD`: a long random string, such as the output of
  `openssl rand -hex 24`

Start it:

```sh
docker compose up -d --build
```

The first start builds the image, creates the database tables, and adds the
built-in blueprints and your admin account.

## Sign in

Email isn't set up yet, so print a sign-in link:

```sh
docker compose exec app php artisan fw:login-link you@example.edu
```

Open the link, then open **Server setup** in the sidebar to set up email,
GitHub, and storage for published documents.
[Server setup](../../docs/guide/server-setup.md) explains each step. The full
guide is in [`docs/guide`](../../docs/guide/index.md), including
[running it on your own computer](../../docs/guide/running-at-home.md) in
single-user mode.

## Try it on your own computer

To try it without a domain, set these in `.env`:

```sh
APP_URL=http://localhost:8080
SERVER_NAME=:80
HTTP_PORT=8080
```

Then open http://localhost:8080. To use it as your own private server with
no sign-in screen, see
[Run it on your own computer](../../docs/guide/running-at-home.md).

## Everyday commands

```sh
docker compose logs -f app                      # web server and app logs
docker compose exec app php artisan <command>   # any artisan command
docker compose exec app php artisan fw:publishing someone@example.edu
docker compose down                             # stop (data is kept)
```

## Upgrade

```sh
git pull
docker compose up -d --build
```

Each start runs new migrations and updates the built-in blueprints.
Blueprints that haven't changed get no new version.

## Back up

Back up the database and the `storage` volume, which holds published
documents unless you use a bucket:

```sh
docker compose exec postgres pg_dump -U framework_cloud framework_cloud > backup.sql
docker run --rm -v framework-cloud_storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/storage.tgz -C /data .
```

Keep `APP_KEY` with the backups. Credentials saved on the Server setup page are
encrypted with it.
