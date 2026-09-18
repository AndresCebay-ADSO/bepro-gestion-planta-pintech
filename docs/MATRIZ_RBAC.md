# 🛡️ Matriz de Control de Accesos y Permisos (RBAC) — v2

> **Estado:** borrador para cerrar la tarea 2.1 · **Fecha:** 2026-09-11
> **Fuente de verdad:** esta matriz define el comportamiento objetivo. Los permisos actuales se generaron
> con vibe coding y **no son un contrato**; donde la matriz y el código difieren, gana la matriz
> (ver §6 para las diferencias que habrá que comunicar a los usuarios).
>
> **Instrucciones en Obsidian:** `[x]` activa un permiso, `[ ]` lo deja inactivo. Para añadir un rol, agrega
> una columna; para añadir un permiso, agrega una fila.
>
> **Leyenda de la columna Descripción:**
> 🔁 cambié tu valor (motivo al lado) · ⚠️ cambia respecto a lo que el software hace hoy · ❓ decisión pendiente (§7)

---

## 0. Quién es quién en Pintech

| Rol | Quién es en la empresa | Consecuencia para la matriz |
| --- | --- | --- |
| **SuperAdmin** | Soporte / tecnología | Todos los permisos vía seeder (sin `Gate::before`). Único que gestiona roles, catálogos, auditoría y borrados físicos. |
| **Admin** | La jefa de la empresa **y** jefa de producción | Conserva el control del catálogo técnico: fórmulas, productos, costos, cancelaciones y revisiones sensibles. |
| **Producción** | Auxiliares y ayudantes de la jefa | Ejecutan y supervisan en planta (crear, operar, revisar y completar órdenes), pero no definen fórmulas ni productos. |
| **Operador** | Personal de planta | Ejecuta las órdenes. |
| **Comercial** | Ventas | Clientes, cotizaciones, pedidos y solicitudes de desarrollo. Ve precios, nunca costos. |

**Regla de asignación:** un rol por usuario, sin permisos directos por usuario en esta fase. Si alguien necesita otra
combinación, se crea un rol nuevo.

---

## 1. Convenciones

- **Formato de la key:** `modulo.accion`, snake_case, en inglés (`products.deactivate`, no `products.desactive`).
- **`view_own` y `view_all`:** en los módulos con dueño (cotizaciones, pedidos, desarrollo de pinturas) no existe un
  `view` a secas. `view_own` = ver los registros propios (`created_by = yo`); `view_all` = ver los de todos (incluye los
  propios). Para entrar al módulo basta con tener uno de los dos. Las acciones sobre registros ajenos (editar, cambiar
  estado, convertir) exigen además `view_all`. Los módulos sin dueño (productos, alertas…) siguen usando `view`.
- **Granularidad:** un permiso = una capacidad que el negocio querría poder apagar por separado. No un permiso por endpoint.

## 2. Principios de diseño (aplican a toda la matriz)

1. **Un costo es un costo.** Un único permiso, `costs.view`, controla la visibilidad de costos en **todo** el sistema:
   página de costos, columnas de costo en materias primas y productos, costo de remanentes, preview de costos de la orden
   y costos en PDF/Excel de órdenes. Así ningún módulo puede filtrar costos por olvidarse de su propio `view_costs`.
2. **Precio ≠ costo.** El *costo* es lo que le cuesta a Pintech producir (materia prima, envase, CIF). El *precio* es lo
   que paga el cliente. Comercial ve precios (los necesita para cotizar), pero **nunca** ve costos ni márgenes.
   Ejemplo real: `PriceListService` ya muestra los precios a comercial y oculta los costos si no es admin.
   **Ojo con los nombres de columna:** `current_price` de productos y presentaciones es el *precio interno*
   (costo × (1 + CIF %)) y cuenta como costo, igual que `sales_margin`; el precio de venta es `sales_price`, calculado en
   servidor. En materias primas, `current_price`, `previous_price` y el `unit_price` de los lotes también son costo.
