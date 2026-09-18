# Pintech OS - Sistema de Gestion de Planta

ERP web para **Pintech Colombia S.A.S** (pinturas industriales, automotrices y arquitectonicas), orientado a operacion de planta, trazabilidad e integracion entre Produccion, Administracion y Comercial.

## Estado actual

- Backend en Laravel 13 + PHP 8.3+
- Frontend en React 19.2 + Inertia + TypeScript + Tailwind v4
- Base de datos PostgreSQL 16
- Autenticacion con Laravel Fortify
- Roles/permisos con Spatie Permission
- Sistema de Auditoria con Spatie Activitylog (v4)
- Recuperacion de contrasena por correo con branding Pintech
- Tema claro/oscuro y layouts auth personalizados
- Navegacion Premium con barra lateral dinámica por roles

## Stack tecnico

- Backend: `laravel/framework` 13.x
- Frontend: React 19.2, Inertia.js, Vite
- UI: Tailwind CSS v4 + componentes UI locales
- DB: PostgreSQL 16
- Auth: Laravel Fortify
- Roles: Spatie Laravel Permission
- Auditoria: Spatie Laravel Activitylog (v4)
- Testing: Pest
- Queue: database (para procesos asinc)
- Cache: database

## Roles del sistema

El acceso se decide por **permisos** (Spatie), no por rol. Los permisos se declaran en `App\Enums\Permission` y los
roles del sistema en `App\Enums\SystemRole` (nombre interno en inglés, etiqueta en español); la matriz completa está
en `docs/MATRIZ_RBAC.md`.

- `super-admin`: soporte / tecnología, todos los permisos.
- `admin`: jefa de la empresa y de producción (catálogo técnico, costos, usuarios).
- `production` (Producción): auxiliares de producción (crear, operar, revisar y completar órdenes).
- `operator` (Operador): personal de planta (ejecuta las órdenes).
- `commercial` (Comercial): clientes, cotizaciones, pedidos y solicitudes de desarrollo; ve precios, nunca costos.

## Funcionalidades principales

- **Gestión de inventarios**: Control estricto de materias primas y lotes con enfoque PEPS/FIFO.
- **Ciclo de vida e Integridad**: Manejo de eliminación lógica/activación (`is_active`) garantizando el historial referencial de costos.
- **Producción y Consumos**: Formulación, órdenes de producción y consumo preciso por lote.
- **Reportes Profesionales**: Motor de exportación robusto de Órdenes de Producción a formatos **PDF y Excel**.
- **Gestión Financiera**: Historial de costos, cálculos de margen de ganancia y listas de precios automatizadas.
- **Trazabilidad**: Alertas operativas, códigos QR, documentos asociados y un sistema profundo de auditoría.
- **Seguridad**: Control de acceso granular por rol y permisos.

## Autenticacion y correo

- Login con email/contrasena
- Recuperacion de contrasena por email
- Registro publico deshabilitado (creacion de usuarios por admin)
- Protección robusta contra ataques de enumeración de usuarios y limitación de peticiones (Rate Limiting) optimizada para SPA.
- 2FA deshabilitado actualmente
- Notificacion de reset personalizada:
  - `app/Notifications/ResetPasswordNotification.php`
  - Firma personalizada: `Saludos, Equipo de Pintech`

### SMTP (desarrollo actual)

Configuracion ejemplo usada:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=pintech.sistemas@gmail.com
MAIL_PASSWORD=<app_password_sin_espacios>
MAIL_FROM_ADDRESS="pintech.sistemas@gmail.com"
MAIL_FROM_NAME="Pintech OS"
```

> Nota: en tests, `phpunit.xml` ya define `MAIL_MAILER=array` para evitar dependencias SMTP externas.

## Requisitos

### Opción A: Local (Homebrew / sistema)

- PHP 8.3+ (con extensiones `pdo_pgsql`, `gd`, `zip` requeridas para BD y exportaciones)
- Composer 2.x
- Node.js 18+ o superior
- PostgreSQL 16

### Opción B: Docker (recomendado para equipos)

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (incluye Docker Compose)
- Git

> Con Docker no necesitas instalar PHP, Composer, Node ni PostgreSQL en tu máquina.

## Instalación con Docker (recomendado)

```bash
# 1. Clonar el repositorio
git clone https://github.com/AndresCebay-ADSO/bepro-gestion-planta-pintech.git
cd bepro-gestion-planta-pintech

