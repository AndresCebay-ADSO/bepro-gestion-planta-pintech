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

- Gestion de materias primas y lotes (enfoque PEPS/FIFO)
- Movimientos de inventario de MP y PT
- Productos, categorias y unidades de medida
- Formulas y detalle de formulacion
- Ordenes de produccion y consumo por lote
- Historial de costos y lista de precios
- Alertas operativas
- Codigos QR y documentos asociados
- Control de acceso por rol

## Autenticacion y correo

- Login con email/contrasena
- Recuperacion de contrasena por email
- Registro publico deshabilitado (creacion de usuarios por admin)
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

- PHP 8.3+
- Composer 2.x
- Node.js 18+
- PostgreSQL 16

## Instalacion

```bash
git clone https://github.com/AndresCebay-ADSO/bepro-gestion-planta-pintech.git
cd bepro-gestion-planta-pintech
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configura `.env` (DB, mail, app) y luego:

```bash
php artisan migrate
# (Opcional) Para datos de prueba:
php artisan db:seed
```

## Desarrollo local

### Opcion recomendada

```bash
composer dev
```

### Opcion manual

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

- `SOFTWARE_OVERVIEW.md` - **NUEVO** Mapa completo de capacidades del sistema (v1.2).
- `MER.md` - Modelo entidad relacion / diccionario de datos actualizado tras consolidacion.
- `SISTEMA_AUDITORIA.md` - Guia técnica del registro de actividad y widgets de trazabilidad.
- `ACTA_ACTUALIZACION_V1_2.md` - Historial de cambios técnicos v1.1 -> v1.2.
- `RESUMEN_CAMBIOS_PINTECH_OS.md` - Resumen ejecutivo de ajustes tecnicos iniciales.
- `ESPECIFICACION.md` - Detalle de requerimientos.
- `PLAN_DESARROLLO.md` - Hoja de ruta.
- `STANDARDS.md` - Estándares de código y diseño.
- `SISTEMA_TEMAS_UI.md` - Guía de implementación del tema visual.

## Autor

Andres Stiven Cebay Ceballos (ADSO) - 2026
