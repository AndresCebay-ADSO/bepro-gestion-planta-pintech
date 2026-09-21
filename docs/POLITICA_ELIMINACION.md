# Política de eliminación y relaciones

Fuente de verdad sobre cómo se "quita" un registro en Pintech OS: qué se desactiva, qué se elimina, qué no se toca
nunca y qué reglas de borrado llevan las claves foráneas. Sustituye a las tareas 2.6 (soft deletes) y 2.7 (eliminación
inteligente) de `PLAN_FASE_2_RBAC.md`, que se funden en una sola.

Decidida el 2026-09-18, tras la revisión de la rama `feature/data-integrity-soft-deletes`. Todo módulo o tabla nueva
debe seguirla (ver la guía del §6).

---

## 1. Conceptos

| Forma | Qué hace | Cuándo |
| --- | --- | --- |
| **Desactivar** (`is_active = false`) | La fila se queda. Sale de los selectores de documentos nuevos; el historial, los PDF y los reportes la siguen mostrando. Se puede reactivar. | El registro **tiene historial** |
| **Eliminar** (borrado físico, `DELETE`) | La fila desaparece. La auditoría (`activity_log`) guarda una copia de sus campos y quién la borró. | El registro **nunca se usó** |
| ~~Soft delete~~ (`deleted_at`) | **No se usa.** Ver §2. | — |

**Documentos del negocio** (cotizaciones, pedidos, órdenes, solicitudes de desarrollo): ni se desactivan ni se
eliminan; **se cancelan por estado** (decisión previa, `PLAN_FASE_2_RBAC.md` §6).

**Libro contable** (movimientos y lotes de inventario): ni se desactivan ni se eliminan; **se corrigen con un
movimiento contrario registrado a mano**, con una nota que explique la corrección (decisión del 2026-09-21: sin
acción "Revertir movimiento").

---

## 2. Por qué no usamos soft deletes

Laravel oculta automáticamente las filas con `deleted_at` en **todas** las consultas, también cuando otro registro las
busca por su relación. Verificado el 2026-09-18 en la base de desarrollo (simulación revertida): al borrar lógicamente
el producto de una orden de producción,

- `$orden->product` pasa a `NULL`: los PDF, Excel y fichas de la orden fallan o salen en blanco (36 lecturas de
  `->product->`, 23 de `->productVariant->`, 13 de `->warehouse->`, 6 de `->formula->`; hay un solo `withTrashed()`);
- la orden desaparece de los filtros que usan `whereHas('product')`;
- las fórmulas del producto borrado siguen visibles, huérfanas;
- el código del producto queda ocupado para siempre por el índice único.

Además, varios modelos tenían a la vez `is_active` y `deleted_at` para lo mismo. Desactivar resuelve el caso de uso
("ya no lo uso, pero existió") sin romper el historial, y es el patrón que ya seguían materias primas y usuarios.

Consecuencia: **los índices únicos se quedan como están** (sin índices parciales). Un código desactivado sigue ocupado,
y es lo correcto, porque el historial lo referencia.

---

## 3. Qué se hace con cada entidad

### 3.1 Datos maestros: desactivar + eliminar si nunca se usó

| Entidad | Desactivar / reactivar | Eliminar (solo sin historial) | Se bloquea desactivar si… |
| --- | --- | --- | --- |
| Producto | `products.deactivate` (Admin) | `products.delete` (SuperAdmin). Se lleva sus variantes y documentos si tampoco tienen historial | tiene órdenes de producción en curso, o stock de producto terminado en alguna presentación |
| Variante (presentación) | `products.manage_variants` (Admin, Producción) | `products.manage_variants` | tiene stock de producto terminado, o una orden en curso la va a envasar |
| Fórmula | `formulas.activate`: activar una versión desactiva la anterior (una activa por producto) | `formulas.delete` (SuperAdmin). En la práctica, solo una versión sin costos calculados ni órdenes | nunca: una orden en curso conserva sus propios detalles y no necesita que su fórmula siga activa |
| Materia prima | `raw_materials.deactivate` (Admin) — ya implementado | `raw_materials.delete` (SuperAdmin) — ya implementado | tiene lotes con stock — ya implementado |
| Bodega | `warehouses.edit` (Admin) | `warehouses.delete` (SuperAdmin) | tiene stock de materia prima o de producto terminado, saldos de producción disponibles u órdenes en curso |
| Cliente | 🆕 `clients.deactivate` (Admin); 🆕 columna `is_active` | `clients.delete` (Admin) | nunca: sus cotizaciones y pedidos abiertos siguen su curso |
| Usuario | `users.edit` (`is_active`) — ya implementado | `users.delete` (SuperAdmin) — ya implementado | es el último SuperAdmin activo — ya implementado |
| Unidad de medida, categorías | `catalogs.edit` (Fase 3) | `catalogs.delete` (Fase 3) | — (llegan con sus CRUD; añadir auditoría antes) |