3. **Admin desactiva, SuperAdmin elimina.** Donde existe `deactivate`, el borrado físico queda solo para SuperAdmin
   y **solo si el registro está intacto** (sin relaciones). Si tiene historial, la eliminación se niega y se ofrece desactivar.
4. **Los documentos transaccionales no se eliminan, se cancelan.** Órdenes de producción, pedidos y cotizaciones
   tienen un estado `cancelled`/`rejected`; borrarlos rompería la trazabilidad y dejaría huecos en el consecutivo.
5. **Los movimientos de inventario son inmutables.** Movimientos MP y PT no se editan ni se eliminan; un error se
   corrige con un movimiento compensatorio. (MP y PT ya lo son; el flujo de reverso llega en la Fase 2B — ver §5.)
6. **Permiso ≠ regla de estado.** El permiso dice *"este rol puede completar órdenes"*; la regla de estado dice
   *"esta orden está en un estado completable"*. Las reglas de estado y de dueño **no** son permisos (§4).

---

## 3. Matriz

| Módulo | Permiso (key) | SuperAdmin | Admin | Producción | Operador | Comercial | Descripción |
| :-- | :-- | :-: | :-: | :-: | :-: | :-: | :-- |
| **Dashboard** | `dashboard.view` | [x] | [x] | [x] | [x] | [x] | Las tarjetas se muestran según los permisos de cada módulo (ver §5, Dashboard). |
| **Usuarios** | `users.view` | [x] | [x] | [ ] | [ ] | [ ] | Listado de personal. |
| | `users.create` | [x] | [x] | [ ] | [ ] | [ ] | Crear empleado asignando rol. |
| | `users.edit` | [x] | [x] | [ ] | [ ] | [ ] | Datos, firma y activar/desactivar (`is_active`). |
| | `users.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Borrado físico solo si el usuario está intacto; si tiene actividad → desactivar. ⚠️ hoy Admin puede borrar. |
| | `users.manage_roles` | [x] | [x]\* | [ ] | [ ] | [ ] | \*Admin asigna admin/producción/comercial/operador. `super-admin` nunca aparece para Admin (filtrado en servidor). |
| **Roles** | `roles.view` | [x] | [ ] | [ ] | [ ] | [ ] | Exclusivo de SuperAdmin. |
| | `roles.create` | [x] | [ ] | [ ] | [ ] | [ ] | Exclusivo de SuperAdmin. |
| | `roles.edit` | [x] | [ ] | [ ] | [ ] | [ ] | Incluye asignar permisos al rol. |
| | `roles.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Los 5 roles del sistema no se pueden eliminar ni renombrar. No se elimina un rol con usuarios asignados. |
| **Auditoría** | `audit_logs.view` | [x] | [ ] | [ ] | [ ] | [ ] | ⚠️ hoy lo ve Admin (vía `Gate::define('view-audit-logs')`). |
| **Catálogos** (Configuración) | `catalogs.view` | [x] | [x] | [ ] | [ ] | [ ] | Unidades de medida, categorías de producto y de materia prima. Los `<select>` de los formularios **no** requieren este permiso. |
| | `catalogs.create` | [x] | [ ] | [ ] | [ ] | [ ] | CRUDs de la Fase 3 (3.1, 3.2). Viven en el menú Configuración. |
| | `catalogs.edit` | [x] | [ ] | [ ] | [ ] | [ ] | |
| | `catalogs.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Solo si nadie usa el valor; si no, desactivar. |
| **Productos** | `products.view` | [x] | [x] | [x] | [ ] | [x] | |
| | `products.create` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción puede crear. Cambio intencional (§0). |
| | `products.edit` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción puede editar; cambio intencional (§0). Cambiar CIF % o umbral de precio exige además `costs.update`. |
| | `products.deactivate` | [x] | [x] | [ ] | [ ] | [ ] | 🔁 corregido el typo `desactive`. |
| | `products.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Solo si está intacto. ⚠️ hoy Admin puede, y **sin comprobar relaciones**. |
| | `products.manage_variants` | [x] | [x] | [x] | [ ] | [ ] | Presentaciones, dentro de la página del producto (no tendrán página propia). |
| | `products.manage_documents` | [x] | [x] | [ ] | [ ] | [ ] | Subir y eliminar fichas técnicas y hojas de seguridad. |
| | `products.download_documents` | [x] | [x] | [x] | [ ] | [x] | 🆕 Hoy lo hace cualquiera que vea el producto; se separa de `manage_documents`. |
| **Costos** | `costs.view` | [x] | [x] | [ ] | [ ] | [ ] | 🆕 alcance global (principio 1): sustituye a `products.view_costs`, `raw_materials.view_costs` y `production_orders.preview_costs`, y oculta el costo de remanentes. |
| | `costs.update` | [x] | [x] | [ ] | [ ] | [ ] | Márgenes, CIF % y umbral de precio (en la página de costos y en el formulario de producto). |
| **Fórmulas** | `formulas.view` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción ve, crea y edita fórmulas. Cambio intencional (§0). |
| | `formulas.create` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ |
| | `formulas.edit` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ |
| | `formulas.activate` | [x] | [x] | [ ] | [ ] | [ ] | Activar una versión desactiva la anterior. |
| | `formulas.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Solo si ninguna orden la usó. ⚠️ hoy Admin puede. |
| **Materias primas** | `raw_materials.view` | [x] | [x] | [x] | [ ] | [ ] | Sin costos salvo `costs.view`. |
| | `raw_materials.create` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `raw_materials.edit` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `raw_materials.deactivate` | [x] | [x] | [ ] | [ ] | [ ] | 🆕 Se separa de `delete`. Se niega si hay lotes con stock. |
| | `raw_materials.reactivate` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `raw_materials.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Borrado físico solo si no tiene lotes, movimientos, fórmulas ni órdenes. |
| **Órdenes de producción** | `production_orders.view` | [x] | [x] | [x] | [x] | [ ] | 🔁 Comercial `[ ]` (§7.3). Sin costos salvo `costs.view`. |
| | `production_orders.create` | [x] | [x] | [x] | [ ] | [ ] | |
| | `production_orders.operate` | [x] | [x] | [x] | [x] | [ ] | Iniciar, ajustes de línea, plan de empaque y consumo de remanentes. |
| | `production_orders.submit_for_review` | [x] | [x] | [x] | [x] | [ ] | ⚠️ hoy solo Operador puede enviar a revisión. |
| | `production_orders.reject_review` | [x] | [x] | [x] | [ ] | [ ] | |
| | `production_orders.complete` | [x] | [x] | [x] | [ ] | [ ] | Descuenta inventario FIFO. |
| | `production_orders.cancel` | [x] | [x] | [ ] | [ ] | [ ] | Coincide con hoy: el código usa `can('delete')` para cancelar. **Una orden no se elimina** (principio 4). |
| | `production_orders.export` | [x] | [x] | [x] | [x] | [ ] | PDF + Excel juntos. Los costos del archivo dependen de `costs.view`. |
| **Remanentes** | `production_remnants.view` | [x] | [x] | [x] | [x] | [ ] | El costo por galón solo con `costs.view`. ⚠️ hoy se envía a todos (`RemnantController:47`). |
| **Movimientos MP** | `inventory_movements.view` | [x] | [x] | [x] | [x] | [ ] | ⚠️ hoy Operador no los ve. |
| | `inventory_movements.create` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción puede crear. Inmutables: no existen `edit` ni `delete` (principio 5). |
| **Inventario PT** | `finished_inventory.view` | [x] | [x] | [x] | [x] | [x] | ⚠️ hoy Operador no lo ve. La vista no muestra costos ni precios. |
| | `finished_inventory_movements.view` | [x] | [x] | [x] | [ ] | [ ] | 🔁 key renombrada (`finished_inventory.movements.view` → dos segmentos, como el resto). |
| | `finished_inventory_movements.create` | [x] | [x] | [x] | [ ] | [ ] | Inmutables (ya lo son hoy). |
| **Cotizaciones** | `quotations.view_own` | [x] | [x] | [ ] | [ ] | [x] | Solo las propias (`created_by`). |
| | `quotations.view_all` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `quotations.create` | [x] | [x] | [ ] | [ ] | [x] | |
| | `quotations.edit` | [x] | [x] | [ ] | [ ] | [x] | Solo en borrador. |
| | `quotations.update_status` | [x] | [x] | [ ] | [ ] | [x] | Se bloquea una vez convertida en pedido. |
| | `quotations.convert_to_order` | [x] | [x] | [ ] | [ ] | [x] | Solo si está aceptada y no se convirtió antes. |
| | `quotations.export_pdf` | [x] | [x] | [ ] | [ ] | [x] | |
| **Pedidos de venta** | `sales_orders.view_own` | [x] | [x] | [x] | [ ] | [x] | Solo los propios (`created_by`). |
| | `sales_orders.view_all` | [x] | [x] | [x] | [ ] | [ ] | |
| | `sales_orders.create` | [x] | [x] | [ ] | [ ] | [x] | |
| | `sales_orders.edit` | [x] | [x] | [ ] | [ ] | [ ] | Contacto, dirección, prioridad, fecha y notas. **Solo mientras está `pending`**; en cuanto arranca queda congelado. |
| | `sales_orders.update_status` | [x] | [x] | [x] | [ ] | [ ] | 🔁 Producción `[x]` (confirmado): es quien sabe cuándo el pedido pasa a `in_progress`/`ready` (hoy ya lo hace). |
| **Clientes** | `clients.view` | [x] | [x] | [ ] | [ ] | [x] | |
| | `clients.create` | [x] | [x] | [ ] | [ ] | [x] | |
| | `clients.edit` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `clients.delete` | [x] | [x] | [ ] | [ ] | [ ] | Borrado **lógico** (clientes no tiene `is_active`; ya usa SoftDeletes). Sus cotizaciones y pedidos se conservan. |
| **Listas de precios** | `price_lists.view` | [x] | [x] | [ ] | [ ] | [x] | Comercial ve precios; los costos solo con `costs.view`. |
| **Desarrollo de pinturas** | `paint_development_requests.view_own` | [x] | [x] | [ ] | [ ] | [x] | Solo las propias (`created_by`). ⚠️ hoy Producción las ve todas; se retira: solo la jefa recibe y revisa estas solicitudes. |
| | `paint_development_requests.view_all` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ |
| | `paint_development_requests.create` | [x] | [x] | [ ] | [ ] | [x] | |
| | `paint_development_requests.edit` | [x] | [x] | [ ] | [ ] | [x] | Solo en borrador. |
| | `paint_development_requests.submit` | [x] | [x] | [ ] | [ ] | [x] | |
| | `paint_development_requests.update_status` | [x] | [x] | [ ] | [ ] | [ ] | En revisión → aprobada/rechazada. ⚠️ hoy lo hace Producción; pasa a solo Admin. |
| | `paint_development_requests.export_pdf` | [x] | [x] | [ ] | [ ] | [x] | |
| **Alertas** | `alerts.view` | [x] | [x] | [x] | [ ] | [ ] | Incluye la campana de notificaciones de la cabecera. |
| | `alerts.resolve` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción puede resolver. |
| **Códigos QR** | `qr_codes.view` | [x] | [x] | [x] | [ ] | [x] | ⚠️ hoy Comercial no los ve. La landing pública `/c/{token}` no requiere sesión. |
| | `qr_codes.update` | [x] | [x] | [ ] | [ ] | [ ] | ⚠️ hoy Producción puede editar. |
| **Bodegas** | `warehouses.view` | [x] | [x] | [x] | [ ] | [x] | Solo las bodegas asignadas. |
| | `warehouses.view_all` | [x] | [x] | [ ] | [ ] | [ ] | Todas las bodegas, también en el selector de bodega de la cabecera. |
| | `warehouses.create` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `warehouses.edit` | [x] | [x] | [ ] | [ ] | [ ] | Incluye activar/desactivar. |
| | `warehouses.assign_users` | [x] | [x] | [ ] | [ ] | [ ] | |
| | `warehouses.delete` | [x] | [ ] | [ ] | [ ] | [ ] | Solo si está intacta. ⚠️ hoy Admin puede. |