# 2. Configurar variables de entorno
cp .env.example .env
# Edita .env → Ajusta DB_PASSWORD y las variables de tu app Laravel

# 3. Levantar todo (el entrypoint configura todo automáticamente)
docker compose -f compose.dev.yaml up -d --build

# 4. Verificar que todo está corriendo
docker compose -f compose.dev.yaml ps

# 5. (Primera vez) migrar y seedear
docker compose -f compose.dev.yaml exec php-fpm php artisan migrate
docker compose -f compose.dev.yaml exec php-fpm php artisan db:seed
```

**¿Qué pasa automáticamente?** Al levantar, el entrypoint de desarrollo:
- Genera APP_KEY de estar vacia
- Limpia caches y enlaza storage
- Inicia PHP-FPM con Xdebug activado

**Servicios disponibles:**
| Servicio | URL | Puerto |
|----------|-----|--------|
| App (Nginx) | http://localhost:8000 | 8000 |
| Vite (HMR) | http://localhost:5173 | 5173 |
| PostgreSQL | localhost | 5432 |

**Comandos Docker frecuentes:**
```bash
docker compose -f compose.dev.yaml exec php-fpm php artisan <comando>  # Ejecutar artisan
docker compose -f compose.dev.yaml exec php-fpm php artisan tinker      # Tinker
docker compose -f compose.dev.yaml exec php-fpm ./vendor/bin/pest       # Tests
docker compose -f compose.dev.yaml logs -f web php-fpm                  # Ver logs
docker compose -f compose.dev.yaml down                                 # Detener todo
docker compose -f compose.dev.yaml up -d --build                        # Rebuild
```

> **Nota:** Si tienes PostgreSQL local (Homebrew) corriendo en el puerto 5432, detenlo antes de usar Docker o cambia `DB_PORT` en tu `.env`.

## Instalación sin Docker (manual)

1. **Clonar el repositorio e instalar dependencias:**
   ```bash
   git clone https://github.com/AndresCebay-ADSO/bepro-gestion-planta-pintech.git
   cd bepro-gestion-planta-pintech
   composer install
   npm install
   ```

2. **Configurar el entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Preparar la Base de Datos:**
   - Crea una base de datos vacía en PostgreSQL (ej. `pintech_os`).
   - Configura las credenciales en tu archivo `.env`:
     ```env
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=pintech_os
     DB_USERNAME=tu_usuario
     DB_PASSWORD=tu_contrasena
     ```

4. **Migrar la base de datos e inyectar datos iniciales:**
   ```bash
   php artisan migrate
   
   # ¡Recomendado en desarrollo! Poblar la base de datos con datos de prueba, roles y usuario admin:
   php artisan db:seed
   ```
   > **Nota:** Al ejecutar los seeders, se generan usuarios de prueba según los roles (admin, produccion, comercial). Revisa la consola o `database/seeders/DatabaseSeeder.php` para las credenciales predeterminadas.

## Desarrollo local

### Con Docker (recomendado)

```bash
docker compose -f compose.dev.yaml up -d          # Levanta todo
docker compose -f compose.dev.yaml logs -f        # Ver logs
docker compose -f compose.dev.yaml down           # Detener
```

### Sin Docker

```bash
composer dev                  # Opción rápida (levanta todo)
```

O manualmente:

```bash
# Terminal 1
php artisan serve

# Terminal 2
php artisan queue:work

# Terminal 3
npm run dev
```

Si usas Reverb:

```bash
php artisan reverb:start
```

## Scripts utiles

```bash
npm run dev
npm run build
npm run types:check
./vendor/bin/pest
./vendor/bin/pint
php artisan optimize:clear
```

## Testing

- Framework: Pest
- Config de test en `phpunit.xml`:
  - `DB_CONNECTION=sqlite` (`:memory:`)
  - `MAIL_MAILER=array`
  - `QUEUE_CONNECTION=sync`

Ejemplos:

```bash
./vendor/bin/pest
./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php
```

### Tests contra PostgreSQL

SQLite no tiene los advisory locks de los consecutivos, ni las restricciones CHECK de inventario, ni los índices
parciales. CI ejecuta la suite en ambos motores; en local, con el contenedor de desarrollo levantado:

```bash
docker compose -f compose.dev.yaml up -d postgres
docker compose -f compose.dev.yaml exec -T postgres psql -U postgres -c "CREATE DATABASE pintech_erp_test"

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=pintech_erp_test \
  DB_USERNAME=postgres DB_PASSWORD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" ./vendor/bin/pest