**Desactivar un producto** oculta también sus variantes y fórmulas en los selectores, aunque cada una conserve su
propio `is_active`: los selectores filtran por el producto y por el hijo.

**Principio de los bloqueos:** no se desactiva lo que tiene valor en libros (stock, saldos de producción) ni lo que una
orden en curso va a usar. Después ningún selector lo ofrecería y ese valor quedaría atrapado.

**Movimientos de producto terminado:** no se aceptan entradas a un producto o presentación inactivos; las salidas y los
traslados sí, para poder dar salida a lo que quede.

**Reactivar** siempre está permitido, con el mismo permiso que desactivar.

### 3.2 Documentos del negocio: se cancelan

Cotización, pedido de venta, orden de producción, solicitud de desarrollo de pintura. **Sin eliminar ni desactivar.**
Se retira el `SoftDeletes` que tenían (código muerto: ninguna ruta los borraba).

### 3.3 Historial y saldos: nunca se tocan a mano

| Tabla | Por qué |
| --- | --- |
| `inventory_movements`, `inventory_batches` | Libro contable de materia prima: se corrige con un movimiento contrario manual y una nota |
| `finished_inventory_movements`, `finished_product_batches`, `transfers` | Libro contable de producto terminado |
| `production_remnants`, `remnant_consumptions` | Movimientos de saldos de producción |
| `price_lists`, `production_costs` | Historial de precios y costos |
| `finished_inventories`, `finished_product_batch_stocks` | Saldos calculados por los movimientos |
| `alerts` | Se resuelven, no se borran |

### 3.4 Datos auxiliares: se eliminan físicamente

| Tabla | Regla |
| --- | --- |
| `production_order_line_adjustments`, `production_order_packaging_plan` | Solo mientras la orden está en proceso (ya implementado) |
| `product_documents`, `qr_documents` | Borrado físico **del registro y del archivo**, con auditoría (hoy no auditan: añadir `LogsActivity`) |
| `formula_details` | Se van con su fórmula (cascada) |
| `qr_codes` | Siguen a su orden: se desactivan (`is_active`), no se eliminan. Se retira `SoftDeletes` |

---

## 4. Claves foráneas: la base de datos decide si hay historial

**Regla:** toda clave foránea hacia historial es `RESTRICT`. `CASCADE` solo en hijos sin valor propio. **Nunca
`SET NULL` hacia historial** (borraría la referencia sin avisar).

**Eliminar = intentar el `DELETE`.** Si PostgreSQL lo rechaza por clave foránea (SQLSTATE `23503`), el registro tiene
historial y la aplicación responde "tiene historial; desactívalo en su lugar". Así ninguna lista de relaciones se
mantiene a mano: una tabla nueva queda protegida por su propia clave foránea. SQLite aplica también las claves
foráneas en los tests (`foreign_key_constraints`).

La única relación sin clave foránea es la autoría en `activity_log` (`causer`): la comprobación de usuarios la
conserva (`User::hasActivity()` se reduce a esa consulta).

### 4.1 Se quedan en `CASCADE` (hijos sin valor propio)

`formula_details` → `formulas` · `qr_documents` → `qr_codes` · `finished_product_batch_stocks` →
`finished_product_batches` · hijos de una orden (`production_order_details`, `_line_adjustments`, `_packaging_plan`,
`qr_codes`) → `production_orders` (las órdenes no se eliminan; la cascada no llega a actuar) · `alerts` →
`raw_materials` / `inventory_batches` · `warehouse_user` → `users` / `warehouses` · tablas de Spatie (roles y permisos).

### 4.2 Pasan a `RESTRICT`

| Padre | Hijo | Antes |
| --- | --- | --- |
| `products` | `price_lists`, `production_costs`, `formulas`, `finished_inventories`, `product_variants`, `product_documents`, `qr_codes` | CASCADE |
| `warehouses` | `finished_inventories` | CASCADE |
| `product_variants` | `finished_inventories`, `finished_inventory_movements`, `finished_product_batches`, `price_lists`, `transfers` | SET NULL |
| `production_orders` | `finished_inventory_movements`, `finished_product_batches`, `inventory_movements`, `production_costs`, `remnant_consumptions` (`target_order_id`) | SET NULL |
| `finished_product_batches` | `finished_inventory_movements` | SET NULL |
| `raw_materials` | `product_variants.package_raw_material_id` | SET NULL |
| `quotations` / `sales_orders` | `sales_orders.quotation_id` / `quotations.convert_to_order_id` | SET NULL |
| `users` | `alerts.resolved_by`, `alerts.updated_by`, `paint_development_requests.reviewed_by`, `price_lists.created_by`, `production_orders.submitted_by`, `production_orders.reviewed_by` | SET NULL |
| `users` / `raw_materials` | `production_order_line_adjustments.created_by`, `production_orders.quality_responsible_user_id`, `production_order_line_adjustments.raw_material_id` | sin regla (NO ACTION) |

