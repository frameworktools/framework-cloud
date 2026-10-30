# Self-hosting overview

You can run your own Framework Cloud server. A self-hosted server includes
every feature, including publishing. It stores only its own credentials: its
GitHub App, its storage bucket, and its email account. It never stores your
users' credentials or data.

## Choose a setup

| Setup | For | Sign-in |
|---|---|---|
| [Docker](install-docker.md) | A lab, department, or institution | Accounts, with email links, passkeys, GitHub, or passwords |
| [Your own computer](running-at-home.md) | One person | None: single-user mode signs you in |
| [Without Docker](install-manual.md) | Servers you already run PHP on, or Laravel Forge | Accounts |

## What you need

- For Docker: a machine with Docker and the Compose plugin.
- For a shared server: a domain name that points at the machine, with ports 80
  and 443 open. The server gets an HTTPS certificate on its own.
- Optional: an email service, a GitHub account for the GitHub App, and an
  S3-compatible bucket. You can add these later from the **Server setup**
  page.

## After installing

1. Sign in as the admin. See the install page for how.
2. Open **Server setup** and set up [email, GitHub, and storage](server-setup.md).
3. Tell R where your server is. Each user adds this line to their `.Renviron`
   file:

   ```sh
   FW_CLOUD_URL=https://framework.example.edu
   ```

4. Follow [Getting started](getting-started.md).
