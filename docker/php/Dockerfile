# ---------------------------------------------------------------------------
# PHP 8.3 FPM image for the Recruitment API (app + queue worker share it).
# Extensions cover: MySQL (writes), Redis (cache + queues), and the ones
# PhpSpreadsheet needs for the Excel report (zip, gd).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm

# UID/GID are passed at build time so php-fpm writes files owned by the host
# user on bind-mounted volumes (no more root-owned files in dev).
ARG UID=1000
ARG GID=1000

# mlocati's helper installs & configures PHP extensions reliably (handles the
# system libs each one needs) — far less brittle than raw docker-php-ext-install.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        pcntl \
        intl \
        zip \
        gd \
        redis \
        opcache && \
    rm -rf /var/lib/apt/lists/*

# Composer binary (copied from the official image, no global install needed).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Remap the built-in www-data user to the host UID/GID so bind-mounted files
# keep your ownership. php-fpm workers run as www-data.
RUN groupmod -o -g "${GID}" www-data && \
    usermod  -o -u "${UID}" -g "${GID}" www-data

# Custom php.ini overrides (memory, opcache, upload sizes for the Excel job).
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# Keep storage/cache writable by the runtime user even before an install.
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
