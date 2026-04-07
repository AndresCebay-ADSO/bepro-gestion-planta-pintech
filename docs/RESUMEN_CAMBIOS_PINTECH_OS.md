# Resumen de Cambios - Pintech OS

Fecha: 2026-04-07

## 1) Base de datos (migraciones)

Se realizó limpieza y consolidación de migraciones para dejar un esquema inicial mantenible y sin pasos intermedios innecesarios.

### Ajustes aplicados
- Se consolidaron columnas agregadas por migraciones `add_*` dentro de sus migraciones `create_*`.
- Se eliminaron migraciones obsoletas/intermedias.
- Se ajustó el orden de migraciones de movimientos para respetar dependencias con órdenes de producción.
- Se normalizaron llaves primarias usando `bigIncrements('id')` en tablas correspondientes.
- Se retiró soporte de 2FA en esquema inicial (no se está usando actualmente).
- Se removió tabla `sessions` de migración base (actualmente `SESSION_DRIVER=file`).

### Migraciones eliminadas por obsolescencia
- `2025_08_14_170933_add_two_factor_columns_to_users_table.php`
- `2026_04_06_000301_add_unit_of_measure_id_to_raw_materials.php`
- `2026_04_06_000601_add_unit_of_measure_id_to_products.php`
- `2026_04_06_001001_add_unit_of_measure_id_to_formula_details.php`
- `2026_04_06_000500_create_inventory_movements_table.php` (reemplazada)
- `2026_04_06_000800_create_finished_inventory_movements_table.php` (reemplazada)

### Validación
- Se ejecutó `php artisan migrate:fresh` exitosamente con el orden nuevo.

---

## 2) Correo de recuperación de contraseña

Se configuró envío SMTP real con Gmail App Password.

### `.env` aplicado
- `MAIL_MAILER=smtp`
- `MAIL_SCHEME=smtps`
- `MAIL_HOST=smtp.gmail.com`
- `MAIL_PORT=465`
- `MAIL_USERNAME=pintech.sistemas@gmail.com`
- `MAIL_PASSWORD=<app-password-sin-espacios>`
- `MAIL_FROM_ADDRESS="pintech.sistemas@gmail.com"`
- `MAIL_FROM_NAME="Pintech OS"`

### Nota técnica importante
- Error corregido: `ssl` no es esquema válido para Symfony Mailer con `smtp`; se usa `smtps`.

---

## 3) Personalización visual de correos (branding)

Se publicaron vistas de correo de Laravel y se personalizaron para Pintech.

### Comandos ejecutados
- `php artisan vendor:publish --tag=laravel-mail`
- `php artisan lang:publish`

### Archivos personalizados
- `resources/views/vendor/mail/html/header.blade.php`
  - Se reemplazó branding Laravel por texto `Pintech OS`.
- `resources/views/vendor/mail/html/message.blade.php`
  - Header de correo fijado a `Pintech OS`.
- `resources/views/vendor/mail/text/message.blade.php`
  - Header de texto plano fijado a `Pintech OS`.

---

## 4) Traducción de notificaciones al español

Se habilitó locale en español y se agregaron traducciones para reset de contraseña.

### Ajustes
- `.env`:
  - `APP_LOCALE=es`
  - `APP_FALLBACK_LOCALE=es`
- Nuevo archivo:
  - `lang/es.json` con textos de recuperación de contraseña y mensajes base.

---

## 5) Firma personalizada del correo de reset

Se implementó notificación personalizada para controlar la salutation exacta.

### Archivos
- `app/Notifications/ResetPasswordNotification.php`
  - Notificación propia para reset con textos en español.
  - Salutation: `Saludos, Equipo de Pintech`.
  - Implementa `ShouldQueue` para cola `database`.
- `app/Models/User.php`
  - Se sobrescribió `sendPasswordResetNotification($token)` para usar la notificación personalizada.

---

## 6) Frontend Auth (React + Inertia)

### Recuperar contraseña
- `resources/js/pages/auth/forgot-password.tsx`
  - Botón ya protegido con `disabled={processing}`.
  - Refuerzo de UX/accesibilidad: `aria-disabled`, `aria-busy`, y estado visual `disabled`.

### Restablecer contraseña
- `resources/js/pages/auth/reset-password.tsx`
  - Ajuste visual y texto en español.
  - Branding con `Pintech OS` en encabezado.
  - Botón con color oscuro (estilo industrial/SaaS coherente).

---

## 7) Comandos operativos recomendados

- Limpiar cachés tras cambios de `.env`, vistas o traducciones:
  - `php artisan optimize:clear`
- Procesar correos en cola (si `QUEUE_CONNECTION=database`):
  - `php artisan queue:work`

