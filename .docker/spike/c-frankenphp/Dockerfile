# Prueba D15, opción C: serversideup/php frankenphp (Caddy y PHP en un proceso), solo modo clásico: sin worker ni
# Octane. Rama spike/image-base: no se fusiona. Imágenes fijadas por etiqueta de versión y digest.
FROM node:22.23.4-trixie-slim@sha256:be1e2a3dc2c4fd68cdfadad2a696a280e0d2749098bee509230174d72fb768c6 AS node_base

# Base común: la imagen de serversideup más las extensiones que no trae. Ya trae pdo_pgsql, zip, pcntl y opcache.
FROM serversideup/php:8.3-frankenphp-trixie-v5.1.1@sha256:da9dfe46bc458823734daebab5ae676b4dd6377985b2bbdbbb1746a89eb0f00e AS base
USER root
RUN install-php-extensions intl bcmath gd pgsql
USER www-data

# Compila Composer y la pantalla una sola vez (Wayfinder necesita PHP durante `npm run build`).
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
# Lo mismo que A: OPcache y subidas de 25 MB. Caddy no tiene límite de cuerpo propio: manda el de PHP. El Caddyfile
# de la imagen ya quita `Server` y `X-Powered-By`; expose_php viene en Off y session.upload_progress en 0.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=25M \
    PHP_POST_MAX_SIZE=25M \
    HEALTHCHECK_PATH=/up
COPY --chown=www-data:www-data --from=builder /var/www/html /var/www/html
COPY --chmod=644 .docker/spike/common/entrypoint.d/ /etc/entrypoint.d/