**Total: 84 permisos en 21 módulos.** Por rol: SuperAdmin 84 · Admin 71 · Producción 23 · Operador 8 · Comercial 22.
(La v1 tenía 83. Entran 6: los 4 de catálogos, `products.download_documents` y `raw_materials.deactivate`.
Salen 5: ver §6. `products.desactive` solo se renombra, igual que los `view` de módulos con dueño → `view_own`.)

---

## 4. Reglas de estado y de dueño (NO son permisos)

Viven en las policies/Actions junto al permiso, nunca en la matriz. Se listan aquí para que no se pierdan al migrar.

| Regla | Dónde vive hoy |
| --- | --- |
| Nadie edita, desactiva ni elimina a un usuario con permisos que él no tiene (así, Admin no toca a un SuperAdmin) | `UserPolicy` (`User::holdsAllPermissions`) |
| Nadie asigna un rol con permisos que él no tiene | `AssignableRoleService` |
| Nadie puede desactivarse, eliminarse ni quitarse el rol a sí mismo | `UserController::update/destroy` |
| Siempre debe quedar al menos un SuperAdmin activo (única regla ligada a un rol) | `UserController::update/destroy` |
| Cotización: editar solo en `draft`; cambiar estado bloqueado si ya se convirtió; convertir solo si `accepted` | `QuotationPolicy` |
| Registros con dueño: sin `view_all` solo se actúa sobre los propios | `scopeVisibleTo` en `Quotation`, `SalesOrder`, `PaintDevelopmentRequest` |
| Orden: `operate` solo en `in_progress`/`pending_review`; `complete` solo en `in_progress`/`pending_review`; `reject_review` solo en `pending_review` | `ProductionOrderPolicy` |
| Orden en `pending_review`: **solo quien puede revisar** (`complete`) sigue operando | Hoy: `hasRole('operador')` → se reescribe sin rol |
| Pedido: `edit` solo en `pending`; las transiciones de estado siguen `SalesOrderStatus::nextTransitions()` | `UpdateSalesOrderRequest` |
| Movimiento MP generado por una orden (`production_order_id`) no se toca | `InventoryMovementPolicy` |
| Desactivar materia prima: se niega si hay lotes con stock | `RawMaterialController::destroy` |

