# Local development image for Docker workspaces (Polyscope on the Linux
# polyscope VM, or `docker compose up`). Production runs on Forge, not this.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        curl \
        libzip-dev \
        zip \
        unzip \
        icu-dev \
        postgresql-dev \
        oniguruma-dev \
        linux-headers \
        nodejs \
        npm \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
        mbstring \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as the host user so files written into the bind mount stay editable.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} -S app && adduser -u ${UID} -S app -G app

WORKDIR /var/www/html
USER app

EXPOSE 9000
CMD ["php-fpm"]
