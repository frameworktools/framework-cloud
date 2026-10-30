# Install without Docker

Use this setup on a server where you already run PHP applications, or with a
service such as Laravel Forge.

## Requirements

- PHP 8.4 with the `pdo_pgsql`, `intl`, `mbstring`, `xml`, `curl`, `zip`, and
  `bcmath` extensions
- Composer
- PostgreSQL 15 or later
- Node.js 20 or later, to build the frontend
- A web server with HTTPS, such as Caddy or nginx with PHP-FPM
- A process manager for the queue worker, such as Supervisor or systemd

## Install

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Keep a copy of `APP_KEY` from `.env` with your backups.

Edit `.env`. Set at least these:

- `APP_URL`: the server's address, such as `https://framework.example.edu`
- `DB_CONNECTION=pgsql` and the other `DB_*` values
- `FW_MODE=self`
- `FW_ADMIN_EMAIL`: the first admin's email address

Create the tables, the built-in blueprints, and the admin account, then cache
the configuration:

```sh
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan config:cache
```

Point the web server's document root at the `public` folder.

## Run the queue worker

Background jobs, such as creating GitHub repositories and sending email, run
in a queue worker. Keep this command running with your process manager:

```sh
php artisan queue:work --sleep=3 --tries=1 --max-time=3600
```

## Sign in

Print a sign-in link on the server:

```sh
php artisan fw:login-link you@example.edu
```

Or set a password and sign in with it:

```sh
php artisan fw:password you@example.edu
```

As an admin you see **Server setup** in the sidebar. Continue with
[Server setup](server-setup.md).
