# syntax=docker/dockerfile:1
#
# Framework Cloud, self-hosted. One image runs both the web server and the
# queue worker (see compose.yml). Releases are published as
# ghcr.io/frameworktools/framework-cloud by .github/workflows/docker-image.yml.
# To build it yourself, from the repository root:
#
#   docker build -f docker/selfhost/Dockerfile -t framework-cloud .
#
# The development image for Polyscope workspaces is docker/php/Dockerfile.

# 1. Frontend assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# 2. PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 3. Runtime: FrankenPHP (Caddy + PHP), which also handles HTTPS certificates
FROM dunglas/frankenphp:1.13-php8.4-alpine

ARG VERSION=dev
LABEL org.opencontainers.image.title="Framework Cloud" \
      org.opencontainers.image.description="Framework Cloud, the web application for the Framework R package" \
      org.opencontainers.image.source="https://github.com/frameworktools/framework-cloud" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${VERSION}"

RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl exif opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/selfhost/php.ini "$PHP_INI_DIR/conf.d/zz-framework-cloud.ini"

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/selfhost/entrypoint.sh /usr/local/bin/framework-cloud-entrypoint

# Run as an unprivileged user that may still bind ports 80 and 443
RUN adduser -D -u 1000 framework \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && rm -f public/hot .env \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R framework:framework storage bootstrap/cache /data /config \
    && chmod +x /usr/local/bin/framework-cloud-entrypoint

USER framework

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:80

EXPOSE 80 443 443/udp
VOLUME ["/app/storage", "/data", "/config"]

ENTRYPOINT ["framework-cloud-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
