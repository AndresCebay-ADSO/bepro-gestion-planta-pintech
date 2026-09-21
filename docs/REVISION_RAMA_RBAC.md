# Revisión de la rama `feature/rbac-permissions`

> **Fecha:** 2026-09-15 · **Base correcta de comparación:** `develop` (la rama sale de su punta; `main` lleva meses sin
> actualizarse e infla el diff de ~150 a ~690 archivos).
>
> Consolida tres revisiones y la verificación de cada hallazgo contra el código:
> - **UR** — `/ultrareview develop` (revisión en la nube, 5 hallazgos).
> - **CR** — revisión manual "Tech Lead" (20 hallazgos, hecha contra `main`).
> - **AG** — revisión de un agente (Antigravity/Gemini) sobre `feature/rbac-permissions-2`, la rama posterior
>   al merge de #141 (15 hallazgos).
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

## 3. Verificación de la tercera revisión (AG)

| ID | Hallazgo reportado | Veredicto | Severidad real | Nota de la verificación |
| --- | --- | --- | --- | --- |
| AG-01 | `scopeVisibleTo` trata `$user === null` como admin sin restricción (`Quotation`, `SalesOrder`, `PaintDevelopmentRequest`) | ✅ | Alta (defensiva) | Confirmé los 8 sitios donde se llama `visibleTo()`: los 3 controladores y `DashboardService`, todos detrás de `auth`+`verified`, y `DashboardController` valida `$user instanceof User` antes de nada. **Hoy no es explotable**, pero es una mina para el futuro (un job, un comando artisan, una ruta pública que se agregue después). **Corregido**: sin usuario, el scope ahora no devuelve ningún registro. |
| AG-02 | `sales_margin` no está oculto en la ficha de producto para roles sin `costs.view` | ✅ | **Alta** | Confirmado: `Product::sales_margin` existe (columna real, con cast) y no estaba en `PRODUCT_COST_ATTRIBUTES`. Con el precio de venta y el margen, cualquiera puede despejar el costo interno — viola el principio 1 de la matriz. **Corregido**: agregado a la constante. |
| AG-03 | Un 404 de asset dispara `HandleInertiaRequests` completo (consultas de bodega, alertas, permisos): riesgo de sobrecarga/DoS | ❌ | — | **Descartado con prueba empírica**, no solo lectura de código: escribí un test que pide una ruta 100 % inexistente y cuenta las consultas SQL ejecutadas → **0 consultas**. El middleware del grupo `web` (donde vive `HandleInertiaRequests`) solo corre para rutas que coincidieron con alguna definida; si el enrutador no encuentra ninguna, lanza `NotFoundHttpException` antes de correr cualquier middleware de grupo. El escenario descrito (bots escaneando `.png`, `favicon.ico`) no es alcanzable en esta app. |
| AG-04 | Sin manejo del código 419 (sesión/CSRF expirado) | ✅ | Baja | Real y previo a esta rama; se nota más ahora que sí se personalizan otros códigos en el mismo `respond()`. |
| AG-05 | `SalesOrderPolicy::edit()` en vez de `update()`, inconsistente con `QuotationPolicy`/`PaintDevelopmentRequestPolicy` | ✅ | Baja | Confirmé que los otros dos módulos sí usan `update()`. La ruta ya usa `can:edit,sales_order` explícito, así que **no hay bug activo hoy**; sí es un riesgo si alguien reutiliza el patrón `update` de los otros dos módulos. |
| AG-06 | Eager load de `client` sin usar en `SalesOrderController::show` | ✅ | Baja (rendimiento) | Confirmado: `buildOrderData()` solo usa las columnas desnormalizadas `client_business_name`/`client_nit`/`client_contact_name`/`client_phone`, nunca la relación `client`. |
| AG-07 | `User::hasActivity()` con 16 consultas seriadas | ✅ | Baja (rendimiento) | Conteo exacto confirmado: 15 `DB::table()->exists()` + 1 `Activity::where()->exists()`. |
| AG-08 | `Products/Show.tsx` con 1.463 líneas, componente monolítico | ✅ | Baja (deuda, frontend) | Dato exacto confirmado. **Preexistente**: el archivo ya tenía ese tamaño antes de esta rama. |
| AG-09 | Botón "Volver" de `ErrorPage.tsx` sin alternativa si no hay historial ni `homeHref` | ✅ | Baja | Confirmado en el código. |
| AG-10 | `buildSidebarGroups` sin `useMemo` | ✅ | Baja (rendimiento, frontend) | Confirmado: se recalcula en cada render de `AppSidebar`. |
| AG-11 | `warehouseContext` sin tipar en `global.d.ts` | ✅ | Baja | Mismo hallazgo que **CR-14**. |
| AG-12 | Mezcla de `.url` y objeto Wayfinder en `href` del menú | ✅ | Muy baja (estilo) | Cosmético. |
| AG-13 | `CostController::update` autoriza con un permiso, no con una ability de policy | ✅ | Baja | Mismo hallazgo que **CR-13**. |
| AG-14 | `verify_production_lock.php` es un script manual, no un test Pest | ✅ | Baja | Mismo hallazgo que **CR-10**. |
| AG-15 | Mezcla de `auth()->user()` y `$request->user()` | ✅ | Baja (estilo) | Mismo hallazgo que **CR-12**; confirmé 2 casos nuevos en `SalesOrderController`. |

**Verificación:** 897 tests OK (9 nuevos: `VisibleToScopeTest` y `ProductShowCostVisibilityTest`), Pint limpio. Para
AG-03, la verificación no fue solo lectura de código: se escribió y ejecutó un test que mide consultas SQL reales antes
de descartar el hallazgo.

## 3b. Verificación de la cuarta revisión (RV, 2026-09-16)

Revisión de un agente sobre `feature/rbac-permissions-2` frente a `develop`, hecha **después** del lote A4 (14 hallazgos,
3 marcados como críticos). Ninguno es una fuga de datos.

