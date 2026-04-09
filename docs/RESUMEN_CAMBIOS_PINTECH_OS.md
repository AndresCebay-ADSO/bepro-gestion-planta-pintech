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


---

## 8) Correcciones de UI y Experiencia de Usuario (Inventory & Admin)

### Mejora en campos decimales del Inventario
- `resources/js/Pages/Inventory/RawMaterials/Edit.tsx`:
  - Se eliminaron los ceros decimales muertos visualmente innecesarios al editar el *stock mínimo* y los *precios*, usando transformaciones exactas (`String(Number(value))`) antes de popular el formulario, para evitar ver campos con `,0000` si existen valores cerrados.

### Corrección de traducción en Paginación
- `resources/js/Pages/Inventory/RawMaterials/Index.tsx`
- `resources/js/Pages/Inventory/Warehouses/Index.tsx`
  - Se actualizó el filtro de las ligas de la paginación de Inertia, capturando específicamente `pagination.previous` y `pagination.next` para ocultar los botones de "anterior/siguiente" que aparecían cuando faltaba la traducción al español en backend, dejando limpia la botonera numérica central.

### Correcciones en Formularios de Administración de Usuarios
- `resources/js/Pages/Admin/Users/Create.tsx` y `Edit.tsx`:
  - **Botón Vislumbrar Contraseña:** Se instaló e implementó el componente `<PasswordInput>` (creado con lucide-react y radix ui) en la creación de usuarios para permitir visualizar las contraseñas escritas (Contraseña y Confirmación).
  - **Corrección Bug de Mensajes de Validación**: Se corrigió el error donde Inertia escupía un objeto con llave string `Record<string, string>`, pero el cliente TypeScript lo trataba como arreglo devolviendo el índice cero `{errors.name[0]}`, lo que causaba que el sistema solo renderizara la primera letra del error.
