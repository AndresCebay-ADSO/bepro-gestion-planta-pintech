# Revisión de la rama `feature/rbac-permissions`

> **Fecha:** 2026-09-15 · **Base correcta de comparación:** `develop` (la rama sale de su punta; `main` lleva meses sin
> actualizarse e infla el diff de ~150 a ~690 archivos).
>
> Consolida dos revisiones y la verificación de cada hallazgo contra el código:
> - **UR** — `/ultrareview develop` (revisión en la nube, 5 hallazgos).
> - **CR** — revisión manual "Tech Lead" (20 hallazgos, hecha contra `main`).
>
> **Veredicto:** ✅ real · ⚠️ real pero con matiz (severidad o alcance distinto al reportado) · ❌ no se sostiene.
> **Origen:** *rama* = introducido o tocado por esta rama · *previo* = ya existía en `develop`.

---

## 1. Ya corregido en esta rama (UR-1 a UR-5)

| ID | Hallazgo | Veredicto | Arreglo aplicado |
| --- | --- | --- | --- |
| UR-1 | SuperAdmin no ve "Configuración" ni "Reportes" (lista fija de 4 roles en el menú) | ✅ rama | El menú decide **solo por permisos**. "Configuración" es visible para todo usuario con sesión (matriz §5; antes también se ocultaba a Producción y Comercial). "Reportes" usa `production_orders.create`. Se añade `super-admin` al tipo `UserRole` (resuelve también **CR-08**). |
| UR-2 | El dashboard comercial filtra siempre por `created_by = yo`, ignorando `view_all` | ⚠️ rama — ningún rol del sistema con `view_all` cae en esa vista; afectaría a roles personalizados (2.4) | Las consultas usan los scopes `visibleTo()`. Test: un rol con `view_all` en la vista comercial ve los registros de todos. |
| UR-3 | Cinco consultas del dashboard comercial se ejecutan antes de comprobar permisos | ✅ rama | Cada dato se calcula solo si hay permiso (métodos `quotationStats`, `recentQuotes`, `recentSalesOrders`). |
| UR-4 | 20 ítems del menú con `allowedRoles` inalcanzable | ✅ rama | Eliminados los 22 `allowedRoles` y la lógica por rol del menú. |
| UR-5 | `CheckRole` y su alias `role` son código muerto | ✅ rama | Eliminados el middleware y el alias de `bootstrap/app.php`. |

**Verificación:** 866 tests OK, Pint, ESLint y TypeScript limpios. El menú no tiene tests automáticos (el proyecto no
tiene tests de frontend): **revisarlo en la prueba manual por rol**.

---

## 2. Verificación de la revisión CR

