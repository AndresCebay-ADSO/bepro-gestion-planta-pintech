# Prueba D15, opción B: serversideup/php fpm-nginx (Nginx y PHP-FPM en un contenedor). Rama spike/image-base: no se
# fusiona. Imágenes fijadas por etiqueta de versión y digest (índice multiarquitectura).
FROM node:22.23.4-trixie-slim@sha256:be1e2a3dc2c4fd68cdfadad2a696a280e0d2749098bee509230174d72fb768c6 AS node_base

# Base común: la imagen de serversideup más las extensiones que no trae. Ya trae pdo_pgsql, zip, pcntl y opcache.
FROM serversideup/php:8.3-fpm-nginx-trixie-v5.1.1@sha256:5ff96ba254d8772689c69288a4c4363c9dc81aab8981c1511911122a44030233 AS base
USER root
RUN install-php-extensions intl bcmath gd pgsql
USER www-data

# Compila Composer y la pantalla una sola vez. Wayfinder corre `php artisan` durante `npm run build`: por eso parte de
# la misma base y no de una imagen de Node.
FROM base AS builder
USER root
COPY --from=node_base /usr/local/ /usr/local/
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    && php artisan route:clear \
    && npm ci --ignore-scripts=false \
    && npm run build \
    && rm -rf node_modules

FROM base AS production
# Lo mismo que A: OPcache, subidas de 25 MB y cabeceras de 32 KB (el 502 del #279). expose_php ya viene en Off,
# session.upload_progress en 0 y server_tokens en off.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=25M \
    PHP_POST_MAX_SIZE=25M \
    NGINX_CLIENT_MAX_BODY_SIZE=25M \
    NGINX_FASTCGI_BUFFER_SIZE=32k \
    NGINX_FASTCGI_BUFFERS="16 16k" \
    HEALTHCHECK_PATH=/up
COPY --chown=www-data:www-data --from=builder /var/www/html /var/www/html
COPY --chmod=644 .docker/spike/common/entrypoint.d/ /etc/entrypoint.d/