## 5. Accesos implícitos (sin permiso) y notas de implementación

- **Perfil propio y apariencia:** todo usuario autenticado. **Se elimina la auto-eliminación de cuenta** ✅ (retirada en la 2.2, lote 4)
  (ruta `DELETE settings/profile`); una cuenta solo la desactiva un Admin.
- **Cambio de bodega activa** (`warehouses.set-current`): todo usuario autenticado, pero solo entre sus bodegas asignadas
  (o todas, con `warehouses.view_all`).
- **Dashboard:** no hay permisos `dashboard.*` por tarjeta. La entrada exige `dashboard.view`. Se conservan las 4 vistas,
  elegidas por permiso: `users.view` → administración, `production_orders.create` → producción, `production_orders.view` →
  planta, `quotations.view_own|view_all` o `sales_orders.view_own|view_all` → comercial; si no aplica ninguna, vista vacía.
  Dentro de cada vista, cada tarjeta solo aparece si el usuario tiene el permiso del dato: órdenes →
  `production_orders.view`, alertas → `alerts.view`, stock bajo y lotes por vencer → `raw_materials.view`, totales →
  `users.view` / `products.view` / `warehouses.view` / `clients.view`. Un rol nuevo nunca recibe un error.
- **Movimientos MP inmutables — ✅ retirado (2026-09-14):** se eliminaron las rutas `inventory-movements.edit/update/destroy`,
  su lógica en `InventoryService` y la página de edición, que era un placeholder desde el commit inicial: **la interfaz nunca
  permitió editar ni borrar**, pero el backend lo aceptaba por petición directa y podía reescribir el costo de un lote.
  Hoy un error se corrige con un movimiento compensatorio (salida o entrada sobre el mismo lote). Pendiente para la
  **Fase 2B**: (1) campo *motivo* en los movimientos MP (compra, ajuste, devolución, corrección…), como ya tienen los de
  producto terminado; (2) acción **"Revertir movimiento"** que cree el compensatorio enlazado al original
  (`reverses_movement_id`) y permita volver a entrar a un lote que la salida errónea dejó vacío.