| ID | Hallazgo reportado | Veredicto | Origen | Severidad real | Nota de la verificación |
| --- | --- | --- | --- | --- | --- |
| CR-01 | La ficha del pedido envía `product`, `productVariant` y `creator` completos | ✅ | previo (jul-2026) | **Alta** | `Product` y `ProductVariant` no ocultan campos: **Comercial recibe `current_cost`, `cif_percentage`, `price_threshold` y `sales_margin`**, lo que viola el principio 1 de la matriz. El listado de pedidos también envía el `creator` completo (correo, teléfono, firma). La pantalla solo usa `product.code`, `product.name`, la variante y `creator.name`. |
| CR-02 | `getAllPermissions()` en cada request: rendimiento y "seguridad" | ⚠️ | rama | Baja | Son 3 consultas pequeñas por petición (permisos directos, roles, permisos de roles), no un N+1. Que el usuario vea **sus propios** permisos no es una vulnerabilidad: la autorización se decide en servidor. **La sugerencia de cachear 5 min se descarta**: tras cambiar el rol, el usuario conservaría permisos viejos hasta 5 minutos. |
| CR-03 | Rutas `edit/update` con permiso plano "permiten saltarse el dueño" | ⚠️ | rama | Baja | **No hay bypass**: `edit()` autoriza con la policy y `UpdateQuotationRequest`/`UpdatePaintDevelopmentRequest`/`UpdateSalesOrderRequest` también. Sí es una inconsistencia con `show` (`can:view,…`). Mejora de defensa en profundidad. |
| CR-04 | Doble autorización en `SalesOrderController::store` y `created_by` nulo | ⚠️ | previo | Muy baja | La ruta exige sesión: `created_by` no puede ser nulo en la práctica. La doble autorización (middleware + request + controlador) es la convención de 17 controladores del proyecto. Solo el `$user?->id` es un detalle a limpiar. |
| CR-05 | Lógica de creación del pedido en el controlador, no en una Action | ✅ | previo | Media (deuda) | Contradice el patrón de `CLAUDE.md` (Actions para casos de uso de escritura). Fuera del alcance RBAC. |
| CR-06 | La ficha de la orden carga todas las materias primas activas | ✅ | previo (abr-2026) | Media (rendimiento) | Solo se usan para ajustes de línea: bastaría enviarlas cuando `can.updateOperationalData` o como prop diferida. |
| CR-07 | `Permission::defaultRoles()` con líneas de hasta 837 caracteres | ✅ | rama | Baja (mantenibilidad) | Código generado desde la matriz. Reformatear con un permiso por línea; no cambia el comportamiento. |
| CR-08 | `UserRole` (TS) no incluye `super-admin` | ✅ | previo | Baja | **Corregido** junto con UR-1. |
| CR-09 | `QuotationController::enumOptions()` duplica `EnumOptions::for()` con `{id,label}` | ✅ | previo | Baja (deuda) | Requiere adaptar el `Combobox` del frontend. |
| CR-10 | `tests/verify_production_lock.php` es un script manual | ✅ | previo (abr-2026) | Baja | Pest nunca lo ejecuta (no termina en `Test.php`). Convertir a test o eliminar. |
| CR-11 | 8 archivos tocados sin `declare(strict_types=1)` | ✅ | previo (archivos tocados por la rama) | Baja | En total, 67 de 249 archivos de `app/` no lo tienen. Añadirlo puede cambiar coerciones de tipo: hacerlo con la suite. |
| CR-12 | Mezcla de `auth()->user()` y `$request->user()` | ✅ | mixto | Baja (estilo) | 19 usos de `auth()->user()` en 10 controladores; varios añadidos en esta rama. |
| CR-13 | `CostController::update` autoriza con un permiso en vez de una ability de policy | ⚠️ | rama (autorización) / previo (validación) | Baja | Es un uso válido de `Gate`, pero rompe el patrón de policies. La validación de margen en el controlador es previa. |
| CR-14 | `warehouseContext` sin tipar en `global.d.ts` | ✅ | previo | Baja | Se absorbe por el índice `[key: string]: unknown`. |
| CR-15 | Controladores en la raíz vs. subcarpetas | ⚠️ | previo | — | Preferencia de organización, no un defecto. Backlog. |
| CR-16 | Flash con `message` (usuarios) y `success` (resto) | ✅ | previo | Muy baja | `FlashMessages` lee ambas claves: no hay fallo visible. Unificar en `success`. |
| CR-17 | `clientOptions()` "podría filtrar columnas internas" | ⚠️ | previo | Muy baja | **No filtra**: selecciona 5 columnas explícitas. Solo el PHPDoc es impreciso. |
| CR-18 | `SalesOrderStatus::Pending->value` en vez del enum | ✅ | previo | Muy baja | Cosmético (el modelo castea el enum). |
| CR-19 | `profile` del dashboard con posibles valores en español | ❌ | — | — | `DashboardProfile` envía `admin`, `production`, `plant`, `commercial`, `none`: coincide exactamente con el tipo TS. |
| CR-20 | Imports sin usar en el frontend | ❌ | — | — | `npm run lint:check` pasa sin errores ni avisos. |

**Hallazgo adicional de la verificación (no estaba en ninguna revisión):**

