# Run it on your own computer

You can run Framework Cloud on your own computer for yourself alone. Your
blueprints, settings, and published notebooks then stay on your machine.

Single-user mode removes the sign-in screen. Every visit from your own
computer is signed in as you.

## With Docker

Download the Compose file and the settings template, and generate a key:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
```

Put the key in `.env` as `APP_KEY`, then set these:

```sh
APP_URL=http://localhost:8080
SERVER_NAME=:80
HTTP_PORT=8080
HTTP_BIND=127.0.0.1

FW_ADMIN_EMAIL=you@example.com
FW_REGISTRATION=closed

FW_SINGLE_USER=true

DB_PASSWORD=<a long random string>
```

Start it and open http://localhost:8080:

```sh
docker compose up -d
```

Create a token on the **API tokens** page and connect R or Python:

```r
framework::cloud_login("<your token>", url = "http://localhost:8080")
```

```python
import framework

framework.cloud_login("<your token>", url="http://localhost:8080")
```

Both packages allow plain `http` only for localhost addresses.

### Why HTTP_BIND matters

Inside Docker, requests from your browser arrive from the Docker network's
gateway instead of from 127.0.0.1. The container finds that address when it
starts and treats it as your computer. The app log shows it:

```text
Single-user mode: signing in requests from 192.168.148.1
```

Anything that reaches the port arrives from that same address, so
`HTTP_BIND=127.0.0.1` is what keeps other machines out. Keep it set whenever
single-user mode is on. Check it with `docker compose ps`, which should show
`127.0.0.1:8080->80/tcp`.

## Without Docker

Install the app as in [Install without Docker](install-manual.md), then set
these in `.env`:

```sh
APP_URL=http://localhost:8000
FW_ADMIN_EMAIL=you@example.com
FW_REGISTRATION=closed
FW_SINGLE_USER=true
```

Create your account and start the server:

```sh
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan serve
```

`php artisan serve` listens only on 127.0.0.1, so the default networks are
enough. Run `php artisan queue:work` in a second terminal for background jobs.

## How single-user mode decides

A request is signed in automatically only when all of these are true:

- `FW_SINGLE_USER` is `true`.
- `APP_URL` is a localhost address, such as `http://localhost:8080`.
- The connection comes from one of `FW_SINGLE_USER_NETWORKS`. The default is
  127.0.0.1 and ::1, plus the Docker gateway in the Docker setup.
- The request has no proxy headers, such as `X-Forwarded-For`. Requests that
  come through a tunnel, such as Expose or ngrok, are never signed in.
- The request is addressed to `localhost` or 127.0.0.1.

The account it signs in is `FW_ADMIN_EMAIL`, or the first admin if that isn't
set. In single-user mode, **Sign out** is hidden, because the next page would
sign you back in.

To share the server with other people later, turn single-user mode off, give
the server a domain, and follow [Install with Docker](install-docker.md).
