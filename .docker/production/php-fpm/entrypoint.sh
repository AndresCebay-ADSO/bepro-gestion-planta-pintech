#!/bin/sh
set -e

# Run root-level initialization if the container is started as root
if [ "$(id -u)" = "0" ]; then
  # Initialize storage directory if empty
  if [ ! "$(ls -A /var/www/storage)" ]; then
    echo "Initializing storage directory..."
    cp -R /var/www/storage-init/. /var/www/storage
    chown -R www-data:www-data /var/www/storage
  fi

  mkdir -p \
    /var/www/storage/app/public \
    /var/www/storage/app/private \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs

  # Remove storage-init directory
  rm -rf /var/www/storage-init

  if [ "${APP_ENV:-production}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required in production."
    exit 1
  fi

  # Cache configurations as www-data
  gosu www-data php artisan config:cache
  gosu www-data php artisan route:cache
  gosu www-data php artisan storage:link --relative --force

  # Drop privileges and run the default command (e.g., php-fpm, queue:work)
  exec gosu www-data "$@"
else
  # Container is already running as a non-privileged user
  if [ "${APP_ENV:-production}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required in production."
    exit 1
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan storage:link --relative --force

  exec "$@"
fi
