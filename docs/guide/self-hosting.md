# Self-hosting overview

Framework Cloud is open source, and you can run your own copy. A self-hosted
server includes every feature, including publishing for every account. It
keeps its own credentials for email, its GitHub App, and its storage bucket.

Source datasets and analysis code stay on your users' computers and in their
repositories. Connected features send project settings, blueprints, and, with
the integrity ledger, file fingerprints with the dataset name, file size,
time, and computer name. Publishing uploads the rendered notebook and everything
in it, including code, tables, and figures, to the server or its storage bucket.

## How it is packaged

Each release of Framework Cloud is published as a Docker image:

```text
ghcr.io/frameworktools/framework-cloud
```

The image holds the web server and the application. A Compose file runs it as
three containers:

- **app**: the web server, which gets an HTTPS certificate for your domain
- **worker**: background jobs, such as creating GitHub repositories and
  sending email
- **postgres**: the database

You download two files, `compose.yml` and `.env.example`, fill in the settings,
and start it. You don't need to clone the repository or build anything.

## Choose a setup

| Setup | For | Sign-in |
|---|---|---|
| [Install with Docker](install-docker.md) | A lab, department, or institution | Accounts, with email links, passkeys, passwords, or GitHub |
| [Run it on your own computer](running-at-home.md) | One person | None: single-user mode signs you in |
| [Install without Docker](install-manual.md) | Servers that already run PHP, or Laravel Forge | Accounts |

## What you need

- A machine with Docker and the Compose plugin.
- For a shared server, a domain name that points at the machine, with ports 80
  and 443 open.
- Optional: an email service, a GitHub account for the GitHub App, and an
  S3-compatible bucket. You can add these later on the **Server setup** page.

## After installing

1. Sign in as the admin. The install page explains how.
2. Open **Server setup** and set up [email, GitHub, and storage](server-setup.md).
3. Connect R. Each user creates a token on the **API tokens** page and runs
   the command shown there, which includes the server's address:

   ```r
   framework::cloud_login("<your token>", url = "https://framework.example.edu")
   ```

4. Follow [Getting started](getting-started.md).

## Versions

Release images are tagged by version, such as `1.2.0`, and `latest` points at
the newest release. Set `FRAMEWORK_CLOUD_VERSION` in `.env` to choose one.
[Maintenance](maintenance.md#upgrade) explains upgrades.
