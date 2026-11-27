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

# Optional Swoole (needs PHP >= 8.2) for the async collection fetching tests:
#   docker compose build --build-arg PHP_VERSION=8.4 --build-arg WITH_SWOOLE=1 php
ARG WITH_SWOOLE=0
RUN if [ "$WITH_SWOOLE" = "1" ]; then \
      apk add --no-cache libstdc++ openssl curl && \
      apk add --no-cache --virtual .swoole-build $PHPIZE_DEPS linux-headers openssl-dev curl-dev && \
      pecl install -D 'enable-sockets="no" enable-openssl="yes" enable-mysqlnd="yes" enable-swoole-curl="no" enable-cares="no" enable-brotli="no" enable-zstd="no" enable-swoole-pgsql="yes"' swoole && \
      docker-php-ext-enable swoole && \
      apk del .swoole-build && rm -rf /tmp/*; \
    fi

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
