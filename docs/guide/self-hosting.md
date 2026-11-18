# Self-hosting overview

Framework Cloud is open source. A self-hosted server includes artifact publishing, collections, reports, and blueprints for every account and manages its own email, GitHub App, and storage credentials.

Source datasets and analysis scripts remain on user computers and in their repositories. Connected clients synchronize project definitions, blueprints, and optional dataset ledger metadata (dataset name, SHA-256 fingerprint, byte size, timestamp, and machine name). Publishing uploads selected artifacts and supporting files to the server or its configured bucket. Rendered notebooks can contain code, tables, and figures.

## Server components

Each Framework Cloud release publishes container images to GitHub Container Registry:

```text title="Container images"
ghcr.io/frameworktools/framework-cloud
ghcr.io/frameworktools/framework-scaffolder
```

`compose.yml` runs five services:

- **app**: FrankenPHP web server and application process, including automatic Let's Encrypt HTTPS certificates.
- **worker**: background queue processor for GitHub scaffolding.
- **scheduler**: periodic task runner for Trash retention cleanup, expired agent authorization cleanup, and GitHub scaffold status checks.
- **scaffolder**: isolated project archive builder for `.zip` project downloads.
- **postgres**: PostgreSQL 17 database.

Deployment requires only `compose.yml` and `.env.example` without cloning the repository or compiling assets locally.

## Installation options

| Setup | Target environment | Authentication |
|---|---|---|
| [Docker installation](install-docker.md) | Lab, department, or institution server | Multi-user accounts with email links, passkeys, passwords, or GitHub |
| [Local installation](running-at-home.md) | Single personal machine | Automatic local sign-in in single-user mode |
| [Manual installation](install-manual.md) | Existing PHP infrastructure or Laravel Forge | Multi-user accounts |

## Requirements

- Linux, macOS, or Windows host with Docker and the Docker Compose plugin.
- Public DNS record pointing at the host with TCP ports 80 and 443 and UDP port 443 reachable (for shared HTTPS servers).
- Optional SMTP or Amazon SES credentials, GitHub App credentials, and S3-compatible object storage. Configure these integrations after startup under **Server administration**.

## Initial configuration

1. Sign in with the administrator account created during installation.
2. Open **Server administration** in the sidebar to configure [email, GitHub, artifact storage, and connected agents](server-setup.md).
3. Connect R, Python, Stata, or `fw` by passing your server URL to `cloud_login()`, `fw_login`, or `fw login`. You can also generate a personal token in **Account > Computers & API tokens**:

   ```r
   framework::cloud_login("<your token>", url = "https://framework.example.edu")
   ```

   ```python
   import framework

   framework.cloud_login("<your token>", url="https://framework.example.edu")
   ```

4. Follow [Getting started](getting-started.md) to configure defaults and create a project.

## Versions

Release images carry semantic version tags such as `1.2.0` alongside `latest`. Set `FRAMEWORK_CLOUD_VERSION` in `.env` to pin a specific release. See [Maintenance](maintenance.md#upgrades) for upgrade steps.
