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

- `admin`: acceso total (usuarios, configuracion, costos, reportes)
- `produccion`: operacion de planta (inventarios, formulas, ordenes)
- `comercial`: consulta de disponibilidad y precios (solo lectura operativa)

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

# 5. (Primera vez) Generar key, migrar y seedear
docker compose -f compose.dev.yaml exec php-fpm php artisan key:generate
docker compose -f compose.dev.yaml exec php-fpm php artisan migrate
docker compose -f compose.dev.yaml exec php-fpm php artisan db:seed
```

**¿Qué pasa automáticamente?** Al levantar, el entrypoint de desarrollo:
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

## Base de datos

El esquema consolidado actual se documenta en:

- `docs/MER.md`

Incluye tablas de negocio y tablas de soporte (cache, jobs, auth, permisos).

## Documentacion

Carpeta `docs/`:

- `SOFTWARE_OVERVIEW.md` - Mapa completo de capacidades del sistema (v1.2).
- `production-exports.md` - **NUEVO** Guía técnica sobre el módulo de exportación a PDF y Excel.
- `MER.md` - Modelo entidad relacion / diccionario de datos actualizado tras consolidacion.
- `SISTEMA_AUDITORIA.md` - Guia técnica del registro de actividad y widgets de trazabilidad.
- `COMPONENTES_UI.md` - Guía de uso de componentes reutilizables (FormattedNumber, FormattedDate, TableActions).
- `ACTA_ACTUALIZACION_V1_2.md` - Historial de cambios técnicos v1.1 -> v1.2.
- `RESUMEN_CAMBIOS_PINTECH_OS.md` - Resumen ejecutivo de ajustes tecnicos iniciales.
- `ESPECIFICACION.md` - Detalle de requerimientos.
- `PLAN_DESARROLLO.md` - Hoja de ruta.
- `STANDARDS.md` - Estándares de código, paginación y diseño.
- `SISTEMA_TEMAS_UI.md` - Guía de implementación del tema visual.

## Autor

Andres Stiven Cebay Ceballos (ADSO) - 2026
