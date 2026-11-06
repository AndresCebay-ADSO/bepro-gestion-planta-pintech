# Imagen de servidor (staging y producción): Nginx y PHP-FPM en un solo contenedor, sobre serversideup/php. La misma
# imagen corre web, queue y scheduler; cambia solo el comando (compose.prod.yaml).
#
# Bases fijadas por versión exacta y digest (índice multiarquitectura). La etiqueta lleva la versión de PHP
# (`8.3.36-fpm-nginx-trixie`) y no la de serversideup (`-v5.1.1`): Dependabot solo entiende la primera y propone el
# parche de PHP siguiente. serversideup recompila cada semana con los arreglos de Debian sin cambiar la etiqueta: ese
# digest nuevo no lo propone Dependabot, lo avisa Trivy (workflow `security`).
FROM node:22.23.4-trixie-slim@sha256:be1e2a3dc2c4fd68cdfadad2a696a280e0d2749098bee509230174d72fb768c6 AS node_base

# Base común: la imagen de serversideup trae pdo_pgsql, zip, pcntl y opcache; faltan estas.
FROM serversideup/php:8.3.36-fpm-nginx-trixie@sha256:5ff96ba254d8772689c69288a4c4363c9dc81aab8981c1511911122a44030233 AS base
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
    && rm -rf node_modules .docker \
    # `storage/app/**` no entra en el contexto: se crean las carpetas, y el enlace público queda hecho en la imagen
    # (el código es de solo lectura para www-data, que no podría crearlo al arrancar).
    && mkdir -p storage/app/public storage/app/private \
    && ln -sfn ../storage/app/public public/storage

FROM base AS production
LABEL org.opencontainers.image.source="https://github.com/Pintech-Colombia/bepro-gestion-planta-pintech" \
      org.opencontainers.image.description="Pintech OS: imagen de servidor (web, queue y scheduler)"

# - OPcache encendido: el código no cambia dentro de la imagen.
# - Subidas de 25 MB (la app valida menos; 30 MB los corta Nginx con 413).
# - Búfer de FastCGI de 32 KB: la cabecera `Link` de precarga de Vite pasa de 6 KB con sesión iniciada, y con un búfer
#   chico Nginx responde 502 («upstream sent too big header»).
# - Revisión de salud contra `/up` de Laravel (la app arrancó), no contra el ping de PHP-FPM.
# - Automatizaciones al arrancar (solo las enciende el servicio web, con AUTORUN_ENABLED): `optimize`. Las migraciones
#   nunca corren al arrancar: las corre `pintech-deploy` antes de reemplazar los contenedores. El enlace de `storage`
#   ya viene en la imagen.
# Ya vienen de la base: expose_php Off, session.upload_progress apagado y server_tokens off.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=25M \
    PHP_POST_MAX_SIZE=25M \
    NGINX_CLIENT_MAX_BODY_SIZE=25M \
    NGINX_FASTCGI_BUFFER_SIZE=32k \
    NGINX_FASTCGI_BUFFERS="16 16k" \
    HEALTHCHECK_PATH=/up \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false

# El código queda de root y de solo lectura para www-data; solo `storage` y `bootstrap/cache` son escribibles. Un fallo
# en la app no puede reescribir el código que se ejecuta.
COPY --from=builder /var/www/html /var/www/html
COPY --from=builder --chown=www-data:www-data /var/www/html/storage /var/www/html/storage
COPY --from=builder --chown=www-data:www-data /var/www/html/bootstrap/cache /var/www/html/bootstrap/cache
# `COPY --chown` cambia el contenido, no la carpeta de destino, que ya existía como de root.
USER root
RUN chown www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
USER www-data
