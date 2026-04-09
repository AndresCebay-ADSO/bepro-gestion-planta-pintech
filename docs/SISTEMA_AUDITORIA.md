# Sistema de Auditoría y Registro de Actividad

Pintech OS utiliza un sistema de auditoría centralizado para garantizar la trazabilidad de las acciones administrativas y operativas críticas.

## 1. Stack Tecnológico
- **Librería**: `spatie/laravel-activitylog` (v5+)
- **Almacenamiento**: Tabla `activity_log` en base de datos central.

## 2. Configuración y Mantenimiento

### 2.1 Retención de Datos
Para mantener la salud de la base de datos y evitar el crecimiento infinito (bloat), se ha configurado una política de retención:
- **Periodo**: 180 días (6 meses).
- **Configuración**: `activitylog.delete_records_older_than_days` en `config/activitylog.php`.

### 2.2 Limpieza Automática
Se ha programado una tarea en el Scheduler de Laravel (`routes/console.php`):
- **Comando**: `activitylog:clean`
- **Frecuencia**: Diario (mientras sea posible) -> `dailyAt('03:00')`.

## 3. Implementación en Modelos

Para que un modelo sea auditado, debe implementar el trait `LogsActivity` y definir el método `getActivitylogOptions`.

### 3.1 Modelos Auditados Actualmente
| Modelo | Eventos Registrados |
| :--- | :--- |
| `User` | Creación, Edición, Cambio de Estado, Eliminación. |
| `RawMaterial` | Cambios en precios y datos maestros. |
| `Formula` | Versiones de recetas y cambios en composición. |
| `InventoryBatch` | Movimientos de lotes y cambios de stock. |
| `ProductionOrder` | Cambios de estado (Pendiente -> Finalizada). |
| `InventoryMovement` | Registro de trazabilidad de entradas/salidas. |

### 3.2 Eventos Especiales
Además de los cambios en modelos, se registran eventos de seguridad:
- **Inicios de sesión fallidos**: Capturados mediante listener `LogFailedLoginAttempt`. Registra IP y navegador.
- **Cambios de Roles**: Registrados explícitamente en el `UserController` cuando se modifica el rol de un usuario.

## 4. Visualización de Actividad

### 4.1 Widget de Actividad Reciente
Ubicado en el Dashboard de Gestión de Usuarios (`resources/js/Pages/Admin/Users/Index.tsx`).
- Muestra los últimos **5 eventos**.
- Codificación por colores (Emerald para creación, Amber para edición, Rose para eliminación, Indigo para seguridad).

### 4.2 Centro de Auditoría Completo
Accesible desde la barra lateral (Sistema -> Registro de Auditoría).
- Enlace: `/admin/audit-logs`
- Permite filtrado avanzado por fecha, usuario, modelo y tipo de evento.

---
**Nota para Desarrolladores**: Evitar registrar campos sensibles como `password` o `remember_token` configurando `dontLogIfAttributesChangedOnly()` en las opciones del log de cada modelo.
