# Uji coba SIPENAMAS V2 versi Inertia, disajikan di sub-path /refactor/inertia.
# Build dari folder sipenamas_v2_backend:
#   docker compose -f docker/inertia/compose.yaml build

ARG BASE_PATH=/refactor/inertia

FROM node:22-bookworm-slim AS assets
ARG BASE_PATH
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# Prefix aset Vite (CSS url(), chunk dinamis) harus sama dengan sub-path deploy.
RUN ASSET_URL=${BASE_PATH} npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi

FROM php:8.4-fpm-bookworm
ARG BASE_PATH

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# libreoffice-writer-nogui: konversi docx -> pdf (DocxToPdfConverter, lembar pengesahan).
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-retries \
    && apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor libreoffice-writer-nogui fonts-dejavu fonts-liberation \
    && install-php-extensions pdo_mysql gd zip intl bcmath exif opcache redis \
    && rm -rf /var/lib/apt/lists/*

COPY docker/inertia/php.ini /usr/local/etc/php/conf.d/zz-sipenamas.ini
COPY docker/inertia/php-fpm.conf /usr/local/etc/php-fpm.d/zz-sipenamas.conf
COPY docker/inertia/nginx.conf /etc/nginx/nginx.conf
COPY docker/inertia/supervisord.conf /etc/supervisor/conf.d/sipenamas.conf
COPY docker/inertia/entrypoint.sh /usr/local/bin/sipenamas-entrypoint

WORKDIR /var/www/app
COPY --from=vendor --chown=www-data:www-data /app /var/www/app
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/app/public/build

# Instalasi sub-direktori: /var/www/html${BASE_PATH} -> public Laravel, jadi
# SCRIPT_NAME = ${BASE_PATH}/index.php dan Laravel otomatis memakai prefix itu.
RUN mkdir -p "/var/www/html$(dirname ${BASE_PATH})" \
    && ln -s /var/www/app/public "/var/www/html${BASE_PATH}" \
    && chmod +x /usr/local/bin/sipenamas-entrypoint

ENTRYPOINT ["sipenamas-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
