ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli

# Update dependencies list
RUN apt-get clean all \
	&& apt-get update \
	&& apt-get autoremove

# Base dependencies
RUN apt-get install -y --no-install-recommends \
	libpq-dev \
	libzip-dev \
	git \
	zip \
	unzip

# PHP extensions
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql zip mysqli pgsql

# Create user
ARG PUID=1000
ARG PGID=1000
RUN groupadd -g ${PGID} php && \
	useradd -l -u ${PUID} -g php php && \
	install -d -m 0755 -o php -g php /home/php

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chown php:php /usr/bin/composer

USER php

WORKDIR /app
# Keep the container running indefinitely
CMD ["tail", "-f", "/dev/null"]