| ID | Hallazgo reportado | Veredicto | Origen | Severidad real | Nota de la verificación |
| --- | --- | --- | --- | --- | --- |
| RV-01 | `ProductController::edit` usa `makeHidden`, que "se puede saltar" | ⚠️ | lote A4 | Baja | **Que se pueda saltar no es cierto**: Inertia serializa con `toArray()`, que respeta los atributos ocultos. Sí es cierto que `makeHidden` es una lista negra: un atributo de costo nuevo se enviaría si nadie lo añade a la constante. Hoy solo Admin tiene `products.edit`, y también `costs.view`. **Corregido**: array explícito. CIF y umbral siguen (B18). |
| RV-02 | Literales de rol (`'operador'`, `'produccion'`, `'admin'`) en `ProductionOrderCostVisibilityTest`, "nuevos en esta rama" | ⚠️ | previo (`55fa71e`, jun-2026) | Baja | **No son nuevos**: ya estaban en `develop`. Los tests añadidos en A3 y A4 usan `SystemRole`. Parte de AU-07 (paso 11). |
| RV-03 | `'dashboard.view'` como texto en `ErrorPage.tsx` sin el tipo `Permission` | ❌ | — | — | `auth.user.permissions` está tipado como `Permission[]`: `includes('dashboard.view')` ya lo comprueba `tsc`, y un permiso renombrado haría fallar la compilación. |
| RV-04 | Tres estrategias de filtrado de costos (arrays explícitos, spread condicional, `makeHidden`) | ✅ | mixto | Baja (mantenibilidad) | `edit` pasa a array explícito. Queda `ProductController::show` (presentaciones, documentos y fórmulas anidados): B22. |
| RV-05 | `UserController::index` carga `roles` con todas sus columnas | ✅ | rama (2.5) | Muy baja | Nada se envía de más (`through()` descarta el modelo). B23. |
| RV-06 | Tipos de `Prices/Index.tsx`: campos condicionales marcados como obligatorios | ✅ | lote A4 | Baja | El razonamiento de la revisión falla (Comercial no recibe esos campos), pero la conclusión es correcta: A4 solo marcó `sales_margin`. **Corregido**: `current_cost`, `cif_percentage` y `current_price` (producto y presentación) pasan a opcionales. |
| RV-07 | El 419 no tiene página propia | ✅ | previo | Baja | Mismo hallazgo que **AG-04** (B8). |
| RV-08 | "Volver" de `ErrorPage.tsx` sin alternativa | ✅ | rama | Baja | Mismo hallazgo que **AG-09** (B12). |
| RV-09 | `const` con `Permission::X->value` no sería portable a PHP 8.1 | ❌ | — | — | `composer.json` exige PHP `^8.3`. |
| RV-10 | Mezcla de `auth()->user()` y `$request->user()` | ✅ | mixto | Baja (estilo) | Mismo hallazgo que **CR-12** (B2). Los cambios de A4 usan `$request->user()`. |
| RV-11 | `UserRole` (TS) con nombres en español | ✅ | mixto | Baja | Mismo hallazgo que **AU-08** (paso 11). |
| RV-12 | Formato de Prettier mezclado con cambios de lógica | ✅ | lote A4 | Baja (revisión) | Los 4 archivos solo tienen formato: se commitean aparte (`style:`). |
| RV-13 | `beforeEach` de 77 líneas sin factories en `ProductionOrderCostVisibilityTest` | ⚠️ | previo (`55fa71e`) | Muy baja | Las factories existen. Deuda de tests: B24. |
| RV-14 | Ruta registrada dentro de `ErrorPagesTest` que "persiste" y podría colisionar en paralelo | ❌ | — | — | Cada test arranca una aplicación nueva, así que la ruta no persiste. Pest no se ejecuta en paralelo en este proyecto (`--parallel` solo está en Pint). |

**Verificación:** 920 tests OK y 5 omitidos; Pint, ESLint, Prettier y TypeScript limpios. El test del formulario de
edición comprueba ahora que el margen no se envía a nadie (el formulario no lo usa).

## 3c. Verificación de CodeRabbit (PR #143, 2026-09-16)

| ID | Hallazgo reportado | Veredicto | Origen | Severidad real | Nota de la verificación |
| --- | --- | --- | --- | --- | --- |
| CB-01 | `ProductController::edit` envía `cif_percentage` y `price_threshold` sin `costs.view` (marcado 🟠 *Major*, riesgo de merge *High*) | ✅ latente | lote A4 (era B18) | Baja hoy | Cierto: la policy solo exige `products.edit`, la validación exigía ambos campos y el formulario los enviaba en `'0'` si faltaban. **Exagera el riesgo:** solo Admin tiene `products.edit`, y también `costs.view`. **Su propuesta mezcla permisos:** pide que modificar dependa de `costs.view`, pero modificar ya lo controla `costs.update` (403 en `update()`). **Corregido** (ver lote A5). |
| CB-02 | Usar factories en `RawMaterialCostVisibilityTest` | ⚠️ | lote A4 | Muy baja (estilo) | Las factories existen, pero `CLAUDE.md` no lo exige y 38 archivos de test usan `::create()`. Su ejemplo (`WarehouseFactory::factory()`) no existe: sería `Warehouse::factory()`. Se suma a B24. |
| CB-03 | *Docstring coverage* 30 % < 80 % | ❌ | — | — | Umbral por defecto de CodeRabbit, no es regla del proyecto. Aviso, no bloquea. |

### Lote A5 — CIF y umbral en el formulario de producto (B18)

> **✅ Aplicado (2026-09-16).**

| # | Tarea | Hallazgos | Criterio de aceptación |
| --- | --- | --- | --- |
| A5.1 | `ProductController::edit` envía CIF y umbral solo con `costs.view`, y expone `can.viewCosts` | CB-01, PC-05 | `ProductShowCostVisibilityTest`: un editor sin `costs.view` no recibe CIF, umbral, costo ni precio interno; Admin sí. |
| A5.2 | `UpdateProductRequest`: CIF y umbral pasan a `sometimes`; si faltan, se conservan los guardados. Al crear siguen obligatorios | CB-01 | `ProductEditTest`: el editor guarda sin enviarlos y los valores no cambian; crear sin ellos da error de validación. |
| A5.3 | `Products/Edit.tsx`: sin `can.viewCosts` no muestra la sección de precios y no envía CIF ni umbral | CB-01 | `tsc` y ESLint limpios. |

La regla queda: **ver** CIF y umbral exige `costs.view`; **cambiarlos** exige `costs.update` (sin cambios).
**Cambios de acceso:** ninguno para los roles del sistema.

---

## 4. Plan de trabajo

