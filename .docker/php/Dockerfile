ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    git \
    zip \
    unzip  && \
    rm -rf /var/cache/apk/* && \
    rm -rf /tmp/*

RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql zip mysqli pgsql

ARG PUID=1000
ARG PGID=1000
RUN addgroup -g ${PGID} -S php && \
    adduser -u ${PUID} -S -G php -h /home/php -s /bin/bash php && \
    mkdir -p /app && chown -R php:php /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chown php:php /usr/bin/composer

USER php

WORKDIR /app
# Keep the container running indefinitely
CMD ["tail", "-f", "/dev/null"]