```

Las variables de entorno mandan sobre `phpunit.xml`, que fija SQLite. Usa una base aparte (`pintech_erp_test`):
`RefreshDatabase` recrea el esquema en cada test.

## Integración continua

Dos workflows, en cada push y PR a `develop` y `main`:

- **quality**: Pint, Prettier, ESLint y TypeScript **en modo comprobación** (fallan, no corrigen), más `composer audit`
  y `npm audit` como aviso. Levanta PostgreSQL y migra antes de generar las rutas de Wayfinder, que tipa los parámetros
  leyendo el esquema: sin base de datos los tipos cambian y `tsc` falla sin motivo real.
- **tests**: la suite en SQLite y en PostgreSQL 16, ambas con `--parallel`.

Los tres jobs corren a la vez y cachean `vendor` y `node_modules`, así que el tiempo total es el del más lento.

Para que sirvan de barrera hay que **proteger `develop` y `main`** en GitHub exigiendo ambos checks antes de fusionar.

## Despliegue a producción

El entrypoint de producción **no** ejecuta migraciones ni seeders. En cada despliegue, después de levantar la nueva
imagen, ejecutar en este orden:

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # sincroniza permisos y roles del sistema con el código
php artisan permission:cache-reset
```

(El entrypoint ya ejecuta `config:cache`, `route:cache`, `view:cache` y `event:cache` al arrancar.)

- **`RolePermissionSeeder` es obligatorio en cada despliegue**, no solo el primero: crea los permisos nuevos del enum,
  elimina los retirados y reasigna a cada rol del sistema sus permisos por defecto. Si se omite, las pantallas nuevas
  responden 403; en una base recién creada, **todos** los usuarios reciben 403.
- Es idempotente y nunca toca los roles creados desde la UI. Si un cambio de reglas deja alguno inválido (permisos
  reservados, obligatorios o dependencias), lo avisa en la salida; el detalle se ve con `php artisan roles:audit`, que
  termina con error mientras quede alguno. Se corrigen a mano desde la pantalla de roles.
- **Nunca** ejecutar `php artisan db:seed` sin `--class` en producción: `DatabaseSeeder` también carga datos de negocio.
- Usuario de soporte: `php artisan users:grant-super-admin {email}` sobre un usuario existente y activo.

## Base de datos

El esquema consolidado actual se documenta en:

- `docs/MER.md`

Incluye tablas de negocio y tablas de soporte (cache, jobs, auth, permisos).

## Documentacion

Carpeta `docs/`:

- `MER.md` - Modelo entidad-relación y diccionario de datos completo.
- `STANDARDS.md` - Estándares de código, paginación y diseño.
- `ARQUITECTURA.md` - Guía y decisiones de arquitectura del sistema.
- `FLUJO_SISTEMA.md` - Flujo integral de operaciones de planta.
- `SOFTWARE_OVERVIEW.md` - Mapa completo de capacidades del sistema.
- `POLITICA_COSTOS_MATERIA_PRIMA.md` - Costeo FIFO y precios de referencia.
- `MATRIZ_RBAC.md` - Matriz de roles y permisos del sistema.
- `PLAN_FASE_2_RBAC.md` - Plan de implementación de roles y permisos (Fase 2).
- `COMPONENTES_UI.md` - Componentes reutilizables (FormattedNumber, FormattedDate, TableActions).
- `SISTEMA_TEMAS_UI.md` - Guía del sistema de temas visuales (claro/oscuro).
- `SISTEMA_AUDITORIA.md` - Guía del registro de actividad y auditoría.
- `LOGOS.md` - Guía de assets y branding corporativo.
- `production-exports.md` - Módulo de exportación de órdenes a PDF y Excel.

## Autor

Andres Stiven Cebay Ceballos (ADSO) - 2026