### Lote A — Antes del merge a `develop` (en esta rama)

> **✅ Aplicado (2026-09-15):** A1, A2, A3 y A4.

| # | Tarea | Hallazgos | Esfuerzo | Criterio de aceptación |
| --- | --- | --- | --- | --- |
| A1 | Transformar la ficha y el listado de pedidos a arrays explícitos: producto (`id`, `code`, `name`), variante (`id`, `name`, `presentation_label`), creador (`name`) y cliente del listado (`id`, `business_name`) | CR-01 | 1 h | Test: Comercial abre su pedido y el payload no contiene `current_cost`, `cif_percentage`, `price_threshold`, `sales_margin` ni el correo del creador. |
| A2 | Autorizar con la policy en la ruta, además del permiso, todas las acciones sobre registros con dueño: edición, cambio de estado, conversión, envío y PDF de cotizaciones, pedidos y desarrollo de pinturas (12 rutas) | CR-03 | 30 min | El test del mapa de rutas sigue en verde y un comercial recibe 403 en la ruta de edición de una cotización ajena. |
| A3 | Reformatear `Permission::defaultRoles()` con un permiso por línea | CR-07 | 20 min | Mismo resultado en `PermissionRegistryTest`; ninguna línea supera 120 caracteres. |
| A4 | Documentar la decisión sobre CR-02: se mantiene la lista de permisos compartida, **sin caché** | CR-02 | 10 min | Nota en `PLAN_FASE_2_RBAC.md` (2.5). |

Después de A1–A4: repetir `/ultrareview develop`, hacer la prueba manual por rol y el merge a `develop`.

### Lote A2 — Fuga de margen y fail-safe de visibilidad (en `feature/rbac-permissions-2`)

> **✅ Aplicado (2026-09-15).**

| # | Tarea | Hallazgos | Criterio de aceptación |
| --- | --- | --- | --- |
| A2.1 | `sales_margin` se oculta junto al resto de costos en la ficha de producto sin `costs.view` | AG-02 | Test: Comercial abre la ficha de un producto y el payload no contiene `sales_margin` (ni el resto de `PRODUCT_COST_ATTRIBUTES`); Admin sí lo recibe. |
| A2.2 | `scopeVisibleTo` de `Quotation`/`SalesOrder`/`PaintDevelopmentRequest`: sin usuario, no devuelve ningún registro | AG-01 | Test: `Modelo::visibleTo(null)` devuelve 0 registros con datos de por medio en la tabla; `view_own` solo ve lo propio; `view_all` ve todo. |

### Lote A3 — Auditoría de la Fase 2 completa (en `feature/rbac-permissions-2`)

Revisión propia de todo lo hecho desde el inicio de la fase (`4adccdb`..`bc41bb5`), verificada contra el código.

| ID | Hallazgo | Veredicto | Origen | Severidad | Nota |
| --- | --- | --- | --- | --- | --- |
| AU-01 | La ficha de la orden envía `remnant.cost_per_gallon` y `remnant_consumptions.*.consumed_cost` sin `costs.view`, y la tarjeta de saldos consumidos muestra la columna *Costo* | ✅ | previo (el lote 1 corrigió solo la página de saldos) | **Alta** | Viola el principio 1 y la decisión C5. `BuildProductionOrderShowDataAction` también alimenta el PDF y el Excel. |
| AU-02 | La ficha de la orden envía `product.cif_percentage` sin `costs.view` | ✅ | previo | Media | Es un atributo de costo (`ProductController::PRODUCT_COST_ATTRIBUTES`). La pantalla solo lo usaba para costos ocultos, pero llegaba en el payload. |
| AU-03 | El seeder de permisos no figura en ningún procedimiento de despliegue | ✅ | rama (2.9) | **Alta** (al desplegar) | Ni el `README`, ni `compose.prod.yaml`, ni el entrypoint, ni CI. Sin él, una base nueva deja a todos en 403. |
| AU-04 | `SalesOrderSeeder` y `WarehouseUserSeeder` escriben los nombres de rol a mano | ✅ | previo | Media | Incumple la regla del paso 11 y rompería el seeder al renombrar los roles. |
| AU-05 | `DashboardService::COMMERCIAL_PERMISSIONS` con strings en vez del enum | ✅ | rama | Baja | |
| AU-06 | Permisos del frontend como strings sin tipo (menú, dashboard, página de error, campana) | ✅ | rama | Media (escalabilidad) | Un permiso renombrado en el backend ocultaría un menú sin ningún error. |
| AU-07 | 245 literales de rol en 38 archivos de test | ✅ | mixto | Media (paso 11) | Reestima el paso 11 (ver `PLAN_FASE_2_RBAC.md`). |
| AU-08 | `UserRole` (TS) con nombres en español y `role_names` compartido sin consumidores | ✅ | mixto | Baja | Se resuelve en el paso 11. |
| AU-09 | `DashboardService` usa `Carbon::today('America/Bogota')` en vez de `config('app.plant_timezone')` | ✅ | rama | Baja | Invariante 5 de `CLAUDE.md`. |
| AU-10 | `rbac:audit` (2.9) y el test de matriz `[rol, ruta, código]` (2.8) nunca se hicieron | ⚠️ | rama | Baja | `rbac:audit` se descarta (ver 2.9). El test de matriz tiene huecos conocidos (B17). |
| AU-11 | `ProductPolicy::restore` y `forceDelete` sin ruta ni uso | ✅ | previo | Muy baja | Código muerto. |

> **✅ Aplicado (2026-09-15):** AU-01 a AU-06 y el descarte de `rbac:audit`.

| # | Tarea | Hallazgos | Criterio de aceptación |
| --- | --- | --- | --- |
| A3.1 | Costo de saldos y CIF % de la orden solo con `costs.view` (payload, PDF/Excel y columna *Costo* de la tarjeta de saldos consumidos) | AU-01, AU-02 | Test: Producción y Operador no reciben `cif_percentage`, `cost_per_gallon` ni `consumed_cost`; Admin sí; la exportación sin costos tampoco los incluye. |
| A3.2 | Seeders y `DashboardService` con `SystemRole` / `Permission` | AU-04, AU-05 | `grep` de nombres de rol en `app/` y `database/` solo encuentra `SystemRole`. |
| A3.3 | Tipo `Permission` en `resources/js/types/permissions.ts`, aplicado a `auth.user.permissions`, `NavItem.allowedPermissions` y los accesos rápidos del dashboard | AU-06 | `tsc` rechaza un permiso inexistente; `PermissionRegistryTest` falla si el tipo y el enum difieren. |
| A3.4 | Procedimiento de despliegue en el `README` y roles del sistema actualizados | AU-03 | Sección *Despliegue a producción* con el seeder obligatorio en cada despliegue. |