Eliminar un producto sin historial se lleva antes, en la misma transacción, sus variantes y documentos sin uso: con
`RESTRICT` la base de datos ya no lo hace sola.

---

## 5. Cómo se implementa

- **Migraciones:** no hay producción, así que se editan las migraciones originales (quitar `softDeletes()`, cambiar
  reglas de borrado, añadir `clients.is_active`) y las bases se recrean con `migrate:fresh --seed`. Desde producción,
  todo cambio de esquema irá en migraciones nuevas.
- **Modelos:** se retira `SoftDeletes` de los 14 modelos que lo usan (`Client`, `Formula`, `PaintDevelopmentRequest`,
  `Product`, `ProductCategory`, `ProductDocument`, `ProductVariant`, `QrCode`, `QrDocument`, `Quotation`,
  `RawMaterialCategory`, `SalesOrder`, `UnitOfMeasure`, `Warehouse`), el `withTrashed()` de `FormulaController` y las
  policies `restore` / `forceDelete` (B16).
- **Eliminar:** una Action reutilizable que abre una transacción, bloquea la fila, borra y traduce el `23503` a un
  mensaje de "tiene historial". Cada módulo la usa en su `destroy`.
- **Desactivar:** endpoint propio por módulo con su permiso y sus reglas de bloqueo (§3.1), como
  `RawMaterialController`.
- **Selectores:** todo selector para documentos nuevos filtra `is_active` (el registro y, en variantes y fórmulas,
  también su producto). Revisar los ~40 puntos que hoy filtran y los que faltan.
- **Pantallas:** botón "Desactivar/Activar" y botón "Eliminar" separados, cada uno con su permiso; listados con filtro
  de activos/inactivos.
- **Tests (en SQLite y PostgreSQL):** por módulo, eliminar sin historial funciona; con historial devuelve el mensaje y
  no borra nada; desactivar respeta sus bloqueos; lo desactivado no aparece en selectores pero sí en el historial.

**Orden:** (1) esquema y modelos · (2) Action de eliminar · (3) módulo por módulo: productos y variantes, bodegas,
fórmulas, clientes, documentos · (4) selectores · (5) usuarios (`hasActivity`) · (6) documentación y matriz.

---

### 5.1 Estado de la implementación (2026-09-18, rama `feature/data-integrity-soft-deletes`)

- ✅ Esquema: 15 claves foráneas en `CASCADE` (las del §4.1) y 82 en `RESTRICT`; ningún `SET NULL`. `clients.is_active`.
- ✅ Retirado `SoftDeletes` de los 14 modelos, los filtros `whereNull('deleted_at')`, el `withTrashed()` y las
  policies `restore` / `forceDelete` (B16).
- ✅ `App\Actions\Shared\DeleteUnusedRecordAction` (intenta el borrado; traduce el `23503` de PostgreSQL y el
  `FOREIGN KEY constraint failed` de SQLite).
- ✅ `App\Services\DeactivationGuardService`: bloqueos de producto (órdenes en curso), presentación (stock de producto
  terminado) y bodega (stock u órdenes en curso). `ProductionOrderStatus::open()`.
- ✅ Productos (se llevan sus presentaciones y documentos sin historial), presentaciones, bodegas, fórmulas y clientes
  (permiso `clients.deactivate`, casilla "Cliente activo", listado con estado y `<TableActions />`).
- ✅ Documentos de producto: auditados; el archivo se borra con `DB::afterCommit` (si la transacción se revierte, se
  conserva). La firma de usuario sigue el mismo patrón.
- ✅ Usuarios: `hasActivity()` solo consulta la auditoría; el resto lo protegen las claves foráneas (B11).
- ✅ Selectores: bodegas activas en los formularios de movimientos (MP y PT) y en su validación; envases activos en el
  formulario de presentaciones. Los filtros de historial siguen mostrando todo.
- ✅ Tests en `tests/Feature/Deletion/` (SQLite y PostgreSQL).
- Materias primas conserva su comprobación explícita de relaciones: la pantalla la usa para ofrecer "Desactivar" o
  "Eliminar" antes de intentarlo.
