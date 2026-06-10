#!/bin/sh
set -e

# Automatically generate APP_KEY if missing or empty
if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi
export APP_KEY=$(grep '^APP_KEY=' /var/www/.env | cut -d= -f2-)
exec "$@"

# Clear configurations to avoid caching issues in development
echo "Clearing configurations..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

mkdir -p \
  storage/app/public \
  storage/app/private \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

php artisan storage:link --relative --force

# Run the default command (e.g., php-fpm or bash)
exec "$@"
