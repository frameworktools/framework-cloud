# Self-hosting overview

Framework Cloud is open source. A self-hosted server includes artifact publishing
for every account. It stores its own email, GitHub App, and storage credentials.

Source datasets and analysis code stay on your users' computers and in their
repositories. Connected features send project settings, blueprints, and, with
the integrity ledger, file fingerprints with the dataset name, file size,
time, and computer name. Publishing uploads the selected artifact and its
supporting files to the server or its storage bucket. Rendered notebooks can
include code, tables, and figures.

## Server components

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

## Installation options

| Setup | For | Sign-in |
|---|---|---|
| [Install with Docker](install-docker.md) | A lab, department, or institution | Accounts, with email links, passkeys, passwords, or GitHub |
| [Run it on your own computer](running-at-home.md) | One person | None: single-user mode signs you in |
| [Install without Docker](install-manual.md) | Servers that already run PHP, or Laravel Forge | Accounts |

## Requirements

- A machine with Docker and the Compose plugin.
- For a shared server, a domain name that points at the machine, with ports 80
  and 443 open.
- Optional: an email service, a GitHub account for the GitHub App, and an
  S3-compatible bucket. You can add these later under **Server administration**.

## Configuration

1. Sign in as the admin. The install page explains how.
2. Open **Server administration** and set up [email, GitHub, and storage](server-setup.md).
3. Connect R or Python. Each user creates a token on the **API tokens** page
   and runs the command shown there, which includes the server's address:

   ```r
   framework::cloud_login("<your token>", url = "https://framework.example.edu")
   ```

   ```python
   import framework

   framework.cloud_login("<your token>", url="https://framework.example.edu")
   ```

4. Follow [Getting started](getting-started.md).

## Versions

Release images are tagged by version, such as `1.2.0`, and `latest` points at
the newest release. Set `FRAMEWORK_CLOUD_VERSION` in `.env` to choose one.
[Maintenance](maintenance.md#upgrades) explains upgrades.
