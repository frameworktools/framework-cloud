#!/bin/sh
# Prepares Framework Cloud and then runs the container's command: the web
# server by default, or e.g. `php artisan queue:work` for the worker.
set -e
cd /app
rm -f /tmp/framework-cloud-ready

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one and put it in docker/selfhost/.env:" >&2
    echo "  docker compose run --rm --no-deps app php artisan key:generate --show" >&2
    # Let key:generate itself run without a key
    case "$*" in *key:generate*) exec "$@" ;; esac
    exit 1
fi

# Single-user mode: requests from this computer reach the container from the
# Docker network's gateway, whose address differs between machines, so it is
# found here unless FW_SINGLE_USER_NETWORKS is set. HTTP_BIND=127.0.0.1 keeps
# other machines from reaching the port.
if [ "${FW_SINGLE_USER:-false}" = "true" ] && [ -z "$FW_SINGLE_USER_NETWORKS" ]; then
    gateway=$(ip route | awk '/^default/ { print $3; exit }')
    export FW_SINGLE_USER_NETWORKS="127.0.0.1/32,::1/128${gateway:+,$gateway/32}"
    echo "Single-user mode: signing in requests from ${gateway:-loopback only}" >&2
fi

# Cache config, routes, and views for this start (environment included)
php artisan optimize --quiet

# Only one container prepares the database: the web server by default
if [ "${FW_PREPARE_DATABASE:-true}" = "true" ]; then
    tries=0
    until php artisan migrate --force --no-interaction; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "The database is still unreachable after 30 tries. Check DB_HOST and the postgres container." >&2
            exit 1
        fi
        echo "Waiting for the database..." >&2
        sleep 2
    done

    # Built-in blueprints (unchanged ones are skipped) and the first admin
    # (FW_ADMIN_EMAIL), on every start
    php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force --no-interaction

    # Tells compose the database is ready, so the worker can start
    touch /tmp/framework-cloud-ready
fi

# Flags go to FrankenPHP (`frankenphp run --config ...`)
exec docker-php-entrypoint "$@"