| ID | Hallazgo | Veredicto | Origen | Nota |
| --- | --- | --- | --- | --- |
| V-01 | `QuotationService::catalogProducts()` envía `sales_margin` de cada producto a las pantallas de cotización | ⚠️ | previo | No es un costo, pero junto con el precio permite deducir el precio interno. **Decisión de negocio:** ¿Comercial debe conocer el margen? Si no, calcular el precio de venta en servidor y enviar solo el resultado. |

---

## 3. Plan de trabajo

### Lote A — Antes del merge a `develop` (en esta rama)

> **✅ Aplicado (2026-09-15):** A1, A2, A3 y A4.

| # | Tarea | Hallazgos | Esfuerzo | Criterio de aceptación |
| --- | --- | --- | --- | --- |
| A1 | Transformar la ficha y el listado de pedidos a arrays explícitos: producto (`id`, `code`, `name`), variante (`id`, `name`, `presentation_label`), creador (`name`) y cliente del listado (`id`, `business_name`) | CR-01 | 1 h | Test: Comercial abre su pedido y el payload no contiene `current_cost`, `cif_percentage`, `price_threshold`, `sales_margin` ni el correo del creador. |
| A2 | Autorizar con la policy en la ruta, además del permiso, todas las acciones sobre registros con dueño: edición, cambio de estado, conversión, envío y PDF de cotizaciones, pedidos y desarrollo de pinturas (12 rutas) | CR-03 | 30 min | El test del mapa de rutas sigue en verde y un comercial recibe 403 en la ruta de edición de una cotización ajena. |
| A3 | Reformatear `Permission::defaultRoles()` con un permiso por línea | CR-07 | 20 min | Mismo resultado en `PermissionRegistryTest`; ninguna línea supera 120 caracteres. |
| A4 | Documentar la decisión sobre CR-02: se mantiene la lista de permisos compartida, **sin caché** | CR-02 | 10 min | Nota en `PLAN_FASE_2_RBAC.md` (2.5). |

Después de A1–A4: repetir `/ultrareview develop`, hacer la prueba manual por rol y el merge a `develop`.

### Lote B — Limpieza técnica (rama nueva `chore/…`, tras el merge)

| # | Tarea | Hallazgos | Esfuerzo |
| --- | --- | --- | --- |
| B1 | `declare(strict_types=1)` en los 8 archivos señalados (luego progresivamente en los 67) | CR-11 | 30 min + suite |
| B2 | Estandarizar `$request->user()` en controladores que reciben `Request` | CR-12, CR-04 | 45 min |
| B3 | Unificar flash en `success` (`UserController`) | CR-16 | 15 min |
| B4 | Tipar `warehouseContext` (y los demás props compartidos) en `global.d.ts` | CR-14 | 20 min |
| B5 | `CostController`: ability de policy (`ProductPolicy::updateCost`) y validación del margen en `UpdateCostRequest` | CR-13 | 45 min |
| B6 | Detalles: enum directo en `store`, PHPDoc de `clientOptions()` | CR-18, CR-17 | 10 min |
| B7 | Convertir `verify_production_lock.php` en test Pest o eliminarlo si ya está cubierto | CR-10 | 30 min |

### Lote C — Refactors de arquitectura (backlog, fuera de la Fase 2)

| # | Tarea | Hallazgos |
| --- | --- | --- |
| C1 | `CreateSalesOrderAction` siguiendo el patrón de producción | CR-05 |
| C2 | Materias primas de la ficha de orden como prop diferida o solo si la orden es operable | CR-06 |
| C3 | Unificar `enumOptions()` con `EnumOptions::for()` y adaptar el `Combobox` | CR-09 |
| C4 | Reorganizar controladores de la raíz en subcarpetas por dominio | CR-15 |

### Decisiones pendientes

1. **V-01:** ¿Comercial debe recibir el margen de venta de cada producto en las cotizaciones?

### Descartados

- **CR-19** y **CR-20**: no se sostienen tras verificar el código.
- **Caché de permisos de CR-02**: introduciría permisos desactualizados tras un cambio de rol.
