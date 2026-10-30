# Maintenance

## Upgrade

With Docker, from the folder with `compose.yml`:

```sh
docker compose pull
docker compose up -d
```

This moves to the newest release, unless `FRAMEWORK_CLOUD_VERSION` in `.env`
names a version. To move to a pinned version, change that line first. Read
the release notes on
[GitHub](https://github.com/frameworktools/framework-cloud/releases) before
upgrading. If you build the image yourself, run `git pull` and then
`docker compose -f compose.yml -f compose.build.yml up -d --build`.

Each start runs new database migrations and updates the built-in blueprints.
A built-in blueprint that hasn't changed gets no new version.

Without Docker:

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan config:cache
php artisan queue:restart
```

## Back up

Back up three things:

- the database
- published notebooks, if they are stored on the server's disk
- `APP_KEY`, which decrypts the credentials saved on the Server setup page

With Docker, from the folder with `compose.yml`:

```sh
docker compose exec postgres pg_dump -U framework_cloud framework_cloud > backup.sql
docker run --rm -v framework-cloud_storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/storage.tgz -C /data .
```

Without Docker, use `pg_dump` and copy the `storage/app` folder.

To restore with Docker, start only the database, load the dump, and restore
the storage volume:

```sh
docker compose up -d postgres
docker compose exec -T postgres psql -U framework_cloud framework_cloud < backup.sql
docker run --rm -v framework-cloud_storage:/data -v "$PWD":/backup alpine \
  tar xzf /backup/storage.tgz -C /data
docker compose up -d
```

## Admin commands

Run these with `php artisan` on the server. With Docker, put
`docker compose exec app` in front.

| Command | What it does |
|---|---|
| `fw:login-link <email>` | Prints a one-time sign-in link. `--create` creates the account first. |
| `fw:password <email>` | Sets an account's password. `--create` creates the account first. |
| `fw:publishing <email>` | Lets an account publish on a hosted server. `--off` takes it away. Self-hosted servers don't need it. |
| `queue:restart` | Restarts the queue worker after a change. |

## Logs

With Docker, `docker compose logs -f app` shows the web server and the app,
and `docker compose logs -f worker` shows background jobs. Without Docker,
the app writes to `storage/logs`.
