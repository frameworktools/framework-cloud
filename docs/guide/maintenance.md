# Maintenance

## Upgrades

In Docker deployments, pull updated images and recreate the five services (`app`, `worker`, `scheduler`, `scaffolder`, and `postgres`) from the directory containing `compose.yml`:

```sh
docker compose pull
docker compose up -d
```

When `FRAMEWORK_CLOUD_VERSION` is unset, `docker compose pull` fetches `latest`. When `FRAMEWORK_CLOUD_VERSION` is pinned, update it in `.env` first. Review the [GitHub release notes](https://github.com/frameworktools/framework-cloud/releases) before upgrading. Source-built deployments upgrade with `git pull` followed by `docker compose -f compose.yml -f compose.build.yml up -d --build`.

Container startup runs pending database migrations and updates built-in blueprints. Unchanged built-in blueprints retain their current version number.

In manual deployments, pull the release commit, rebuild assets, migrate, seed, cache config, and restart workers:

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan config:cache
php artisan queue:restart
```

## Backups

Include these items in every backup:

- PostgreSQL database dump.
- Local storage files (`storage/app` or `FW_LOCAL_STORAGE_ROOT`) holding private artifacts and imported docs.
- Object-storage bucket contents, when `FW_ARTIFACTS_DISK` or `FW_DOCUMENTS_DISK` names a bucket disk. Saving a bucket in **Server administration** switches both variables to `bucket` unless `.env` sets them.
- OAuth signing keys for connected AI apps: `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`, or the `storage/oauth-private.key` and `storage/oauth-public.key` files.
- `APP_KEY` (and `APP_PREVIOUS_KEYS` if set), required to decrypt credentials saved in **Server administration**.

In Docker deployments, dump PostgreSQL and archive the `storage` volume from the directory containing `compose.yml`. The volume includes the OAuth key files. The commands assume the default `DB_USERNAME` and `DB_DATABASE` of `framework_cloud`:

```sh
docker compose exec -T postgres pg_dump -U framework_cloud framework_cloud > backup.sql
docker run --rm -v framework-cloud_storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/storage.tgz -C /data .
```

In manual deployments, run `pg_dump` and archive `storage/app` (plus any custom `FW_LOCAL_STORAGE_ROOT`). The OAuth key files sit in `storage/`, outside `storage/app`, so copy them separately.

To restore a Docker backup, start `postgres`, load the SQL dump, extract the storage archive, and start the remaining services:

```sh
docker compose up -d postgres
docker compose exec -T postgres psql -U framework_cloud framework_cloud < backup.sql
docker run --rm -v framework-cloud_storage:/data -v "$PWD":/backup alpine \
  tar xzf /backup/storage.tgz -C /data
docker compose up -d
```

## Administration commands

Run Artisan commands directly on manual hosts or prefix them with `docker compose exec app` in Docker deployments:

| Command | Description |
|---|---|
| `fw:login-link <email>` | Prints a single-use sign-in URL (`--create` provisions the account if absent). |
| `fw:password <email>` | Prompts to set an account password (`--create` provisions the account if absent). |
| `fw:publishing <email>` | Grants publishing access in `hosted` mode (`--off` revokes it, and `FW_MODE=self` already grants it). |
| `fw:docs-import <product>` | Imports a documentation bundle (`--release` downloads from GitHub Releases). |
| `fw:move-artifacts` | Copies stored artifact files, including trashed versions, to `FW_ARTIFACTS_DISK` (`--to` names another disk). Run it after changing `FW_ARTIFACTS_DISK`. `--dry-run` lists the moves, and `--delete-source` removes each version's old files after it moves. |
| `fw:purge-trash` | Permanently deletes items in Trash longer than `FW_TRASH_DAYS` (`--days` overrides it for one run). The `scheduler` runs it daily. |
| `fw:refresh-scaffolds` | Records finished GitHub scaffold runs whose webhook did not arrive. The `scheduler` runs it every minute. |
| `queue:restart` | Signals running queue workers to stop after completing their current job. |

## Logs

In Docker deployments, inspect logs per service with `docker compose logs -f app`, `docker compose logs -f worker`, and `docker compose logs -f scheduler`. In manual deployments, application logs write to `storage/logs/`.
