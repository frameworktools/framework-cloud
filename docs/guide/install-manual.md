# Manual installation

Manual installation deploys Framework Cloud onto a host running PHP, PostgreSQL, and a web server. Laravel Forge environments follow this setup.

## Requirements

- PHP 8.4 with `pdo_pgsql`, `intl`, `mbstring`, `xml`, `curl`, `zip`, and `bcmath`.
- Composer 2.
- PostgreSQL 15 or later.
- Node.js 20.19 or later for building frontend assets.
- HTTPS web server such as Caddy or nginx with PHP-FPM.
- Process supervisor such as Supervisor or `systemd` for background workers.

## Installation

Clone the repository, install dependencies, build frontend assets, and generate an application key:

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.server.example .env
php artisan key:generate
```

`.env.server.example` runs the app in production mode. Back up the generated `APP_KEY` from `.env`.

Fill in the core settings in `.env`:

- `APP_URL`: public HTTPS URL, such as `https://framework.example.edu`.
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
- `FW_ADMIN_EMAIL`: email address for the initial administrator account.

Run database migrations, seed built-in blueprints and the administrator account, and cache configuration:

```sh
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan config:cache
```

Configure your web server document root to point to `public/`. The web server user needs write access to `storage/` and `bootstrap/cache/`. The app creates the OAuth signing keys for connected AI apps in `storage/` the first time an agent signs in. `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` override them.

## Queue worker and scheduler

Background tasks such as GitHub scaffolding require a supervised queue worker:

```sh
php artisan queue:work --sleep=3 --tries=1 --max-time=3600
```

Configure cron to invoke `php artisan schedule:run` every minute (or supervise `php artisan schedule:work`) so Trash cleanup, expired agent token cleanup, and GitHub scaffold status checks run on schedule.

## Project downloads

To enable `.zip` downloads on **Projects > New project**, run the isolated scaffolder container on loopback and connect the app to it:

```sh
docker run -d --name framework-scaffolder --restart unless-stopped \
  -p 127.0.0.1:8100:8000 -e SCAFFOLDER_TOKEN=choose-a-secret \
  ghcr.io/frameworktools/framework-scaffolder:latest
```

Set `SCAFFOLDER_URL=http://127.0.0.1:8100` and matching `SCAFFOLDER_TOKEN` in `.env`, then run `php artisan config:cache`. When `SCAFFOLDER_URL` is empty, **ZIP download** appears disabled.

## Sign-in

Print a one-time sign-in link on the server:

```sh
php artisan fw:login-link you@example.edu
```

Alternatively, set a password from the CLI:

```sh
php artisan fw:password you@example.edu
```

Administrator accounts display **Server administration** in the sidebar. Continue with [Server setup](server-setup.md).