**Cambio de acceso a comunicar:** Producción y Operador dejan de ver la columna *Costo* de los saldos consumidos en la
ficha de la orden de producción.

**Verificación:** 907 tests OK (5 nuevos: 4 en `ProductionOrderCostVisibilityTest`, 1 en `PermissionRegistryTest`), Pint,
ESLint, Prettier y TypeScript limpios.

### Lote A4 — Auditoría sistemática de precios y costos (en `feature/rbac-permissions-2`)

La misma fuga apareció cuatro veces en módulos distintos (`sales_margin` en productos, CIF % y costo de saldos en la
orden, precios en la ficha de materia prima), siempre corregida de forma reactiva. Esta vez se recorrió **toda** respuesta
que devuelve datos de `RawMaterial`, `Product`, `ProductVariant` o `Formula`: los 52 `Inertia::render` de la aplicación
(más los 7 de autenticación de Fortify, que no tocan estos modelos), las 2 respuestas JSON, los PDF (orden, cotización, desarrollo de pinturas, certificado de calidad) y el Excel de la orden, más los
servicios que alimentan esas respuestas (`PriceListService`, `QuotationService`, `FinishedInventoryQueryService`,
`DashboardService`, `AlertService` y los props compartidos de `HandleInertiaRequests`).

**Criterio (matriz, principios 1 y 2):** en materias primas `current_price`, `previous_price` y el `unit_price` de los
lotes son costo. En productos y presentaciones son costo `current_cost`, `cif_percentage`, `price_threshold`,
`sales_margin` y **también `current_price`**: verificado en el código, es el *precio interno* (costo × (1 + CIF %)), así
lo rotula la interfaz y así lo trata `PriceListService`. El precio que paga el cliente es `sales_price` = precio interno
÷ (1 − margen), que Comercial ya ve en Lista de precios. **Decisión del usuario (2026-09-15):** `current_price` de
productos y presentaciones es costo y queda bajo `costs.view`.

**Veredicto:** ✅ fuga real con los roles del sistema · ⚠️ latente (hoy solo la reciben roles que ya tienen `costs.view`;
afectaría a un rol creado desde la pantalla de roles de la 2.4) · ❌ no se sostiene.

| ID | Hallazgo | Veredicto | Origen | Severidad | Nota |
| --- | --- | --- | --- | --- | --- |
| PC-01 | `RawMaterialController::show` envía el modelo completo: `current_price`, `previous_price` y el `unit_price` de cada lote, sin `costs.view` | ✅ | previo (el lote 2 corrigió solo `index`) | **Alta** | Producción lo recibía y lo veía en la ficha: *Precio actual*, *Precio anterior* y la columna *Precio unitario* de los lotes. |
| PC-02 | `ProductionOrderController::index` envía `product`, `formula` y `warehouse` completos | ✅ | previo | **Alta** | Producción y Operador recibían `current_cost`, `current_price`, `cif_percentage`, `price_threshold` y `sales_margin` de cada producto. La pantalla no los pintaba. |
| PC-03 | `current_price` de producto y presentación es el precio interno, pero la ficha de producto se lo muestra a Comercial y Producción, y el listado de productos lo envía | ✅ | previo | **Alta** | Con él se conoce el costo con CIF de cada producto. Resuelto según la decisión del usuario. |
| PC-04 | `PriceListService` envía `sales_margin` a Comercial | ✅ | previo | Media | Con `sales_price` y el margen se despeja el precio interno. La pantalla solo lo pintaba con `view_costs`. **Cierra V-01.** |
| PC-05 | `ProductController::edit` envía el modelo completo (costo, precio interno y margen) | ⚠️ | previo | Baja hoy | Solo Admin tiene `products.edit`, y también tiene `costs.view`. Se ocultan los montos. (Tras RV-01: array explícito con los campos del formulario; el margen ya no se envía a nadie.) CIF y umbral siguen en el payload: el formulario los reenvía siempre y `StoreProductRequest` los exige, así que ocultarlos haría que se enviaran en 0 y el guardado diera 403 (ver B18). |
| PC-06 | `RawMaterialController::edit` envía el modelo completo | ⚠️ | previo | Baja hoy | Solo Admin tiene `raw_materials.edit`. El formulario no usa precios: pasa a array explícito. |
| PC-07 | Lotes con `unit_price` en el formulario de movimientos MP (`InventoryMovementController::index`, prop `batches`) | ⚠️ | previo | Baja | Solo se envían con `inventory_movements.create` (Admin). El formulario de entrada usa el precio del lote, así que ocultarlo cambia el flujo: queda como B19. |
| PC-08 | La auditoría muestra `properties` con `current_price`, `unit_price`, `cost_price`, `price` y `cost_at_time` (`AuditLogController` y las últimas entradas de `UserController`) | ⚠️ | previo | Baja | Solo SuperAdmin tiene `audit_logs.view`. Queda como B20, a resolver con la 2.4. |
| PC-09 | `ProductionOrderIngredientsSheet` y `ProductionOrderGeneralSheet` son código muerto, y la de ingredientes lee `unit_cost`/`total_cost` del modelo sin `includeCosts` | ⚠️ | previo | Muy baja | Nadie las usa: el Excel sale de `ProductionOrderExport` con la vista Blade, que sí respeta los costos. Si alguien las conectara, filtrarían costos. Queda como B21. |
| PC-10 | V-01: `QuotationService::catalogProducts()` envía `sales_margin` a las pantallas de cotización | ❌ | — | — | Hoy selecciona el margen para calcular `sales_price` en servidor, pero **no lo envía**: el payload lleva `id`, `code`, `name`, `description` y las presentaciones con `sales_price`. |

