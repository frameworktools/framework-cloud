# Install with Docker

The Docker setup in
[`docker/selfhost`](https://github.com/frameworktools/framework-cloud/tree/main/docker/selfhost)
runs three containers:

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

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud/docker/selfhost
cp .env.example .env
```

Generate an application key:

```sh
docker compose run --rm --no-deps app php artisan key:generate --show
```

Put the printed key in `.env` as `APP_KEY`. Keep a copy with your backups,
because values saved on the Server setup page are encrypted with it.

Then set these in `.env`:

- `APP_URL`: the address people use, such as `https://framework.example.edu`
- `SERVER_NAME`: the domain alone, such as `framework.example.edu`
- `FW_ADMIN_EMAIL`: your email address
- `DB_PASSWORD`: a long random string, such as the output of
  `openssl rand -hex 24`

Start the server:

```sh
docker compose up -d --build
```

The first start builds the image, creates the database tables, and adds the
built-in blueprints and your admin account. It takes a minute or two. Check
progress with `docker compose ps` and `docker compose logs -f app`.

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
