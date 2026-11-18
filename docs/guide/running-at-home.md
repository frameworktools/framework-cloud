# Local installation

Framework Cloud can run locally on a personal computer so blueprints, project definitions, and published artifacts stay on your machine.

Single-user mode bypasses the sign-in screen and authenticates local browser requests automatically as the administrator account.

## Docker installation

Download `compose.yml` and `.env.example`, then generate an encryption key:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
```

Set `APP_KEY` in `.env` to the generated key and configure local single-user settings:

```sh title=".env"
APP_URL=http://localhost:8080
SERVER_NAME=:80
HTTP_PORT=8080
HTTP_BIND=127.0.0.1

FW_ADMIN_EMAIL=you@example.com
FW_REGISTRATION=closed

FW_SINGLE_USER=true

DB_PASSWORD=<random-password>
```

Start the five Compose services (`app`, `worker`, `scheduler`, `scaffolder`, and `postgres`) and open `http://localhost:8080`:

```sh
docker compose up -d
```

Create a personal token in **Account > Computers & API tokens** (or approve a browser login) to connect R and Python:

```r
framework::cloud_login("<your token>", url = "http://localhost:8080")
```

```python
import framework

framework.cloud_login("<your token>", url="http://localhost:8080")
```

Both packages allow unencrypted `http://` URLs exclusively for `localhost` and loopback addresses.

### Network binding

Inside Docker, host browser connections arrive from the Docker bridge gateway IP rather than `127.0.0.1`. At container startup, Framework Cloud detects the bridge gateway and appends it to the allowed single-user networks. Container logs record the detected address:

```text title="Container log"
Single-user mode: signing in requests from 192.168.148.1
```

Because every connection forwarded to the container shares that gateway source IP, `HTTP_BIND=127.0.0.1` is essential to block LAN access when `FW_SINGLE_USER=true`. Verify the port binding with `docker compose ps`, which must list `127.0.0.1:8080->80/tcp`.

## Manual installation

Follow the build steps in [Manual installation](install-manual.md), then configure `.env` for local single-user operation:

```sh title=".env"
APP_URL=http://localhost:8000
FW_ADMIN_EMAIL=you@example.com
FW_REGISTRATION=closed
FW_SINGLE_USER=true
```

Rebuild the configuration cache so the app reads these settings, then seed the administrator account and start the local PHP server:

```sh
php artisan config:cache
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan serve
```

`php artisan serve` binds to `127.0.0.1` by default. Run `php artisan queue:work` and `php artisan schedule:work` in separate processes if you use background jobs or scheduled Trash cleanup.

## Single-user authentication

Framework Cloud signs a request in automatically only when every condition holds:

- `FW_SINGLE_USER` is `true`.
- `APP_URL` uses a `localhost` or loopback host.
- The client IP matches `FW_SINGLE_USER_NETWORKS` (`127.0.0.1/32` and `::1/128` by default, plus the Docker gateway in the container entrypoint).
- No forwarded proxy headers such as `X-Forwarded-For` are present, preventing automatic sign-in across tunnels such as Cloudflare Tunnel, Expose, or ngrok.
- The HTTP `Host` header resolves to `localhost` or `127.0.0.1`.

Requests sign in as `FW_ADMIN_EMAIL` (or the first administrator record when unset). **Sign out** is hidden while single-user mode is active.

To convert a local installation into a shared multi-user server later, set `FW_SINGLE_USER=false`, configure a domain and TLS settings, and follow [Docker installation](install-docker.md).