**Revisado sin hallazgos:** materias primas `index`/`create` · productos `create` · fórmulas `index`/`create`/`show`/`edit`
(solo el código de la MP y código y nombre del producto) · órdenes de producción `create`, `show`, PDF y Excel (lote A3),
`previewCosts` (policy con `costs.view`) y saldos disponibles en JSON (sin costo) · saldos · movimientos MP y PT
(`cost_price` oculto y relaciones con columnas explícitas) · inventario PT · bodegas `show` · costos (exige
`costs.view`) · cotizaciones (solo precios de venta) · pedidos (lote A1) · códigos QR y landing pública · dashboard y
alertas compartidas · certificado de calidad · desarrollo de pinturas.

> **✅ Aplicado (2026-09-15):** PC-01 a PC-06.

| # | Tarea | Hallazgos | Criterio de aceptación |
| --- | --- | --- | --- |
| A4.1 | Ficha y formulario de edición de materia prima con arrays explícitos; precios y `unit_price` de los lotes solo con `costs.view`, y la ficha oculta esos datos sin el permiso | PC-01, PC-06 | `RawMaterialCostVisibilityTest`: Producción no recibe precios y sí el código y los lotes; Admin sí los recibe; el formulario de edición no envía precios, tampoco a un rol personalizado sin `costs.view`. |
| A4.2 | Listado de órdenes con array explícito (producto: `id`, `code`, `name`; fórmula: `id`, `version`; bodega: `id`, `name`) | PC-02 | `ProductionOrderCostVisibilityTest`: Producción, Operador y Admin reciben la identidad del producto y ninguno de sus costos. |
| A4.3 | `current_price` de producto y presentación bajo `costs.view` en listado y ficha de producto (payload y pantalla); montos de costo ocultos en el formulario de edición sin `costs.view` | PC-03, PC-05 | `ProductShowCostVisibilityTest`: Comercial y Producción no reciben el precio interno ni en el listado ni en la ficha, y siguen viendo nombre y presentaciones; Admin sí lo recibe; un editor sin `costs.view` no recibe costo, precio interno ni margen en el formulario. |
| A4.4 | `sales_margin` de la lista de precios solo con `costs.view` | PC-04 | `PriceListControllerTest`: Comercial recibe `sales_price` y no `sales_margin`; Admin sí. |

Los tests negativos se ejecutaron también contra el código anterior a la corrección: los 12 casos fallaron, lo que
confirma que cada uno detecta su fuga.

**Cambios de acceso a comunicar:**
- **Producción** deja de ver en la ficha de materia prima el *Precio actual*, el *Precio anterior* y el *Precio unitario*
  de los lotes. El listado ya se los ocultaba desde el lote 2.
- **Comercial y Producción** dejan de ver el *Precio Interno* en la ficha de producto, tanto del producto como de cada
  presentación. Comercial sigue viendo el precio de venta en *Lista de precios*.
- Sin cambio visible: el listado de órdenes y el margen de la lista de precios (las pantallas ya no los mostraban; solo
  viajaban en el payload).

**Verificación:** 920 tests OK y 5 omitidos, ninguno de este lote (13 casos nuevos: 4 en `RawMaterialCostVisibilityTest`,
6 más en `ProductShowCostVisibilityTest` y 3 en `ProductionOrderCostVisibilityTest`; `PriceListControllerTest` amplía
un caso). Pint, ESLint, Prettier y TypeScript limpios. Prettier también corrigió el formato de 4 archivos que ya
fallaban en `a30b896` sin que este lote los tocara (`entry-movement-form.tsx`, `user-identity-fields.tsx`,
`date-time-helpers.ts`, `Products/Create.tsx`); son cambios de formato, sin lógica.

### Lote A6 — Revisión de la 2.4 (en `feature/rbac-roles`, 2026-09-17)

Revisión propia de la pantalla de roles (commit `7cbbe10`), verificada contra el código y con la suite completa.

| ID | Hallazgo | Veredicto | Origen | Severidad | Nota |
| --- | --- | --- | --- | --- | --- |
| RR-01 | Las alertas de variación de precio guardan en el mensaje el precio anterior y el nuevo de la materia prima, y el mensaje lo ve todo el que tiene `alerts.view` (Producción, sin `costs.view`) en la página de alertas, el dashboard y la notificación emergente | ✅ | previo (se escapó en el lote A4, que revisó las relaciones de la alerta pero no el texto) | **Alta** | Además, `new_alerts` se entregaba sin comprobar `alerts.view`. |
| RR-02 | Un rol personalizado puede llamarse `production`, `operator` o `commercial`, los nombres en inglés del paso 11 | ✅ | rama (2.4) | Media | El renombrado fallaría o el seeder tomaría ese rol como del sistema y le reasignaría permisos. |
| RR-03 | El nombre de un rol personalizado admite `\|`, que Spatie usa como separador en `hasRole()` y en el scope `role()` | ✅ | rama (2.4) | Media | |
| RR-04 | Los roles personalizados solo se validan al guardarlos: si el código cambia las reglas (permisos reservados, dependencias), los existentes quedan inválidos sin aviso | ✅ | rama (2.4) | Media | |
| RR-05 | Un rol personalizado sin `dashboard.view` deja a sus usuarios en un 403 al iniciar sesión | ✅ | rama (2.4) | Media | Venía marcado por defecto pero se podía desmarcar. |
| RR-06 | Faltan tests: editar con dependencias incompletas, 403 de `show`/`edit`/`update`/`destroy` fuera de SuperAdmin y conservar el rol actual al editar un usuario | ✅ | rama (2.4) | Media | |
| RR-07 | `RoleController::create` y `show` no llaman a `authorize` (sí `edit` y `destroy`) | ✅ | rama (2.4) | Baja | La ruta ya lo exige. |
| RR-08 | Guard `'web'` escrito a mano en `RoleController`, las Actions, `AssignableRoleService` y `UserController::lockRole` | ✅ | rama (2.4) | Baja | Lote B. |
| RR-09 | La 2.4 documenta B20 como resuelto por la reserva **y** por la dependencia `audit_logs.view` → `costs.view`, pero esa dependencia nunca actúa | ⚠️ | rama (2.4) | Muy baja | Solo documentación. |
| RR-10 | Tras aplicar una plantilla al crear un rol no se puede volver a "Empezar desde cero" | ✅ | rama (2.4) | Baja | |
| RR-11 | `String(role.id)` innecesario en las rutas de Wayfinder | ✅ | rama (2.4) | Muy baja | |
| RR-12 | `filters: Record<string, …>` en `Roles/Index.tsx`, menos tipado que otros listados | ✅ | rama (2.4) | Muy baja | |
| RR-13 | La búsqueda no encuentra los roles del sistema por su etiqueta ("Administrador") | ✅ | rama (2.4) | Baja | Estaba documentado como límite; arreglo barato: buscar también por etiqueta. |
| RR-14 | Las alertas de solicitudes de desarrollo de pinturas (con el cliente en el mensaje) las ve Producción con `alerts.view`, aunque la matriz le retiró ese módulo | ✅ | previo | Media | Hallado al rediseñar RR-01: el problema de fondo es que ningún tipo de alerta comprobaba el permiso de su módulo. |
| RR-15 | Al editar un usuario cuyo rol no puede asignar quien edita, el selector de rol queda vacío | ⚠️ | rama (2.4) | Baja hoy | Latente: con las reglas actuales Admin puede asignar todos los roles personalizados y no edita SuperAdmin. Se vuelve real si cambian las reglas (RR-04). |
| RR-16 | Borrar un rol desde el listado no conserva la posición (`preserveScroll`) | ✅ | rama (2.4) | Muy baja | |