- **Variantes:** siguen dentro de la página del producto. **Esto cambia la tarea 3.3** (ya no tendrán index ni
  páginas propias).

## 6. Diferencias respecto al comportamiento actual

Para comunicar a los usuarios antes de desplegar.

**Pierden acceso**
- **Producción:** crear y editar productos, todo el módulo de fórmulas, crear y editar movimientos MP, resolver
  alertas, editar códigos QR, ver y revisar solicitudes de desarrollo de pinturas.
- **Admin:** eliminar usuarios, productos, fórmulas, materias primas y bodegas (conserva la desactivación);
  ver la auditoría.
- **Todos:** editar y eliminar movimientos MP; eliminar su propia cuenta; ver costos sin `costs.view` (remanentes).

**Ganan acceso**
- **Operador:** ver movimientos MP e inventario PT.
- **Comercial:** ver códigos QR.
- **Admin y Producción:** enviar órdenes a revisión (hoy solo Operador).

**Permisos de la v1 que salen y por qué**

| Permiso v1 | Motivo |
| --- | --- |
| `products.view_costs`, `raw_materials.view_costs`, `production_orders.preview_costs` | Unificados en `costs.view` (principio 1). |
| `sales_orders.delete` | Un pedido se cancela por estado (principio 4). |
| `quotations.delete` | Hoy no tiene ruta. Se rechaza por estado; borrarla dejaría un hueco en el consecutivo. |
| `products.desactive` | Typo → `products.deactivate`. |

