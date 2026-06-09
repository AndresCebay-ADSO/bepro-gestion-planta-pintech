#!/bin/sh
set -e

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

# Run pending migrations (idempotent — only runs new ones)
php artisan migrate --force

# Run the default command (e.g., php-fpm or bash)
exec "$@"