**Decisiones del usuario (2026-09-17):** **cada tipo de alerta exige el permiso de su módulo** además de `alerts.view`
(variación de precio → `costs.view`; desarrollo de pinturas → `paint_development_requests.view_all`; stock bajo y
vencimientos → `raw_materials.view`). Sustituye a una primera solución, descartada sin commitear, que quitaba los montos
del mensaje con una columna `data` y migraciones: era más código y solo resolvía un tipo; `dashboard.view` es obligatorio en todo rol personalizado; si un rol personalizado queda
inválido, el despliegue **solo avisa** (nunca cambia permisos solo).

| # | Tarea | Hallazgos | Estado |
| --- | --- | --- | --- |
| A6.1 | Visibilidad de alertas por tipo | RR-01, RR-14 | ✅ |
| A6.2 | Nombres reservados y caracteres permitidos en roles personalizados | RR-02, RR-03 | ✅ |
| A6.3 | `dashboard.view` obligatorio en roles personalizados | RR-05 | ✅ |
| A6.4 | Comando `roles:audit`, ejecutado por el seeder | RR-04 | ✅ |
| A6.5 | Tests que faltaban y rol actual en el selector de usuarios | RR-06, RR-15 | ✅ |
| A6.6 | Detalles de la pantalla de roles | RR-07, RR-09 a RR-13, RR-16 | ✅ (RR-08 queda como B25) |

**A6.1 — aplicado:**
- `AlertType::requiredPermission()` (un `match` sin `default`: un tipo nuevo obliga a decidir quién lo ve).
- `Alert::scopeVisibleTo()` y `Alert::visibleTypesFor()`, con el mismo patrón que los scopes de cotizaciones, pedidos
  y desarrollo de pinturas: sin usuario no devuelve nada.
- Se aplica en todas las salidas: listado de alertas y sus opciones de tipo, `AlertPolicy::view` (y `resolve`),
  contador de la campana, desglose y alertas recientes del dashboard, alertas recientes compartidas y notificación de
  alerta nueva (`new_alerts`, que además ya no se entrega sin `alerts.view`).
- Los mensajes no cambian: quien ve la alerta de precio ve los montos. Sin migraciones.
- Tests: `AlertVisibilityTest` (11 casos; 9 fallaban antes de la corrección).

**Cambio de acceso a comunicar:** Producción deja de ver las alertas de variación de precio (incluían los precios de la
materia prima) y las de solicitudes de desarrollo de pinturas. Sigue viendo las de stock bajo y vencimientos.

**A6.2, A6.3, RR-15 y parte de A6.6 — aplicados:**
- **Nombres (A6.2):** `SystemRole::reservedNames()` reúne nombre y etiqueta de los roles del sistema y los nombres en
  inglés del paso 11 (`production`, `operator`, `commercial`). El nombre solo admite letras (con tildes), números,
  espacios, guiones y guion bajo, así que `|` queda fuera.
- **`dashboard.view` obligatorio (A6.3):** `PermissionCatalogService::requiredForCustomRoles()` es la fuente única. La
  validación lo exige al crear y al editar; el catálogo marca el permiso como `required` y el formulario lo muestra
  marcado y bloqueado. Al editar un rol que no lo tuviera, el formulario lo añade.
- **Rol actual en el formulario de usuario (RR-15):** `UserController::edit` añade el rol actual del usuario a las
  opciones cuando quien edita no puede asignarlo, así que el selector nunca queda vacío. En el alta no aparece.
- **Pantalla de roles (RR-10, RR-13, RR-16):** "Empezar desde cero" en el selector de plantillas (y tras un cambio
  manual el selector deja de mostrar la plantilla); la búsqueda encuentra los roles del sistema por su etiqueta; borrar
  conserva la posición.
- Tests: 11 casos nuevos en `RoleManagementTest` (todos fallaban antes de la corrección) y 3 ajustados para que el
  error de dependencias no se confunda con el del permiso obligatorio.
- **Sin cambios de acceso** para los roles del sistema.

**A6.4, A6.5 y el resto de A6.6 — aplicados:**
- **`roles:audit` (A6.4):** lista los roles personalizados con permisos reservados, obligatorios faltantes o dependencias
  incompletas, y termina con error mientras quede alguno. `RolePermissionSeeder` avisa en la salida del despliegue sin
  fallar ni tocar permisos (decisión del usuario). Las reglas viven en un único sitio,
  `PermissionCatalogService::customRoleViolations()`, que usan también la validación de la pantalla y el seeder.
  Documentado en el `README` (*Despliegue a producción*).
- **Tests de RR-06 (A6.5):** 403 fuera de SuperAdmin en `show`, `edit`, `update` y `destroy`; editar un rol con
  dependencias incompletas; conservar el nombre al editar; Admin no puede cambiar el rol de un usuario por uno que no
  puede asignar, pero sí conservar el actual. Estos casos ya funcionaban: faltaba cubrirlos.
- **A6.6:** `authorize` también en `RoleController::create` y `show` (RR-07); `String(role.id)` retirado en las rutas de
  Wayfinder (RR-11); tipo `RoleFilters` (RR-12); B20 descrito como resuelto por la reserva del permiso (RR-09).
