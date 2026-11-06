#!/bin/sh
# Prueba D15 (spike/image-base): sincroniza los roles y permisos del sistema con el código en cada arranque del
# contenedor web. Corre después de las automatizaciones de Laravel (50-), que ya migraron.
#
# Solo en el contenedor web: con AUTORUN_ENABLED y el comando por defecto de la imagen. Queue, scheduler y los
# comandos sueltos (`docker compose run … php artisan …`) no lo corren.
if [ "${AUTORUN_ENABLED:-false}" != "true" ] || [ "${SERVERSIDEUP_DEFAULT_COMMAND:-false}" != "true" ]; then
    exit 0
fi

echo "🔑 Sincronizando roles y permisos: php artisan db:seed --class=RolePermissionSeeder --force"
php "$APP_BASE_DIR/artisan" db:seed --class=RolePermissionSeeder --force
