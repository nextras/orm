ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli-bookworm

# Update dependencies list
RUN apt-get clean all \
	&& apt-get update \
	&& apt-get autoremove

# Base dependencies
RUN apt-get install -y --no-install-recommends \
	apt-transport-https \
	ca-certificates \
	curl \
	gnupg \
	libpq-dev \
	libzip-dev \
	git \
	zip \
	unzip

# PHP extensions
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql zip mysqli pgsql

# Microsoft repo (Debian 12 / bookworm)
RUN set -eux; \
	mkdir -p /etc/apt/keyrings; \
	curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
		| gpg --dearmor > /etc/apt/keyrings/microsoft.gpg; \
	chmod 0644 /etc/apt/keyrings/microsoft.gpg; \
	echo "deb [arch=amd64,arm64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
		> /etc/apt/sources.list.d/microsoft-prod.list; \
	apt-get update; \
	ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 mssql-tools18; \
	echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' > /etc/profile.d/mssql-tools.sh;

# PHP SQL Server extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
	unixodbc unixodbc-dev \
	$PHPIZE_DEPS
RUN pecl install sqlsrv pdo_sqlsrv \
 && docker-php-ext-enable sqlsrv pdo_sqlsrv

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