- **Corrección respecto al §3.1 original:** las fórmulas no bloquean su desactivación (ver la tabla).
- **Tras la revisión de la rama:**
  - Un documento abierto conserva lo que ya usa: al editar una cotización se aceptan y se ofrecen su cliente, sus
    productos y sus presentaciones aunque estén inactivos (`Quotation::keptRecords()`). Con productos y presentaciones
    el bloqueo ya existía antes de esta rama.
  - Materias primas elimina con `DeleteUnusedRecordAction` y, si tiene historial, desactiva: su lista manual omitía el
    uso como envase y los ajustes de línea, y el borrado respondía 500. El diálogo cuenta ahora esas dos relaciones.
  - Listados de clientes, bodegas y productos con filtro de estado (`QueryFilter::applyActiveStatus`).
  - Desactivar producto, presentación o bodega comprueba y guarda en una transacción con la fila bloqueada, y
    `CreateProductionOrderAction` toma un bloqueo compartido sobre el producto y la bodega y vuelve a comprobar que
    siguen activos: una orden y una desactivación simultáneas se serializan.
  - `ClientPolicy::deactivate` y `ProductPolicy::deactivate`.
  - **Riesgo aceptado:** los movimientos de inventario (MP, PT y traslados) no toman el bloqueo compartido sobre la
    bodega. Una bodega podría quedar desactivada con stock que entró en el mismo instante; no se pierde nada (el stock
    sigue visible y basta reactivarla) y evitarlo exigiría tocar los servicios de movimientos, la parte más delicada del
    costeo. Las órdenes de producción sí lo toman, porque una orden en curso en una bodega inactiva rompe el flujo.
- **Tras la segunda revisión (2026-09-21):**
  - Bloqueos nuevos: producto con stock de producto terminado, presentación que una orden en curso va a envasar y
    bodega con saldos de producción disponibles.
  - Entradas de producto terminado rechazadas si el producto o la presentación del lote están inactivos.
  - El selector de envase de una presentación ofrece también su envase actual si está inactivo, marcado "(inactivo)"
    (antes aparecía vacío y el formulario lo reenviaba sin mostrarlo).
  - Una materia prima inactiva sin historial se puede eliminar (antes había que reactivarla primero).
  - Casilla "Presentación activa" en el diálogo de edición de presentaciones (antes el backend lo permitía, pero
    ninguna pantalla lo ofrecía). La casilla "Producto activo" solo se muestra con `products.deactivate`: con un rol
    personalizado que solo edita, el 403 rechazaba el guardado completo.
  - **Riesgo aceptado:** `UserController::destroy` consulta la auditoría (`hasActivity()`) antes de la transacción. Un
    usuario que registra su primera acción justo mientras lo eliminan dejaría una fila de auditoría con un autor que ya
    no existe; el propio borrado queda auditado. Moverla dentro de la transacción no lo cierra (escribir en la
    auditoría no bloquea al usuario), y cerrarlo exigiría bloquear en cada escritura de auditoría.
  - **Se deja como aviso, no como bloqueo:** desactivar una materia prima que usa una fórmula activa. Suele hacerse
    justamente para reemplazarla en la fórmula; el diálogo ya indica que tiene fórmulas.
- **Bug previo corregido:** `UpdateWarehouseRequest` no importaba `App\Models\Warehouse` y respondía 403 a toda
  edición de bodega.

## 6. Guía para una tabla o módulo nuevo

1. **¿Otros registros lo usan o lo usarán?** → `is_active` + eliminar solo sin historial (§3.1).
2. **¿Es un documento del negocio con número y estado?** → se cancela por estado; no se elimina.
3. **¿Es un movimiento o un saldo?** → inmutable; se corrige con un movimiento contrario manual y una nota.
4. **¿Es un dato auxiliar de otro?** → se elimina físicamente con su padre (`CASCADE`) o mientras el padre está en
   borrador.

Y siempre: claves foráneas `RESTRICT` hacia lo que es historial, nunca `SET NULL`; nunca `SoftDeletes`; el modelo que
se puede eliminar físicamente debe usar `LogsActivity`.

---

## 7. Fuera de alcance (anotado)

- Las unidades de medida tienen `is_active`, pero productos, presentaciones y materias primas no lo filtran al validar
  (las fórmulas sí). Hoy ninguna pantalla desactiva unidades; cerrarlo con el CRUD de catálogos (Fase 3).

- `clients.nit` es único solo en la validación (`Store/UpdateClientRequest`), no en la base de datos. Decidir en otra
  tarea si se añade el índice único.
