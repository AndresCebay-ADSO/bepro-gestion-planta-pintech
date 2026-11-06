# Servidor: staging y producción

Cómo corre Pintech OS en el VPS y cómo se opera. Staging y producción viven en el mismo servidor, aislados: cada uno es
un proyecto de Docker Compose aparte, con su base, sus archivos, su túnel y sus límites de recursos.

## Qué hay en el servidor

```text
/usr/local/bin/pintech-deploy      despliega un digest (lo único que puede correr el usuario `deploy` del CI)
/usr/local/bin/pintech-backup      copia diaria de la base y de storage
/etc/sudoers.d/pintech-deploy      sudoers.example
/etc/cron.d/pintech-backup         pintech-backup.cron
/home/deploy/.ssh/authorized_keys  authorized_keys.example

/srv/pintech/<entorno>/            staging o prod (dueño root, permisos 700)
  compose.yaml                     copia de compose.prod.yaml del repositorio
  .env                             server.env.example completado (permisos 600)
  tunnel.env                       TUNNEL_TOKEN=<token del túnel de ese entorno> (permisos 600)
  deploys.log                      un renglón por despliegue: fecha, digest, commit y resultado
  backups/                         daily/, weekly/ (solo prod) y predeploy/ (solo prod)
```

Nada se compila en el servidor: la imagen la compila el CI una vez (workflow `image`) y se baja de GHCR por digest. El
VPS necesita un token de **solo lectura** de GHCR (`sudo docker login ghcr.io`).

`compose.yaml` y los scripts no se actualizan con un despliegue: si cambian en el repositorio, se copian a mano al
servidor.

## Desplegar

```bash
sudo pintech-deploy staging sha256:<digest>
```

El digest sale del resumen del workflow `image` en GitHub Actions. El script baja la imagen, migra con la imagen nueva
en un contenedor de un solo uso (los contenedores actuales siguen atendiendo), sincroniza roles y permisos, reemplaza
web, queue y scheduler, y espera su revisión de salud. Si la migración falla, no reemplaza nada. Si la imagen nueva no
queda sana en 90 segundos, vuelve sola a la anterior. En producción hace además una copia de la base antes de migrar.

**Volver atrás:** el mismo comando con el digest anterior (está en `deploys.log`). Solo vuelve el código: la base no se
deshace. Por eso las migraciones son siempre compatibles hacia atrás: agregar columnas o tablas; para borrar o
renombrar, primero se deja de usar en un despliegue y se borra en otro posterior.

## Comandos sueltos

Desde la carpeta del entorno, `docker compose` ya sabe qué proyecto, archivo e imagen usar (del `.env`):

```bash
cd /srv/pintech/staging
sudo docker compose ps                                   # estado y salud de cada servicio
sudo docker compose logs --tail=100 web                  # registros (también queue, scheduler, postgres, tunnel)
sudo docker compose run --rm web php artisan users:create-admin   # primer usuario (pide los datos)
sudo docker compose run --rm web php artisan db:seed --force      # datos de demostración, solo staging y una vez
```

El primer usuario, los datos de demostración y el reset de staging se explican en el README principal («Entornos
protegidos»).

## Copias de seguridad

`pintech-backup` corre todos los días a las 02:00 de Colombia (`pintech-backup.cron`) y guarda en
`/srv/pintech/<entorno>/backups/`:

| | Qué | Retención |
|---|---|---|
| `daily/db-<fecha>.dump` | La base completa (`pg_dump`, formato personalizado), comprobada con `pg_restore --list` | staging 3, prod 14 |
| `daily/storage-<fecha>.tar.gz` | `storage`: archivos subidos, PDFs generados y logs (sin cachés ni sesiones) | staging 3, prod 14 |
| `weekly/` | La copia del domingo | prod 8 |
| `predeploy/db-<fecha>.dump` | La base justo antes de migrar en un despliegue | prod 5 |

A mano: `sudo pintech-backup staging`. El registro queda en `/var/log/pintech-backup.log`.

Estas copias viven en el mismo VPS: si se pierde el servidor, se pierden con él. Antes de producción se agrega una
copia cifrada fuera del VPS.

### Restaurar

Un backup sin restaurar no es un backup: se prueba una vez al mes. Con la app detenida para que nadie escriba durante
la restauración (el ejemplo es staging; en producción, igual con `prod`):

```bash
cd /srv/pintech/staging
sudo docker compose stop web queue scheduler

# 1. Base: se borra y se crea vacía, y se carga la copia. Queda exactamente como estaba en la fecha de la copia.
sudo docker compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" --force "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
sudo docker compose exec -T postgres sh -c 'pg_restore --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
  < backups/daily/db-<fecha>.dump

# 2. Archivos: se descomprimen sobre el volumen de storage.
sudo docker compose run --rm --no-deps -T --entrypoint tar web -C /var/www/html -xzf - < backups/daily/storage-<fecha>.tar.gz

# 3. Si la imagen que corre es más nueva que la copia, aplica las migraciones que le faltan a la base restaurada.
sudo docker compose run --rm web php artisan migrate --force

sudo docker compose up -d --wait
```

Comprobar: la app abre, se inicia sesión y se descarga un PDF que ya existía.
