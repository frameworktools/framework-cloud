# Self-hosting Framework Cloud with Docker

This folder holds the files for running Framework Cloud with Docker:

- `compose.yml` runs the published image as three containers: the web server,
  a queue worker, and PostgreSQL.
- `.env.example` is the settings template.
- `compose.build.yml` builds the image from this repository instead of pulling
  a release.
- `Dockerfile`, `entrypoint.sh`, and `php.ini` define the image.

Releases are published as `ghcr.io/frameworktools/framework-cloud`.

## Install

You only need `compose.yml` and `.env.example`. The guide covers each step:

- [Install with Docker](../../docs/guide/install-docker.md) for a shared server
- [Run it on your own computer](../../docs/guide/running-at-home.md) for one
  person, in single-user mode

In short:

```sh
mkdir framework-cloud && cd framework-cloud
curl -fsSLO https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/compose.yml
curl -fsSL https://raw.githubusercontent.com/frameworktools/framework-cloud/main/docker/selfhost/.env.example -o .env
docker run --rm ghcr.io/frameworktools/framework-cloud php artisan key:generate --show
# fill in .env, then:
docker compose up -d
docker compose exec app php artisan fw:login-link you@example.edu
```

## Build from source

From this folder in a clone:

```sh
cp .env.example .env    # then fill it in
docker compose -f compose.yml -f compose.build.yml up -d --build
```

## Publishing a release

`.github/workflows/docker-image.yml` builds the image for `linux/amd64` and
`linux/arm64` and pushes it to GitHub's container registry.

- Push a tag such as `v1.2.0` to publish `1.2.0`, `1.2`, and `latest`.
- Run the workflow by hand to publish an image tagged with the branch name,
  for testing.

The first time the image is published, make the package public in the
organization's package settings on GitHub, so people can pull it without
signing in.