- Tests: `RoleAuditTest` (4 casos, fallaban antes de crear el comando) y 7 casos nuevos en `RoleManagementTest`.
- **Verificación del lote A6 completo:** 997 tests OK; Pint, ESLint, Prettier y TypeScript limpios.
- **Sin cambios de acceso** para los roles del sistema.

**Verificación de la revisión externa de la 2.4 (2026-09-17):** sus 15 puntos se verificaron contra el código.
Coinciden con RR-02, RR-03, RR-05, RR-07, RR-08, RR-10 a RR-13; aportan RR-15 y RR-16. Además:
- `formatPrice` con negativos y `json` en vez de `jsonb`: reales, pero afectaban a la solución descartada.
- `session()` "lanza excepción en consola": **no se sostiene** (probado con tinker; `alerts:check-expiry` ya corre a
  diario por ese camino).
- Doble consulta de alertas recientes en el dashboard: real y previa → B26.
- Accesibilidad del componente de permisos (módulo anunciado dos veces, casillas deshabilitadas en solo lectura) → B27.
- Lógica de `UserController` en el controlador: previa, fuera del alcance (Lote C).

### Lote B — Limpieza técnica (rama nueva `chore/…`, tras el merge)

| # | Tarea | Hallazgos | Esfuerzo |
| --- | --- | --- | --- |
| B1 | `declare(strict_types=1)` en los 8 archivos señalados (luego progresivamente en los 67) | CR-11 | 30 min + suite |
| ~~B2~~ | ✅ Hecho (2026-09-21): las 20 acciones usan `$request->user()` (se inyecta `Request` donde faltaba). Queda `auth()` solo en el helper privado `UserController::assignableRoles`. Estandarizar `$request->user()` en controladores que reciben `Request` | CR-12, CR-04 | 45 min |
| ~~B3~~ | ✅ Hecho (2026-09-21): `UserController` usa `success`; se retira la clave compartida `flash.message`, que nadie más usaba. Unificar flash en `success` (`UserController`) | CR-16 | 15 min |
| ~~B4~~ | ✅ Hecho (2026-09-21): `global.d.ts` tipa todas las props compartidas (`warehouseContext`, alertas, flash) con tipos en `types/shared.ts`; se quitan 11 genéricos locales de `usePage` y el `as any` de `flash-messages`. Tipar `warehouseContext` (y los demás props compartidos) en `global.d.ts` | CR-14 | 20 min |
| ~~B5~~ | ✅ Hecho (2026-09-21): `ProductPolicy::updateCost`; `UpdateCostRequest` autoriza con la policy, valida y calcula el margen con `DecimalCalculator` (antes, con floats en el controlador). `CostController`: ability de policy (`ProductPolicy::updateCost`) y validación del margen en `UpdateCostRequest` | CR-13 | 45 min |
| ~~B6~~ | ✅ Hecho (2026-09-21): `SalesOrderStatus::Pending` directo; el PHPDoc de `clientOptions()` ya se corrigió en la política de eliminación. Detalles: enum directo en `store`, PHPDoc de `clientOptions()` | CR-18, CR-17 | 10 min |
| ~~B7~~ | ✅ Hecho (2026-09-21): eliminado; usaba valores que ya no existen y la regla ya la cubren dos tests de `ProductionOrderValidationTest`. Convertir `verify_production_lock.php` en test Pest o eliminarlo si ya está cubierto | CR-10, AG-14 | 30 min |
| ~~B8~~ | ✅ Hecho (2026-09-21): el 419 muestra la página de error "Tu sesión expiró" (un `back()` perdía el aviso al redirigir al login). Manejar el código 419 (sesión/CSRF expirado) en el `respond()` de `bootstrap/app.php`, igual que el 429 | AG-04 | 20 min |
| ~~B9~~ | ✅ Hecho (2026-09-21): `SalesOrderPolicy::edit()` renombrado a `update()` en la policy, el request, la ruta y el controlador (sin alias). `SalesOrderPolicy`: añadir `update()` como alias de `edit()` para que el patrón sea intercambiable con `QuotationPolicy`/`PaintDevelopmentRequestPolicy` | AG-05 | 15 min |
| ~~B10~~ | ✅ Hecho (2026-09-21): `show` ya no carga `client` (usa las columnas desnormalizadas). `SalesOrderController::show`: quitar `client` del `load()`, no se usa (se leen las columnas desnormalizadas) | AG-06 | 10 min |
| ~~B11~~ | ✅ Resuelto con la política de eliminación: las claves foráneas `RESTRICT` protegen el historial y `hasActivity()` solo consulta la auditoría. `User::hasActivity()`: condensar las 16 consultas en una sola | AG-07 | 45 min |
| ~~B12~~ | ✅ Hecho (2026-09-21): sin historial, "Volver" lleva al inicio o al login. Botón "Volver" de `ErrorPage.tsx`: si no hay historial, navegar a `homeHref` (o `/` si tampoco hay) | AG-09 | 15 min |
| ~~B13~~ | ❌ Obsoleto: el proyecto usa React Compiler (`babel-plugin-react-compiler`), que ya memoriza el componente; el `useMemo` se retiró. `useMemo` en `buildSidebarGroups` dentro de `AppSidebar` | AG-10 | 15 min |
| ~~B14~~ | ✅ Hecho (2026-09-21): menú lateral, cabecera y Configuración con `.url`. Queda `/reports` (elemento deshabilitado sin ruta, decisión de producto). Uniformar `href` del menú: siempre objeto Wayfinder o siempre `.url` | AG-12 | 15 min |
| ~~B15~~ | ✅ Hecho (2026-09-21): `DashboardService` (3) y `SaveProductionOrderOperationalDataAction` leen `config('app.plant_timezone')`. `DashboardService`: zona horaria de planta desde `config('app.plant_timezone')` | AU-09 | 10 min |
| ~~B16~~ | ✅ Resuelto con la política de eliminación (sin soft deletes): retirados `restore` y `forceDelete` de las policies. Retirar `ProductPolicy::restore` y `forceDelete` | AU-11 | 10 min |
| B17 | Test de acceso por rol: dataset `[rol, ruta, código]` sobre las rutas principales, y verificar la ability exacta en las rutas `can:viewAny` / `can:view` | AU-10 | 3 h |
| ~~B18~~ | ✅ Aplicado en A5. Formulario de edición de producto: enviar CIF y umbral solo con `can.managePrices` y hacerlos `sometimes` en `UpdateProductRequest`; después, ocultarlos también sin `costs.view` | PC-05 | 45 min |
| ~~B19~~ | ✅ Resuelto en la 2.4: `inventory_movements.create` exige `costs.view` (dependencia validada al guardar un rol). Formulario de movimientos MP: decidir qué ve del precio del lote un rol con `inventory_movements.create` sin `costs.view` | PC-07 | 30 min |
| ~~B20~~ | ✅ Resuelto en la 2.4: `audit_logs.view` es un permiso reservado a SuperAdmin, así que ningún rol personalizado lo recibe (la dependencia de `costs.view` queda como documentación: no llega a actuar). Auditoría: filtrar de `properties` los atributos de costo sin `costs.view`, o impedir que un rol reciba `audit_logs.view` sin `costs.view` | PC-08 | 1 h |
| ~~B21~~ | ✅ Hecho (2026-09-21): eliminadas las dos hojas y la carpeta `Sheets`. Eliminar `ProductionOrderIngredientsSheet` y `ProductionOrderGeneralSheet` (sin uso) | PC-09 | 10 min |
| ~~B22~~ | ✅ Hecho (2026-09-21): arrays explícitos en producto, presentaciones, documentos y fórmulas; test que fija la lista de campos. `ProductController::show` con arrays explícitos en lugar de `makeHidden` (producto, presentaciones, documentos y fórmulas) | RV-04 | 1 h |
| ~~B23~~ | ❌ Obsoleto: desde el paso 11 el listado carga `roles.permissions` y `permissions` a propósito (la policy compara permisos por fila); reducirlo reintroduciría N+1. `UserController::index`: `with('roles:id,name')` | RV-05 | 5 min |
| ~~B24~~ | ✅ Hecho (2026-09-21): factories donde existen y `userWithRole(SystemRole::X)`. Además, tres tests con nombres de "soft delete" pasan a probar registros inactivos o inexistentes. `ProductionOrderCostVisibilityTest`: preparar datos con factories y pasar los literales de rol a `SystemRole` | RV-13, RV-02 | 30 min |
| ~~B25~~ | ✅ Hecho (2026-09-21): `SystemRole::GUARD` en controladores, Actions, servicio, comandos y seeder. Constante o configuración para el guard `web` en roles y usuarios | RR-08 | 15 min |
| ~~B26~~ | ✅ Hecho (2026-09-21): el dashboard usa las props compartidas `recentAlerts` y `unresolvedAlertsCount` (ya no las vuelve a consultar); un solo tipo `RecentAlert`. Además, `auth`, `recentAlerts` y `unresolvedAlertsCount` se comparten como closures: un POST que redirige ahorra 3 consultas. Dashboard: reutilizar las alertas recientes compartidas en lugar de volver a consultarlas | Revisión 2.4 | 20 min |
| B27 | `role-permissions-fields`: no anunciar dos veces el módulo y mostrar los permisos en solo lectura sin casillas deshabilitadas | Revisión 2.4 | 45 min |
| B28 | Modelo `App\Models\Role` propio que extienda el de Spatie (registrado en `config/permission.php`), con `@property int $id` y los helpers de rol del sistema. **Solo cuando haga falta** (añadir relaciones, scopes o lógica al rol): hoy los tipos de Wayfinder ya salen bien porque el CI genera las rutas contra PostgreSQL | CI (PR #145) | 30 min |
| ~~B29~~ | ✅ Hecho (2026-09-21): `scopeBindings()` en las rutas de presentaciones; test del 404 con una presentación de otro producto. Rutas anidadas de presentaciones (`products/{product}/variants/{variant}`) con `->scopeBindings()` en lugar del `abort_if` manual del controlador | Revisión política de eliminación | 15 min |
| ~~B30~~ | ✅ Hecho (2026-09-21): 20 llamadas a Wayfinder; retirados `@routes`, `tightenco/ziggy` y `ziggy-js` (`@routes` además publicaba en cada página la lista completa de rutas). Sustituir `route()` de Ziggy por los helpers de Wayfinder en 9 archivos (selector de bodega, formularios de movimientos MP, bodegas, materias primas) y retirar `ziggy-js` y `@routes`: incumple el invariante 6 de `CLAUDE.md` | Lote B, grupo 2 | 1 h |
| B31 | Enlaces al dashboard (logos del menú y la cabecera, `home` de Fortify) llevan a un 403 a un usuario sin `dashboard.view`. Hoy solo le pasa a un usuario sin rol (los 5 roles y todo rol personalizado lo tienen); resolver si algún día existe un rol sin dashboard | Code review grupos 3-4 | 30 min |
| ~~B32~~ | ✅ Hecho (2026-09-21): asignar usuarios a una bodega conserva las asignaciones de los usuarios inactivos (el formulario no los lista y `sync()` las borraba, incluida su bodega por defecto). Test incluido | Code review grupos 3-4 | 20 min |

### Lote C — Refactors de arquitectura (backlog, fuera de la Fase 2)

| # | Tarea | Hallazgos |
| --- | --- | --- |
| C1 | `CreateSalesOrderAction` siguiendo el patrón de producción | CR-05 |
| C2 | Materias primas de la ficha de orden como prop diferida o solo si la orden es operable | CR-06 |
| C3 | Unificar `enumOptions()` con `EnumOptions::for()` y adaptar el `Combobox` | CR-09 |
| C4 | Reorganizar controladores de la raíz en subcarpetas por dominio | CR-15 |
| C5 | Partir `Products/Show.tsx` (1.463 líneas) en componentes: variantes, documentos, fórmulas, calidad | AG-08 |

### Decisiones pendientes

Ninguna. **V-01 cerrada (2026-09-15, lote A4):** el margen de venta es costo y solo se envía con `costs.view`. Las
cotizaciones ya no lo enviaban (PC-10) y la lista de precios deja de hacerlo (PC-04).

### Descartados

- **CR-19** y **CR-20**: no se sostienen tras verificar el código.
- **AG-03**: descartado con prueba empírica (0 consultas SQL en una ruta 100 % inexistente).
- **Caché de permisos de CR-02**: introduciría permisos desactualizados tras un cambio de rol.
