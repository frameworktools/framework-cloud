# Docker self-hosting

This directory holds the files for running Framework Cloud with Docker:

- `compose.yml` runs five services (`app`, `worker`, `scheduler`, `scaffolder`, and `postgres`).
- `.env.example` provides the environment configuration template.
- `compose.build.yml` builds the images from this repository rather than pulling a release. `app`, `worker`, and `scheduler` all run the local build.
- `Dockerfile`, `entrypoint.sh`, and `php.ini` define the application image.

Releases are published as `ghcr.io/frameworktools/framework-cloud` and `ghcr.io/frameworktools/framework-scaffolder`.

## Installation

A deployment requires `compose.yml` and `.env.example`. Two guides cover the setup steps:

- [Docker installation](../../docs/guide/install-docker.md) for a shared server.
- [Local installation](../../docs/guide/running-at-home.md) for single-user mode on one computer.

Download the files, generate an application key, and start the containers:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
# fill in .env, then:
docker compose up -d
docker compose exec app php artisan fw:login-link you@example.edu
```

## Source builds

Run these commands from this directory in a repository clone:

```sh
cp .env.example .env    # then fill it in
docker compose -f compose.yml -f compose.build.yml up -d --build
```

## Release publishing

`.github/workflows/docker-image.yml` and `scaffolder-image.yml` build the images for `linux/amd64` and `linux/arm64` and pushes them to the GitHub container registry.

- Push a tag such as `v1.2.0` to publish `1.2.0`, `1.2`, and `latest`.
- Trigger the workflow manually to publish a test image tagged with the branch name.

After the first publish, set package visibility to public in the GitHub organization package settings. Public visibility allows pulls without authentication.
