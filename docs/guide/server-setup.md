# Server setup

Admins see **Server administration** in the sidebar. It holds the server's three
credentials: email, GitHub, and storage for published content. Each one is
optional, but sign-in links need email.

## Email

Sign-in links and password resets are sent by email, so set this up first.

1. Choose **SMTP** (Postmark, Mailgun, Resend, or your institution's mail
   server) or **Amazon SES**.
2. Enter the sender address and the account details, and save.
3. Click **Send a test email**.

Until email works, sign in with `php artisan fw:login-link` or a password.

## GitHub

GitHub lets people sign in with GitHub and create projects as repositories.

1. Click **Create GitHub App**. GitHub opens with the app's name,
   permissions, sign-in callback, and webhook already filled in.
2. Choose whether the app belongs to your account or an organization, and
   confirm. GitHub sends you back with the app saved.
3. Install the app on the accounts or organizations where projects should go.

Each user then connects their own GitHub account in **Account > GitHub**.

## Published artifacts

New artifacts are stored on the private `local` filesystem disk by default.
Framework Cloud serves their reader pages, originals, and supporting files
through application routes so passwords apply to every request. Uploaded HTML
runs with a sandbox policy that isolates it from the application's origin.

`FW_ARTIFACTS_DISK` selects another disk defined in `config/filesystems.php`.
It must be private, without a public bucket endpoint or web-accessible storage
directory. For multiple application nodes, use shared private storage. Back up
this disk together with the database. The database holds metadata and version
history. The disk holds the outputs.

The storage credentials in Server administration configure the older
`FW_DOCUMENTS_DISK` path. They do not change `FW_ARTIFACTS_DISK`.

### Notebook bucket

By default, published artifacts are stored on the server's disk. For more
than a few people, use an S3-compatible bucket: Amazon S3, Cloudflare R2,
MinIO, or Backblaze B2.

1. Enter the bucket name, endpoint, region, and access keys.
2. Click **Test connection**. It writes, reads, and deletes a small file.
3. Save.

### Notebook domain

A published notebook is a web page that someone uploaded, and it can contain
scripts. On a server that more than one person uses, serve notebooks from a
separate domain, such as `files.example.edu`. Attach that domain to the
bucket and enter it as the **Public address**. Notebooks then load from that
domain instead of the server's own.

## Credential storage

Each credential has an environment variable, and the setup page shows its
name next to the field. The server looks for a credential in this order:

1. **An environment variable that is set.** The setup page shows it as
   read-only.
2. **The value saved on the setup page.** Saved values are encrypted with
   `APP_KEY`.

You can run entirely on environment variables, entirely from the setup page,
or with a mix. The variables are listed in the
[Configuration reference](configuration.md#server-credentials).

Saving on the setup page takes effect right away and restarts the queue
worker. After you change an environment variable, restart the containers, or
run `php artisan config:cache` without Docker.

## Application key protection

Values saved on the setup page are encrypted with `APP_KEY`. If the key
changes, saved values can't be read. The setup page then lists them so you can
enter them again.

To change the key without losing saved values, put the old key in
`APP_PREVIOUS_KEYS`.
