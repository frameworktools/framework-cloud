# Configuration reference

Framework Cloud reads its settings from environment variables, usually in the
`.env` file. With Docker, that is `docker/selfhost/.env`. After changing it,
restart with `docker compose up -d`. Without Docker, run
`php artisan config:cache`.

## Server

| Variable | Default | What it does |
|---|---|---|
| `APP_KEY` | none | Encryption key. Required. Generate it once and back it up. |
| `APP_PREVIOUS_KEYS` | none | Old keys, comma-separated, so saved credentials stay readable after you change `APP_KEY`. |
| `APP_URL` | none | The address people use, such as `https://framework.example.edu`. |
| `FW_MODE` | `self` | `self` for your own server, where every feature is on. `hosted` is for framework.pub. |
| `FW_ADMIN_EMAIL` | none | The admin account, created on first start. |
| `FW_ADMIN_NAME` | from the email | The admin's name when the account is created. |
| `FW_REGISTRATION` | `open` | `closed` stops new sign-ups. Admins can still create accounts with `fw:login-link --create`. |
| `FW_DOCUMENTS_DISK` | `local` | Storage for published notebooks. Becomes `bucket` when a bucket is saved on the setup page. |

## Sign-in

| Variable | Default | What it does |
|---|---|---|
| `FW_PASSWORDS` | `true` when `FW_MODE=self` | Allows password sign-in alongside email links, passkeys, and GitHub. |
| `FW_SINGLE_USER` | `false` | Signs every local request in as the admin. See [Run it on your own computer](running-at-home.md). |
| `FW_SINGLE_USER_NETWORKS` | `127.0.0.1/32,::1/128` | Addresses that count as this computer in single-user mode, comma-separated. The Docker setup adds its network's gateway when this is unset. |

## Docker

These apply only to `docker/selfhost`.

| Variable | Default | What it does |
|---|---|---|
| `SERVER_NAME` | none | The domain to get an HTTPS certificate for. `:80` serves plain HTTP. |
| `HTTP_PORT` | `80` | The machine's port for HTTP. |
| `HTTPS_PORT` | `443` | The machine's port for HTTPS. |
| `HTTP_BIND` | `0.0.0.0` | The address the ports listen on. `127.0.0.1` makes the server reachable only from this machine. |
| `DB_PASSWORD` | none | The database password. Required. |
| `FW_PREPARE_DATABASE` | `true` | Runs migrations and seeding on start. |

## Database

`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and
`DB_PASSWORD`. Framework Cloud is built for PostgreSQL. The Docker setup fills
in the connection details for its own database container.

## Server credentials

Each of these can be set here or on the Server setup page. A variable that is
set wins. See [Server setup](server-setup.md#where-credentials-are-kept).

| Credential | Variables |
|---|---|
| GitHub App | `GITHUB_APP_ID`, `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_WEBHOOK_SECRET`, `GITHUB_APP_PRIVATE_KEY` |
| Notebook storage | `FW_BUCKET_NAME`, `FW_BUCKET_ENDPOINT`, `FW_BUCKET_REGION`, `FW_BUCKET_KEY`, `FW_BUCKET_SECRET`, `FW_DOCUMENTS_BASE_URL` |
| Email | `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` |

## R

R finds the server through one variable, set in each user's `.Renviron`:

| Variable | Default | What it does |
|---|---|---|
| `FW_CLOUD_URL` | `https://framework.pub` | The Framework Cloud server that `cloud_login()`, `setup()`, and `publish()` use. |