**Permisos que propuse en el plan y descarté tras tus notas:** `users.deactivate` y `users.manage_signatures`
(se quedan en `users.edit`), `roles.assign_permissions` (redundante: roles es 100 % SuperAdmin),
`alerts.receive_notifications` (la campana usa `alerts.view`), `finished_inventory.view_costs` (la vista no muestra
costos), `quotations.view_costs` (quien ve cotizaciones ya puede ver precios), `profile.delete_own` (se elimina la
función), `inventory_movements.edit/delete` (inmutables), `production_orders.delete` (no existe).

## 7. Decisiones

1. ✅ **Producción y el catálogo técnico — intencional.** Admin es la jefa de producción y de la empresa; Producción son
   sus auxiliares. Fórmulas y creación/edición de productos quedan en Admin.
2. ✅ **Desarrollo de pinturas — solo Admin.** Solo la jefa recibe y revisa las solicitudes. Producción y Operador no
   tienen acceso (se valoró dar vista al Operador y se descartó). Comercial ve y gestiona solo las suyas.
3. ✅ **Comercial en órdenes de producción — sin acceso.** La necesidad real (*"¿mi pedido ya se está fabricando?"*) la
   cubre el **estado del pedido**, que Producción actualiza (punto 4) y Comercial ya ve en sus pedidos. Hoy, además, una
   orden de producción no está vinculada a ningún pedido, así que el índice de órdenes no le diría si *su* pedido está en
   producción; solo le mostraría la operación de planta (cantidades, operarios, consumos), que no es asunto comercial.
   Para saber si hay stock de un producto, Comercial ya tiene `finished_inventory.view`.
   *Mejora futura:* cuando la tarea 3.4 (flujo de color cotización → pedido → orden) cree el vínculo pedido ↔ orden, se
   puede mostrar en el detalle del pedido una insignia de solo lectura con el estado de su orden, sin dar `production_orders.view`.
4. ✅ **`sales_orders.update_status` para Producción — sí.** Es quien marca cuándo el pedido pasa a `in_progress`/`ready`.
5. ✅ **Nomenclatura `view_own` / `view_all`** en los módulos con dueño (§1).

**No quedan decisiones abiertas**, ni en la matriz ni en el plan (`PLAN_FASE_2_RBAC.md` §6).
