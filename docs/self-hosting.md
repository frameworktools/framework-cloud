# Self-hosting Framework Cloud

Framework Cloud is the server behind framework.pub. You can run your own copy
for a lab, a department, or yourself. A self-hosted server has every feature
turned on, including publishing, and stores only its own credentials. It
never stores your users' credentials or data.

## With Docker (recommended)

[`docker/selfhost`](../docker/selfhost/README.md) runs everything with Docker
Compose: the web server with automatic HTTPS, a queue worker, and PostgreSQL.
Follow its README, then continue at [Server setup](#server-setup) below.

## Without Docker

### What you need

- PHP 8.4 with the `pgsql`, `mbstring`, `xml`, `curl`, and `zip` extensions
- PostgreSQL 15 or later
- Node.js 20 or later, to build the frontend
- A domain with HTTPS
- A queue worker (a process manager such as Supervisor, or Laravel Forge)

### Install

```sh
git clone https://github.com/frameworktools/framework-cloud.git
cd framework-cloud
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Edit `.env`. Set at least these:

- `APP_URL`: the server's address, such as `https://framework.example.edu`
- `DB_*`: the database
- `FW_MODE=self`
- `FW_ADMIN_EMAIL`: the first admin's email address

Then create the tables, the built-in blueprints, and the admin account:

```sh
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\ProductionSeeder' --force
php artisan config:cache
```

Run a queue worker with `php artisan queue:work`.

### Sign in for the first time

Email isn't set up yet, so print a sign-in link on the server instead:

```sh
php artisan fw:login-link you@example.edu
```

Open the link. As an admin you'll see **Server setup** in the sidebar.

## Server setup

**Server setup** holds the server's three credentials:

1. **GitHub.** This lets people sign in with GitHub and create projects as
   GitHub repositories. Click **Create GitHub App**. GitHub opens with the
   name, permissions, sign-in callback, and webhook already filled in. Confirm
   there, and you come back with the app saved. Then install the app on the
   accounts or organizations where projects should go. GitHub is optional.
2. **Published documents.** By default, notebooks published with
   `framework::publish_notebook()` are stored on the server's disk. For more
   than a small group, add an S3-compatible bucket: Amazon S3, Cloudflare R2,
   MinIO, or Backblaze B2. **Test connection** writes, reads, and deletes a
   small file before you save.
3. **Email.** Sign-in links are sent by email, so set this up first. Choose
   SMTP (Postmark, Mailgun, Resend, or your institution's server) or Amazon
   SES, save, and use **Send a test email**.

### Where credentials are kept

Each credential has an environment variable, and the setup page shows its
name next to the field. Where the server looks for a credential:

1. **An environment variable that is set always wins.** The setup page shows
   it as read-only.
2. **Otherwise, the value saved on the setup page is used.** Saved values are
   encrypted with `APP_KEY`.

You can run entirely on environment variables, entirely from the setup page,
or with a mix of the two.

| Credential | Environment variables |
|---|---|
| GitHub App | `GITHUB_APP_ID`, `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_WEBHOOK_SECRET`, `GITHUB_APP_PRIVATE_KEY` |
| Documents bucket | `FW_BUCKET_NAME`, `FW_BUCKET_ENDPOINT`, `FW_BUCKET_REGION`, `FW_BUCKET_KEY`, `FW_BUCKET_SECRET`, `FW_DOCUMENTS_BASE_URL` |
| Email | `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` |

`FW_DOCUMENTS_DISK` picks the storage disk by name. When it is unset and a
bucket is saved, documents go to the bucket.

After you change an environment variable, run `php artisan config:cache`
again. Saving on the setup page takes effect right away and restarts the
queue workers.

### Back up APP_KEY

Values saved on the setup page are encrypted with `APP_KEY`. Keep a copy of
the key with your database backups. If the key changes, saved values can't be
read. The setup page lists them, and you enter them again.

To rotate the key without losing them, put the old key in
`APP_PREVIOUS_KEYS`, and saved values stay readable.

## Publishing on your own domain

A document is a web page that someone uploaded, and it can contain scripts.
Serving documents from the server's own domain lets those scripts run next
to people's sign-ins. For a server that more than one person uses, attach a
separate public domain to the bucket, such as `files.example.edu`, and set it
as the **Public address**. Documents then load from that domain.

## Updating

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
```
