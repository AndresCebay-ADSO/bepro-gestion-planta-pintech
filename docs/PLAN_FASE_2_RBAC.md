# Plan FASE 2 — RBAC + Integridad de datos

> Documento de **diseño y planificación**. No contiene implementación.
> Base de análisis: rama `refactor/unify-user-forms`, commit `8cf631c`.

---

## 1. Diagnóstico del estado actual 

| Métrica | Valor real hoy |
| --- | --- |
| Roles en BD | 4 (`admin`, `produccion`, `comercial`, `operador`) — `RolePermissionSeeder` |
| **Permisos en BD** | **0** — el seeder dice literalmente *"los permisos específicos se definen por operación en Controllers/Middleware"* |
| Policies | 16 clases · **91 métodos** |
| Llamadas `hasRole()` / `hasAnyRole()` en `app/` | **136** |
| Form Requests | 64 (≈20 autorizan con `hasRole` directo, no con policy) |
| Rutas nombradas de aplicación | ≈125 |
| Modelos | 35 · **15 con SoftDeletes** · 20 sin |
| Archivos de test | 97 · **203 llamadas a `assignRole`** en 63 archivos |

### 1.1 Las cinco capas de autorización que hoy conviven

El sistema no tiene una sola puerta, tiene cinco, y **ninguna se deriva de otra**:

1. **Middleware de ruta** — `role:admin,produccion` en `routes/web.php` (`CheckRole`).
2. **Form Request `authorize()`** — a veces policy (`can('viewAny', X::class)`), a veces `hasRole('admin')` a pelo.
3. **Policies** — 91 métodos, todos resolviendo con `hasAnyRole([...])`.
4. **Scopes de visibilidad en modelos** — `Quotation::scopeVisibleTo`, `SalesOrder::scopeVisibleTo`, `PaintDevelopmentRequest::scopeVisibleTo`, todos con `hasRole('admin')` embebido.
5. **Frontend** — `app-sidebar.tsx` con `allowedRoles: [...]` hardcodeado por ítem de menú, más `can` props sueltos por página.

Además hay **dos capas implícitas** que no aparecen en la matriz y sí son control de acceso:

6. **Contexto de bodega** — `WarehouseContextService::availableWarehouses()`: `admin` ve todas las bodegas, el resto solo las asignadas en `warehouse_user`. Esto *es* `warehouses.view_all`, pero está hardcodeado a `hasRole('admin')`.
7. **Ocultamiento de costos** — `RawMaterialController` (`$canViewCosts`), `ProductionOrderController` (`$includeCosts` vía `previewCosts`), `PriceListService` (`$isAdmin`). Cada uno lo resuelve a su manera.

**Conclusión del diagnóstico:** migrar solo las policies (tarea 2.2) deja 4 capas sin migrar y el sistema queda incoherente. El plan tiene que cubrir las 7.

---

## 2. Hallazgos críticos (decisiones de diseño que bloquean la implementación)

### 🔴 C1 — `Gate::before` con bypass total rompe los invariantes de estado

La tarea 2.3 propone: *"Gate::before() para que cualquier usuario con rol admin pase todas las autorizaciones"*. **Esto introduce bugs reales**, porque las policies actuales mezclan dos cosas distintas:

```php
// app/Policies/ProductionOrderPolicy.php:54
public function complete(User $user, ProductionOrder $productionOrder): bool
{
    if (! $user->hasAnyRole(['admin', 'produccion'])) return false;   // ← PERMISO
    if (in_array($productionOrder->status, [Completed, Cancelled])) return false;  // ← INVARIANTE
    return in_array($productionOrder->status, [InProgress, PendingReview]);
}
```

Un `Gate::before` que devuelve `true` **corta la ejecución antes de llegar al invariante**. Resultado: un admin podría completar una orden **ya cancelada** o **ya completada** (doble descuento de inventario FIFO). Lo mismo con `QuotationPolicy::convertToOrder` (cotización ya convertida → segunda orden de venta) y `updateStatus`.

**Recomendación:**

> **📝 Tu nota:** Para mi la ocpion A es la mejor, simplemente me doy permisos, despiues de todo ese super-admin vendra a ser el usuario de soporte o tech con acceso a todo el software
>
> **✅ Resolución:** **Decidido: opción A.** SuperAdmin es el usuario de soporte/tecnología; recibe todos los permisos vía seeder. Sin `Gate::before`.

- **Opción A (recomendada):** *no* usar `Gate::before`. Al rol `super-admin` se le asignan **todos los permisos** vía seeder (`$role->syncPermissions(Permission::all())`). Las policies siguen siendo `permiso && invariante`. Cero riesgo.
- **Opción B:** `Gate::before` que devuelve `true` solo si la ability está en una **lista blanca de abilities sin invariante de estado** (`view`, `viewAny`, `create`, `export*`), y `null` (sin opinión) para las de ciclo de vida (`complete`, `cancel`, `convertToOrder`, `submitForReview`, `rejectReview`, `startProduction`, `updateStatus`, `activate`). Más frágil: cada ability nueva hay que clasificarla.

En cualquier caso, **antes** de esto hay que separar permiso de invariante dentro de cada policy (ver 2.2).

### 🔴 C2 — `DashboardService` explota con cualquier rol nuevo — ✅ resuelto

```php
// app/Services/DashboardService.php:31-38
$role = $user->getRoleNames()->first();
return match ($role) {
    'admin' => ..., 'produccion' => ..., 'comercial' => ..., 'operador' => ...,
    default => throw new \LogicException("Dashboard: rol no soportado: {$role}"),
};
```

El momento en que un SuperAdmin cree el rol `calidad` desde la UI de 2.4 y se lo asigne a alguien, **ese usuario recibe un 403 al entrar** (corrección: no era un 500; `DashboardController` filtraba antes con una lista fija de 4 roles, así que la excepción nunca llegaba a lanzarse, pero el efecto es el mismo porque el dashboard es la página de llegada tras el login). Y si creamos el rol `super-admin`, el propio SuperAdmin no puede ver el dashboard. **La tarea 2.4 es inviable sin arreglar esto primero.**

> **✅ Resuelto (paso previo a la 2.2):** la entrada exige `dashboard.view`. Las 4 vistas se conservan, pero se eligen
> por permiso (`DashboardService::resolveProfile`): `users.view` → administración, `production_orders.create` → producción,
> `production_orders.view` → planta, `quotations.*`/`sales_orders.*` → comercial, ninguno → vista vacía de bienvenida.
> Cada dato solo se calcula si el usuario tiene su permiso, y la tarjeta sin dato se oculta. Super-admin recibe la vista
> de administración. Hay que pasar el dashboard a composición por permisos (cada tarjeta se muestra si su permiso está presente) o, como mínimo, a un fallback seguro.

> **📝 Tu nota:** en estos casos actualemtne el sofwtare sigue un flujo de permisos hechos por el vibecoding, realmente a penas a estas alturas haremos todo el tema de permisos reales, no es algo que digamos que actualemtne este bien por el momento tal vez por esas razones en perimosso o roles hay tanta cosa
>
> **✅ Resolución:** Entendido: los permisos actuales salieron del vibe coding y **no son un contrato**. La matriz pasa a ser la fuente de verdad (ver `MATRIZ_RBAC.md` §6 para las diferencias). El dashboard se compone por permisos de cada módulo, sin permisos `dashboard.*` nuevos.


### 🟠 C3 — El sistema asume "un rol por usuario" en 5 sitios

`UserController::update` → `syncRoles([$validated['role']])` · `Admin/Users/Edit.tsx` → `user.roles[0]?.name` · `Admin/Users/Create.tsx` → un `<Select>` simple · `DashboardService` → `getRoleNames()->first()` · `app-sidebar.tsx` → `extractUserRoles()` con lista cerrada de 4 strings.

Hay que **decidir explícitamente**: ¿un rol por usuario (más simple, coherente con la operación de la planta) o multi-rol? Mi recomendación: **mantener un rol por usuario** como regla de negocio, pero dejar el código tolerante a N roles (usar `->contains()` en vez de `[0]`), porque el coste de asumir 1 es que un día se paga carísimo.

> **📝 Tu nota:** esto d multirol no comprendo, algo que si pense es mas como un rol por usuario, pero en algun caso podria o crear un rol nuevo con permisos diferentes, o a ese usuario asignarle permisos extras, es algo que he estado evaluando
>
> **✅ Resolución:** Lo que describes no es multi-rol (un usuario con dos roles a la vez); es **rol + permisos directos por usuario**. **Decidido: un rol por usuario y sin permisos directos en esta fase.** Si alguien necesita otra combinación, se crea un rol nuevo desde 2.4. Así, la pregunta "¿qué puede hacer esta persona?" se sigue contestando mirando su rol. Spatie ya trae la tabla `model_has_permissions`, así que activar permisos directos más adelante no requiere migración, solo UI.


### 🟠 C4 — La matriz contradice el comportamiento actual en dos módulos

| Permiso | Matriz propuesta | Código real hoy | Impacto |
| --- | --- | --- | --- |
| `formulas.view/create/edit/activate` | solo Admin | `produccion` puede todo (`FormulaPolicy`, ruta `role:admin,produccion`) | **Producción pierde las fórmulas.** ¿Intencional? ✅ **Sí** |
| `products.create` / `products.edit` | solo Admin | `admin` + `produccion` (`ProductPolicy:20-27`, `StoreProductRequest:15`) | Producción ya no crea productos ✅ **Intencional** |

> **📝 Tu nota:** En este caso es intencional, pasa que admin relamente en la empresa es la encargada de produccion y jefa de la empresa, produccion son mas auxiliares o ayudantes que tiene, por tanto la jefa quiere seguir manteniendo el control de muchas cosas / exacto por la misma razon que explico en la linea 87
>
> **✅ Resolución:** C4 cerrado: es intencional. Admin es la jefa de la empresa y de producción; Producción son sus auxiliares. Queda recogido en `MATRIZ_RBAC.md` §0 como contexto organizacional, porque explica buena parte de la matriz.

Al ser intencional, es una **restricción de alcance funcional**, no una migración técnica: hay que avisar a los auxiliares de producción antes del despliegue (`MATRIZ_RBAC.md` §6).

### 🟠 C5 — Permiso de la matriz que hoy no está implementado

`production_remnants` → *"Costos ocultos para Producción y Operador"*. Hoy `RemnantController.php:47` envía `cost_per_gallon` a **todos** los que ven la página (admin, produccion, operador). No es una migración, es **funcionalidad nueva** a construir.

> **📝 Tu nota:** el tema de costos nadie a parte de admin puede ver el de remnant, aunque operador o produccion puedan ver la tabla de remnants, no deberia poder ver el costo
>
> **✅ Resolución:** Confirmado. Se resuelve con un único `costs.view` de alcance global (`MATRIZ_RBAC.md`, principio 1).


### 🟡 C6 — `products.code` es `unique` y `Product` usa SoftDeletes

`create_products_table.php:16` → `$table->string('code', 50)->nullable()->unique();` con `SoftDeletes` activo en el modelo. Un producto borrado lógicamente **sigue bloqueando su código** para siempre. Este bug ya existe y **se multiplica** con la tarea 2.6: cada modelo al que le añadamos SoftDeletes y tenga índice único necesita un **índice único parcial** (`WHERE deleted_at IS NULL`) — que en PostgreSQL se hace con `whereNull` en la migración, y en SQLite (tests) se comporta distinto. Es el mayor riesgo técnico de 2.6.

> **📝 Tu nota:** EN ESTE CASO EN EL SOFTWARE NO HE APLICADO NADA DE SOFTDELETES CUALQUERI FORMA DE BORRADO CASCADA NULL ON DELTE, ETC ETC QUE HAYA, FUE EL VIBE CODE QUE LO HIZO PRO ESO TANTA DIFERENCIA O COSAS QUE HAY
>
> **✅ Resolución:** Entendido. **Consecuencia: 2.6 incorpora una auditoría de claves foráneas.** Hoy hay 23 `cascadeOnDelete`, 20 `nullOnDelete` y 51 `restrictOnDelete`. La más peligrosa: `products` borra en cascada `price_lists`, `production_costs`, `formulas` (→ `formula_details`), `finished_inventories`, `product_variants`, `product_documents` y `qr_codes`. Un borrado físico de producto se lleva su historial de precios y costos.


### 🟡 C7 — El impacto en tests está subestimado

**203 llamadas a `assignRole`** en 63 archivos. En cuanto las policies pregunten `can('x.y')`, todos los tests que hacen `User::factory()->create()->assignRole('produccion')` sin sembrar permisos **empiezan a devolver 403**. Necesitamos, antes de tocar la primera policy:
- sembrar permisos en el bootstrap de tests (`RefreshDatabase` borra la BD en cada test),
- limpiar la caché de Spatie entre tests (`PermissionRegistrar::forgetCachedPermissions()`), porque si no los tests se contaminan entre sí de forma intermitente,
- un helper `actingAsRole('produccion')` para no reescribir 203 líneas a mano.

### 🟡 C8 — Con RBAC fino, los 403 se vuelven frecuentes y hoy son feos

La página 403 personalizada está planificada en **4.1**. Al endurecer permisos, un usuario verá 403 muchísimo más seguido (y el `CheckRole` actual aborta con un `abort(403, 'No tienes permiso...')` plano de Symfony). **Recomiendo adelantar 4.1 a la subfase 2.8.**

---

## 3. Catálogo de permisos — qué falta respecto a tu matriz

Tu matriz tiene **~75 permisos** y cubre bien el esqueleto. Auditando los ~125 endpoints reales, faltan estos. Marco con 🆕 lo que no existe en tu tabla.

### 3.1 Módulos completos que faltan

> **📝 Tu nota:** Lo de perfil es real, me falto ello, lo unico es que no creo que un usuario tenga la opcion de eliminar cuenta, y de hacerlo que realmente si a mucho se desactive, los movimeintos de mp o pt, no van a tener oportunidad de eliminarse o editarse, si actualemtenel codigo muestra eso se borrara a futuro, ya que si dejamos ello puede que rompa trazabilidad en algun momento, lo de documetnos download me parece bien, no compredi lo de precios dentro del form de producto catalogos de fase 3, los cruds de categorias, unidades de medida, quedaran para que admin pueda leer, pero solo superadmin crear editar, y borrar y esos quedaran dentro de configuracion el variantes realmente no se si dejalro en productos la verdad es mucho mas facil en vista que creales una pgian dedicada, a mi parecer, simplemetne entro al producto y veo sus presentciones a mi parecer es mucho mas facil para un usuario.
>
> **✅ Resolución:** Resuelto en `MATRIZ_RBAC.md`: (1) se elimina la auto-eliminación de cuenta; (2) movimientos MP y PT inmutables: se retiran edit/delete de MP (PT ya lo es); (3) `products.download_documents` aceptado; (4) *precios en el formulario de producto* = los campos CIF % y umbral de precio, que recalculan costos; quedan bajo `costs.update`, sin permiso nuevo; (5) catálogos: Admin lee y SuperAdmin gestiona, dentro de Configuración; (6) variantes siguen dentro del producto, **lo que cambia la tarea 3.3**.


| Módulo | Permisos que faltan | Por qué |
| --- | --- | --- |
| **Perfil propio** | 🆕 `profile.edit`, 🆕 `profile.delete_own` | `profile.edit/update/destroy` existen y **cualquier autenticado puede auto-eliminarse** (`routes/settings.php`). Hay que decidir si eso se permite. |
| **Contexto de bodega** | 🆕 `warehouses.switch_context` | `warehouses.set-current` está abierto a todo autenticado. |
| **Movimientos MP** | 🆕 `inventory_movements.edit`, 🆕 `inventory_movements.delete` | Existen `inventory-movements.update` (admin+producción) y `.destroy` (admin). Tu matriz solo tiene view/create. |
| **Documentos de producto** | 🆕 `products.documents.download` (separado de `manage_documents`) | `products.documents.download` hoy lo puede usar cualquiera que vea el producto — incluido **comercial**, que en tu matriz no tiene `manage_documents`. Son dos permisos distintos. |
| **Precios dentro del form de producto** | 🆕 `products.manage_prices` | `ProductController:95,183` bloquea cambios de CIF/umbral con `Gate::allows('create', PriceList::class)`. Tu matriz solo tiene `price_lists.view` — falta el permiso de escritura que ya se usa. |
| **Catálogos (Fase 3)** | 🆕 `unit_of_measures.*`, 🆕 `product_categories.*`, 🆕 `raw_material_categories.*` | Los CRUDs llegan en 3.1/3.2. **Definir los permisos ahora** evita una segunda migración de permisos en dos semanas. |
| **Variantes como módulo** | 🆕 `product_variants.view/create/edit/delete` | 3.3 les da index y páginas propias. Hoy viven bajo `products.manage_variants`. |

### 3.2 Permisos que faltan dentro de módulos que sí tienes

> **📝 Tu nota:** a mi parecer una orden de produccion no deberia de poderse eliminar, por el hecho de que, es un dilema el tema de las infinitas cosas, que hace la orden por tanto el eliminar a mi parecer no deberia de existir, me paerce bien el de preview costs, a mi parecer el de exportar los dos, tienen sentido dejarlos juntos, realemtne el pdf es apra que los chicos lo descarguen y lo usen, y el de excel por si deben ajustar algo,  lo que dices de pedidos de vneta me parece bien, aunque no lo entendi del todo que recomiendas, pedidos, realmente un pedido edit y delete lo pienso por el hecho de que si lo editan despues de que tal vez el admin ya lo empezo pues seria arriesgado y eliminarlo pues no se que opines evalua ello lo que creas mejor, remanentes necesito que los costos no se vean. inventario pt me parece correcto lo de view cost, tal vez para que algunos usuarios no vean precios aunque no se te entiendo esa parte, una cosa es precio y otra costo, quotations realmente no veo mucho caso, por el tema de que las personas que vean quotations en el caso de la matriz solo seran admin y comercial ellos si pueden ver precios claro esta. lo de alertas no lo entendi del todo. auditoria tmapco compredni que dices, user pues no se que opines, deside lo mejor en ello si separar o dejar solo edit, como el form tiene el checkbox dentro pues no encuentro problema, pero si no es lo correcot evalua, lo que dices de los roles correcto, me parece perfecto, ese analisis.
>
> **✅ Resolución:** Resuelto en `MATRIZ_RBAC.md`: orden sin `delete` (el `delete` de la policy en realidad es *cancelar*, se renombra); export PDF+Excel juntos; pedidos sin `delete` (se cancelan por estado), `edit` solo en `pending` y `update_status` separado, que recupera Producción (confirmado); costos de remanentes ocultos; inventario PT no muestra costos ni precios hoy, así que se descarta `view_costs`; `quotations.view_costs` descartado; **alertas**: me refería a la campana de la cabecera, que hoy se decide por rol, y pasa a usar `alerts.view`; **auditoría**: solo significa que hoy usa un `Gate` suelto, que se reemplaza por `audit_logs.view`, sin decisión de negocio; usuarios: todo se queda en `users.edit`.


| Módulo | Falta | Detalle |
| --- | --- | --- |
| Órdenes Prod. | 🆕 `production_orders.delete` | `ProductionOrderPolicy::delete` existe (admin) pero **no tiene ruta**. Decidir: implementarla o borrar el método muerto. |
| Órdenes Prod. | 🆕 `production_orders.view_costs` | Renombrar `preview_costs`: ese permiso hoy controla **tres cosas** — el endpoint de preview, si el PDF lleva costos y si el Excel lleva costos (`ProductionOrderController:112,150,181`). |
| Órdenes Prod. | separar `export_pdf` / `export_excel` | Tu `production_orders.export` cubre 2 endpoints. Probablemente esté bien fusionarlo, pero hay que decidirlo conscientemente. |
| Pedidos Venta | 🆕 `sales_orders.view_own` | `SalesOrderPolicy::view` ya distingue "comercial ve solo los suyos". `view_all` sin su contraparte `view_own` no es implementable. Mismo caso en **Cotizaciones** y **Desarrollo de pinturas**. |
| Pedidos Venta | `sales_orders.edit` / `.delete` sin ruta | La policy tiene `update`/`delete`; la única ruta es `PATCH sales-orders/{id}` que hace *update de estado*. Hay permisos en tu matriz sin endpoint detrás. |
| Remanentes | 🆕 `production_remnants.view_costs` | Ver C5: el ocultamiento de costos no existe todavía. |
| Inventario PT | 🆕 `finished_inventory.view_costs` | Mismo criterio que MP: si `raw_materials.view_costs` existe, PT necesita su equivalente. |
| Cotizaciones | 🆕 `quotations.view_costs` / margen | El PDF y el form muestran márgenes y costos derivados (relacionado con la tarea 1.5). |
| Alertas | 🆕 `alerts.receive_notifications` | `HandleInertiaRequests:100-105` inyecta `unresolvedAlertsCount` y `recentAlerts` con `hasAnyRole(['admin','produccion'])`. Es una capa de acceso más, hoy invisible en la matriz. |
| Auditoría | `audit_logs.view` ya existe en tu matriz | Pero hoy se resuelve con un `Gate::define('view-audit-logs')` suelto en `AppServiceProvider:84`. Hay que migrarlo, no olvidarlo. |
| Usuarios | 🆕 `users.deactivate` (distinto de `users.edit`) | Tu matriz mete activar/desactivar dentro de `users.edit`. Es la operación más sensible del módulo (bloqueo de acceso) y merece permiso propio. |
| Usuarios | 🆕 `users.manage_signatures` | La firma va a documentos oficiales (certificados de calidad). Editar la firma de otro es distinto de editar su teléfono. |
| Roles | 🆕 `roles.assign_permissions` | Separar "crear un rol" de "otorgarle permisos a un rol" — es el permiso que permite escalada de privilegios. |

### 3.3 Nomenclatura: dos correcciones

> **📝 Tu nota:** me equivoque escribiendo el dessactive, lo de materias primas analiza, y decide
>
> **✅ Resolución:** Decidido: `products.deactivate`. Materias primas: el `destroy` actual se parte en `raw_materials.deactivate` (Admin) y `raw_materials.delete` (SuperAdmin, borrado físico solo si está intacta).


- `products.desactive` → **`products.deactivate`** (typo; el código va en inglés según `CLAUDE.md`).
- `raw_materials.reactivate` existe pero falta `raw_materials.deactivate`. Hoy `raw-materials.destroy` **hace las dos cosas** (`RawMaterialController::destroy`: borra físico si no tiene actividad, desactiva si la tiene). Es exactamente la lógica de la tarea 2.7 — **ya está implementada ahí y sirve de patrón de referencia**.

### 3.4 Regla de granularidad propuesta

No un permiso por endpoint (serían ~125 y la UI de roles sería inmanejable). La regla:

> **Un permiso = una capacidad que el negocio querría poder apagar por separado.**

Con eso el catálogo queda en **~95-105 permisos** en 18 módulos. Es manejable en una UI de checkboxes agrupados si se pintan por módulo con un "marcar todo el módulo".

### 3.5 Fuente única de verdad

Propongo `app/Support/PermissionRegistry.php` (o un enum `App\Enums\Permission`) que declare módulo → permisos → etiqueta en español. De ahí consumen **cuatro** sitios: el seeder, la UI de roles (agrupación), el `can` compartido a Inertia, y un **test de arquitectura** que falle si una policy referencia un permiso no registrado. Sin esto, en tres meses habrá permisos huérfanos en BD y permisos usados que nadie sembró.

---

## 4. Plan por subfase

Estimaciones en días de trabajo efectivo, asumiendo un desarrollador.

---

### 2.1 — Diseñar permisos por módulo

**Objetivo:** cerrar el catálogo definitivo y las decisiones abiertas. **Sin código.**

**Entregables**
1. `docs/MATRIZ_RBAC.md` — la matriz final (tu tabla + sección 3 de este documento), con una columna extra **"Endpoint(s) / policy que lo implementa"**. Sin esa columna es imposible verificar la migración.
2. Decisiones firmadas sobre: C1 (estrategia super-admin), C3 (mono vs multi-rol), C4 (¿producción pierde fórmulas y creación de productos?), `profile.delete_own`, granularidad de exports.
3. Borrador de `PermissionRegistry` (solo la estructura de datos, aún sin integrar).

**Criterio de aceptación:** cada uno de los ~125 endpoints nombrados está mapeado a exactamente un permiso, o marcado explícitamente como público (`qr.public.*`) o como universal-autenticado (`dashboard`, `profile.*`).

**Riesgo:** bajo. **Estimación: 1 día.**

---

### 2.2 — Migrar policies a permisos

**Objetivo:** las 16 policies dejan de preguntar por rol y preguntan por permiso, y el comportamiento pasa a ser **exactamente el de `MATRIZ_RBAC.md`**. El comportamiento actual no es contrato (vibe coding), así que los cambios de acceso listados en su §6 son intencionales.

**Orden interno obligatorio**
1. **Primero el seeder de permisos + asignación a roles** (esto es tu tarea 2.9 — *va antes, no después*). Si migras una policy a `can()` antes de que existan los permisos, todo devuelve 403.
2. **Después** la infraestructura de tests (C7): helper `actingAsRole()`, seeding de permisos en el bootstrap, reset de caché de Spatie.
3. **Después** las policies, en este orden de menor a mayor riesgo:
   - Trivial (solo `hasAnyRole`, sin estado): `Alert`, `FinishedInventory`, `FinishedInventoryMovement`, `ProductionRemnant`, `QrCode`, `RawMaterial`, `Client`, `PriceList`, `Formula`, `Product`, `InventoryMovement`, `Warehouse`.
   - Con ownership (`created_by`): `Quotation`, `SalesOrder`, `PaintDevelopmentRequest` → patrón `can('x.view_all') || (can('x.view') && $model->created_by === $user->id)`.
   - Con máquina de estados: `ProductionOrder` (12 checks de rol en un solo archivo) → **separar permiso de invariante** antes de tocar nada.
4. **Los scopes de visibilidad de los modelos** (`scopeVisibleTo` × 3) migran junto con su policy — si no, la policy dice "ve solo los suyos" y la lista sigue mostrando todo, o al revés.
5. **Los 64 Form Requests**: los ~20 que hacen `hasRole()` directo pasan a `can()`. Los que ya delegan en policy no se tocan.
6. **Los servicios**: `WarehouseContextService` (→ `warehouses.view_all`), `PriceListService` (`$isAdmin`), `HandleInertiaRequests` (alertas), `RawMaterialController::$canViewCosts`, `ProductionOrderController::$includeCosts`.

**Patrón de referencia para policies con estado:**

```
permiso   → ¿este rol puede, en general, completar órdenes?   → $user->can('production_orders.complete')
invariante→ ¿ESTA orden está en un estado completable?        → $order->status->isCompletable()
policy    → permiso && invariante
```

Esto además mata C1 de raíz y hace los invariantes testeables sin usuario.

**✅ Método adoptado: migración por módulo completo.** Cada commit migra un módulo de punta a punta: policy, form
requests, controladores, **rutas** (salen de los grupos `role:` y pasan a `can:<permiso>` en el bloque
"RUTAS PROTEGIDAS POR PERMISO" de `routes/web.php`) y sus tests, que adoptan el seeder o los helpers. Migrar solo la
policy dejaría la ruta bloqueando los accesos nuevos de la matriz. El test `RoutePermissionMapTest` exige `can:<permiso>`
en toda ruta que ya no tenga `role:`. Con este método, la 2.8 se reduce a las páginas de error y a retirar `CheckRole`.

**Avance por módulo**

| Lote | Módulo | Estado |
| --- | --- | --- |
| 1 | Alertas | ✅ Producción pierde `resolve`. La campana usa `alerts.view` (permisos compartidos con el frontend en `auth.user.permissions`). |
| 1 | Inventario PT + movimientos | ✅ Operador gana la vista del inventario (no sus movimientos). |
| 1 | Saldos de producción | ✅ Operador gana acceso (antes el sidebar se lo mostraba y la ruta daba 403). Costo por galón solo con `costs.view`. |
| 1 | Códigos QR | ✅ Comercial gana la vista; Producción pierde la edición. |
| 1 | Dashboard | ✅ Ruta con `can:dashboard.view`. |
| 2 | Materias primas | ✅ Costos solo con `costs.view` (Producción los pierde). `destroy` exige `raw_materials.deactivate`: Admin desactiva y solo SuperAdmin borra físicamente (se separan en endpoints propios en la 2.7). |
| 2 | Fórmulas | ✅ Producción pierde el módulo completo. `activate` pasa a tener su propia habilidad en la policy. |
| 2 | Bodegas | ✅ `warehouses.view_all` sustituye al `hasRole('admin')` de `WarehouseContextService` (qué bodegas ve cada quien y el selector de la cabecera). Asignar usuarios tiene su propia habilidad. |
| 2 | Clientes | ✅ Comercial ve y crea; editar y eliminar quedan en Admin. |
| 3 | Productos (variantes y documentos) | ✅ Producción pierde crear/editar productos y gestionar documentos; conserva las presentaciones. La ficha deja de enviar costo, CIF y umbral sin `costs.view`, y las fórmulas sin `formulas.view` (antes los recibían Comercial y Producción). CIF/umbral exigen `costs.update`; la casilla "Producto activo" exige `products.deactivate`. `UpdateProductRequest` autorizaba con *crear*: ahora con *editar*. |
| 3 | Movimientos MP | ✅ Operador gana la vista; Producción pierde el registro. `cost_price` oculto sin `costs.view`; los lotes con precio solo se envían a quien puede registrar. **Editar y borrar retirados** (rutas, lógica de servicio, request, página placeholder y sus tests): la interfaz nunca los ofreció, pero el backend los aceptaba por petición directa. Policy inmutable, como PT. |
| 3 | Costos | ✅ Adelantado del lote 4: el margen de venta exige `costs.update` (antes pasaba por la policy de producto). |
| 3 | Fuga corregida del lote 1 | ✅ Movimientos PT: `cost_price` oculto sin `costs.view` en listado y detalle (Producción lo veía). |
| 4 | Listas de precios | ✅ Costos de la lista solo con `costs.view`. La policy se reduce a ver: las listas son históricas y nadie las edita a mano. |
| 4 | Usuarios | ✅ Nueva `UserPolicy`. Admin pierde el borrado (solo SuperAdmin); nadie salvo un SuperAdmin modifica o elimina a otro SuperAdmin; cambiar el rol exige `users.manage_roles`. La pantalla de usuarios dejaba ver a Admin las 5 últimas entradas de auditoría: ahora solo con `audit_logs.view`. Los literales `'admin'` pasan a `SystemRole`. La protección "último admin activo" sigue igual hasta la 2.3. |
| 4 | Auditoría | ✅ Solo SuperAdmin (Admin la pierde). Se elimina el `Gate::define('view-audit-logs')` suelto. Los accesos rápidos del dashboard se filtran por permiso en los módulos migrados, así que el enlace a Auditoría ya no le aparece a Admin. |
| 4 | Perfil propio | ✅ Retirada la auto-eliminación de cuenta (ruta, método, request, componente `delete-user` y sus tests), como se decidió en la matriz. |
| 5 | Cotizaciones | ✅ `view_own`/`view_all` con el trait `AuthorizesOwnedRecords` (compartido por los tres módulos con dueño): sin `view_all` solo se ve y se actúa sobre lo propio. Convertir en pedido exige además `sales_orders.create`. Listado y detalle se autorizan con la policy (`can:viewAny` / `can:view`). Se retira el `delete` de la policy (sin ruta ni permiso). |
| 5 | Pedidos de venta | ✅ El PATCH único se separa: `sales-orders.update` (datos, `sales_orders.edit`, solo Admin y **solo en pendiente**) y `sales-orders.update-status` (estado, `sales_orders.update_status`, Admin y Producción). El panel lateral queda en dos formularios. **Producción pierde editar prioridad, fecha estimada, contacto y notas**: solo cambia el estado. |
| 5 | Desarrollo de pinturas | ✅ Producción pierde el módulo completo (solo la jefa revisa). Enviar tiene su propia habilidad (`submit`). |
| 5 | Scopes de visibilidad | ✅ `scopeVisibleTo` de `Quotation`, `SalesOrder` y `PaintDevelopmentRequest` usan `*.view_all` en vez del rol. |
| 6 | Órdenes de producción | ✅ `ProductionOrderPolicy` separa permiso y estado en cada habilidad. Operar solo en proceso; **en revisión opera quien tiene `production_orders.complete`** (ya no depende del rol Operador). Enviar a revisión pasa a Admin, Producción y Operador (antes solo Operador). Costos de la orden (preview, PDF, Excel) solo con `costs.view`: **Producción deja de verlos**. `delete` pasa a llamarse `cancel`; se retira `update`, que nadie usaba. Firmantes del certificado de calidad: quien tiene `production_orders.complete`, tanto en la lista (`User::permission`) como en la validación. |
| — | **Cierre de la 2.2** | ✅ Las 112 rutas de la aplicación usan `can:`; ninguna usa `role:` (test `no protege ninguna ruta por rol`). Los únicos `hasRole` restantes son las excepciones previstas: la regla de SuperAdmin de `UserPolicy`, las protecciones de administrador de `UserController` (se revisan en la 2.3) y el middleware `CheckRole`, que ya no usa ninguna ruta y se retira en la 2.8. |

**Adelanto parcial de la 2.5:** `NavItem` acepta `allowedPermissions`, y el sidebar decide por permiso en los módulos
ya migrados (dashboard, materias primas, fórmulas, bodegas, clientes, inventario PT y sus movimientos, saldos, alertas y
QR). El resto de ítems sigue con `allowedRoles` **a propósito**: si decidieran por permiso antes de migrar su ruta,
mostrarían enlaces que responden 403 (por ejemplo, movimientos de materia prima a Operador).

**Criterio de aceptación:** `grep -rn "hasRole\|hasAnyRole" app/` devuelve **0 resultados** fuera de `UserController` (protección del último admin) y del futuro chequeo de `super-admin`. El test de matriz de acceso (ver 2.8) pasa en verde. Los tests existentes solo cambian donde la matriz modifica un acceso a propósito.

**Riesgo: ALTO.** Es la subfase donde se rompe todo si se hace en un solo commit.
**Mitigación:** un commit por policy, con sus tests, y un test de regresión por rol que recorra las rutas principales (ver 2.8).
**Estimación: 4 días** (1 seeder+tests infra, 3 policies+requests+servicios).

---

### 2.3 — Super-admin

**Objetivo:** un rol por encima de `admin` que nunca se queda fuera.

**Decisión pendiente (C1).** Recomendación: **sin `Gate::before`**; `super-admin` recibe todos los permisos vía seeder.

**Trabajo asociado que tu plan no lista y es obligatorio:**
- Crear el rol y **al menos un usuario super-admin** (`UserSeeder`, idempotente, gateado por entorno para el mock).
- **Blindar el rol en la UI**: `UserController::create/edit` hace hoy `Role::all()` → un admin vería `super-admin` en el select y podría auto-promoverse. Hay que filtrar server-side, no solo en React.
- **Blindar el objeto**: un `admin` no puede editar, desactivar ni eliminar a un `super-admin`.
- Revisar la protección del "último admin" (`UserController:133-146`): la regla pasa a ser "último **super-admin** activo".
- `DashboardService` (C2) tiene que saber qué mostrarle.
- `app-sidebar.tsx` → `UserRole` en `types/auth.ts` es un union cerrado de 4 strings; hay que abrirlo.

**Criterio de aceptación:** test que verifique que un `admin` recibe 403 al intentar asignar el rol `super-admin` vía POST directo (no solo que el select no lo muestre).

**Riesgo: medio-alto** — es la superficie clásica de escalada de privilegios.
**Estimación: 1,5 días.**

---

> **✅ Estado 2.3 (2026-09-14):**
> - `User::isSuperAdmin()` centraliza la regla; lo usan `UserPolicy`, las requests y `UserController`.
> - Solo un SuperAdmin ve y asigna el rol `super-admin` (selector y validación). Admin nunca lo ve ni puede enviarlo a mano.
> - Protecciones al editar usuarios: nadie cambia su propio rol ni se desactiva; no se puede desactivar ni degradar al
>   **único SuperAdmin activo** ni al **único Admin activo** (se conserva la de Admin para que la empresa no se quede sin
>   su jefa por error). No se elimina a un SuperAdmin ni a un Admin: se desactivan.
> - Usuario de soporte: en local/testing, `UserSeeder` crea `soporte@pintech.test` como SuperAdmin. En producción,
>   `php artisan users:grant-super-admin {email}` otorga el rol a un usuario existente y activo, con confirmación y
>   registro en auditoría (no crea usuarios ni contraseñas por consola).

> **✅ Adelantos tras la revisión de la rama (2026-09-15, ver `docs/REVISION_RAMA_RBAC.md`):**
> - **2.5 (menú):** el sidebar decide **solo por permisos**; se eliminaron `allowedRoles` y la lógica por rol.
>   "Configuración" es visible para todo usuario con sesión; "Reportes" usa `production_orders.create`.
> - **2.8 (parcial):** retirado el middleware `CheckRole` y su alias `role`. Quedan las páginas de error 403/404/500.
> - Dashboard comercial: respeta `view_own` / `view_all` mediante los scopes `visibleTo()`.
> - **Decisión sobre los permisos compartidos con el frontend (`auth.user.permissions`):** se mantiene la lista completa
>   y **sin caché**. Son 3 consultas pequeñas por petición; un caché por usuario dejaría permisos desactualizados tras un
>   cambio de rol (Spatie solo invalida su propio caché). La autorización real siempre se decide en servidor.

### 2.4 — CRUD de Roles en la UI

**Bloqueada por C2.** Antes de la primera línea: arreglar `DashboardService`, o el primer rol nuevo produce un 500.

**Alcance**
- Backend: `RoleController` (index/create/store/edit/update/destroy), `RolePolicy`, Form Requests, `RoleFilter extends QueryFilter` (siguiendo el patrón de listados de `CLAUDE.md`).
- Frontend: `pages/Admin/Roles/{Index,Create,Edit}.tsx`, checkboxes agrupados por módulo alimentados por `PermissionRegistry`, `TableActions` para las filas (Ver → Editar → Eliminar, iconos + tooltip + `sr-only`, según el estándar obligatorio de UI).
- Sidebar: nuevo ítem en grupo SISTEMA.

**Reglas de negocio que hay que implementar (fáciles de olvidar)**
- Los 5 roles del sistema **no se pueden eliminar ni renombrar** (marca `is_system` o lista blanca) — si alguien borra `produccion`, se cae `DashboardService`, el sidebar y 60 tests.
- No se puede eliminar un rol **con usuarios asignados** (o se exige reasignar). Esto es exactamente la lógica de 2.7 aplicada a roles.
- Un rol no puede otorgar permisos que quien edita no posee (evita escalada).
- Un rol nuevo sin `dashboard.view` deja a sus usuarios con un 403 en la página de llegada: el formulario de
  crear rol debe traer `dashboard.view` **marcado por defecto**.
- Al guardar permisos: `PermissionRegistrar::forgetCachedPermissions()`, o los cambios no se ven hasta 24 h después (caché de Spatie).
- Registrar los cambios de permisos en el activity log (`security`), como ya se hace con los cambios de rol en `UserController:199-207`.

**Estimación: 3 días** (1,5 backend + 1,5 frontend).

> **✅ Estado 2.4 (2026-09-16):** implementada y en `develop` (PR #144, 2026-09-18).
>
> **Decisiones del usuario:**
> 1. **Nombre del rol personalizado:** se guarda en `roles.name` tal como se escribe ("Jefe de calidad"), sin migración.
>    `SystemRole::labelFor()` lo muestra por su nombre. No puede repetir otro rol ni el nombre o la etiqueta de un rol
>    del sistema (sin distinguir mayúsculas).
> 2. **Permisos reservados:** un rol personalizado nunca tiene los 13 permisos que solo tiene SuperAdmin (gestión de
>    roles, auditoría, gestión de catálogos y borrados físicos). `Permission::isReserved()`; un test exige que coincidan
>    con los permisos sin roles por defecto. **Resuelve B20** (la reserva es la protección efectiva: la dependencia
>    `audit_logs.view` → `costs.view` nunca llega a actuar en un rol personalizado).
> 3. **Dependencias entre permisos:** `Permission::requires()` / `dependencies()`. Cada acción exige el `view` de su
>    módulo (`view_own` en los módulos con dueño; `view_all` también exige `view_own`); `products.deactivate` exige
>    `products.edit`; `costs.update` exige `costs.view`; `quotations.convert_to_order` exige `sales_orders.create`; y
>    **`inventory_movements.create` exige `costs.view`**, porque registrar una entrada implica escribir el precio del
>    lote. **Resuelve B19.** Un test exige que los 5 roles del sistema cumplan todas las dependencias.
> 4. **Crear a partir de un rol existente:** selector que copia los permisos de otro rol, sin los reservados.
>
> **Implementación:**
> - Rutas `roles.*` (resource) con `can:roles.*`; editar y eliminar además con la policy (`can:update,role` /
>   `can:delete,role`). `RolePolicy` registrada con `Gate::policy` (el modelo es de Spatie).
> - Los roles del sistema se ven en solo lectura (`roles.show`): la policy impide editarlos y eliminarlos, y las Actions
>   lo vuelven a comprobar.
> - `RoleFormRequest` valida nombre, permisos asignables y dependencias en servidor; el formulario marca las
>   dependencias al activar un permiso y retira a sus dependientes al desactivarlo.
> - `Create/Update/DeleteRoleAction` en transacción con bloqueo de la fila del rol, y auditoría `security`
>   (`role_created`, `role_updated` con permisos añadidos y retirados, `role_deleted`).
> - **No se elimina un rol con usuarios** (también inactivos): `model_has_roles` borra en cascada y los dejaría sin
>   acceso. `UserController` bloquea la misma fila al asignar un rol, así que asignar y eliminar no se cruzan.
> - **Escalada de privilegios al asignar roles:** `AssignableRoleService` (formularios y validación de usuarios). Salvo
>   un SuperAdmin, nadie asigna un rol con permisos que él no tiene; al editar, el rol actual del usuario se puede
>   conservar. Con los roles actuales Admin sigue viendo los mismos 4 roles.
> - La caché de Spatie se limpia sola (`syncPermissions` y los eventos del modelo `Role`), y los permisos compartidos
>   se leen en cada petición: un cambio se aplica a los usuarios del rol en su siguiente petición.
> - Frontend: `Admin/Roles/{Index,Show,Create,Edit}`, componente `roles/role-permissions-fields` e ítem "Roles" en
>   SISTEMA (`roles.view`).
> - Tests: `RoleManagementTest` (acceso, listado, plantillas, validación, roles del sistema, auditoría, borrado y
>   escalada) y 4 casos nuevos en `PermissionRegistryTest`.
>
> **Sin cambios de acceso** para los roles del sistema: la pantalla es solo de SuperAdmin.
> **Ajustes tras la revisión (lote A6 de `docs/REVISION_RAMA_RBAC.md`):** la búsqueda encuentra los roles del sistema
> también por su etiqueta; todo rol personalizado incluye `dashboard.view`; su nombre no admite `|` ni los nombres
> reservados (`SystemRole::reservedNames()`); y cada tipo de alerta exige el permiso de su módulo.

---

### 2.5 — Asignación de roles a usuarios

**Alcance real, más allá del select:**
- `UserController::create/edit` → `Role::all()` filtrado por lo que el editor puede asignar.
- `StoreUserRequest`/`UpdateUserRequest` → validar contra los roles asignables (hoy la regla es un `exists` genérico).
- Decidir mono/multi-rol (C3) y adaptar `Create.tsx` / `Edit.tsx` (hoy `roles[0]`, `<Select>` simple).
- **Propagar permisos al frontend**: añadir `permissions` (o un `can` plano) a `HandleInertiaRequests::share()` y migrar `app-sidebar.tsx` de `allowedRoles` a `allowedPermissions` — 24 ítems de menú. Sin esto, la UI sigue decidiendo por rol mientras el backend decide por permiso, y aparecen menús que llevan a 403.
- Los **accesos rápidos del dashboard** (`QuickAccessGrid`) también están fijos por vista: hay que filtrarlos por
  permiso. Ejemplo: el enlace "Auditoría" de la vista de administración dará 403 a Admin en cuanto la 2.8 aplique
  `audit_logs.view` (solo SuperAdmin).
- Cuidado con el tamaño del payload: ~100 permisos por usuario en **cada** respuesta Inertia. Usar un array plano de strings, no objetos.

**Criterio de aceptación:** ningún ítem del sidebar visible para un rol lleva a un 403.

**Estimación: 2 días.**

> **✅ Estado 2.5 (2026-09-15):** cerrada.
> - **Etiquetas de rol:** los formularios de crear y editar usuario y el listado muestran la etiqueta del rol
>   ("Producción", "Super administrador") en lugar del nombre interno. `SystemRole::labelFor()` centraliza la
>   traducción (la usan también el dashboard y `UserController`); un rol creado desde la UI (2.4) se muestra por su nombre.
> - Los roles asignables llegan como `{id, name, label}`; `name` sigue siendo el valor que se envía y valida.
> - El rol por defecto al crear (`defaultRole`) llega del servidor: el frontend ya no escribe `'produccion'` a mano,
>   que se habría roto en el paso 11.
> - El listado de usuarios envía arrays explícitos (id, nombre, correo, etiqueta del rol, estado, último acceso) en vez
>   del modelo completo: deja de enviar teléfono, cargo y ruta de la firma, que la tabla no usaba.
> - **Fuera de alcance:** la auditoría (`role_changed`) sigue guardando los nombres internos; es dato histórico.
> - Tests en `tests/Feature/Admin/UserRoleLabelsTest.php`.

---

### 2.8 — Middleware de rutas

**Va después de 2.2/2.9**, nunca antes.

- `routes/web.php`: sustituir los 9 grupos `role:...` por `can:permiso`. Ojo: `can:` de Laravel sobre rutas con modelo enlazado (`can:update,production_order`) se comporta distinto que sobre permisos planos (`can:production_orders.create`). Hay que elegir uno por ruta y ser consistente.
- **Decidir el destino de `CheckRole`**: si ya no se usa, se borra (y su test); si se mantiene para `super-admin`, se documenta.
- **Reagrupar rutas por permiso, no por rol** — hoy `raw-materials` está partido en dos grupos (index/show en uno, el resto en otro) precisamente porque los roles no coincidían; con permisos eso deja de ser necesario y el archivo se simplifica.
- **Adelantar 4.1 aquí** (C8): páginas Inertia 403/404/500.
- **Test de matriz de acceso**: un dataset Pest `[rol, ruta, código esperado]` que recorra las ~125 rutas × 5 roles. Son ~600 aserciones generadas, tarda segundos y es la única forma real de verificar que la migración no abrió ni cerró nada por accidente. **Este test se escribe ANTES de 2.2 y se genera a partir de `MATRIZ_RBAC.md`**, no del comportamiento actual. Arranca en rojo en las celdas de su §6 y se va poniendo en verde a medida que avanza la migración.

**Estimación: 1,5 días** (+0,5 si se incluyen las páginas de error).

> **✅ Estado 2.8 (2026-09-15):** cerrada. Las rutas ya usaban `can:` desde la 2.2 y `CheckRole` se retiró en el lote A.
> - **Páginas de error:** 403, 404, 500 y 503 se renderizan con el componente Inertia `ErrorPage`, sin layout, desde el
>   `respond` que ya existía en `bootstrap/app.php`. No se usa `Inertia::handleExceptionsUsing()` porque Laravel
>   admite un único callback de respuesta y borraría el manejo del 429.
> - 403 y 404 siempre usan la página. 500 y 503 solo con `APP_DEBUG` desactivado: en local se conserva la traza.
> - Las peticiones que esperan JSON siguen recibiendo JSON. Si la página de error falla al renderizar, se entrega la
>   respuesta original de Laravel.
> - La página no muestra el mensaje de la excepción, porque un 404 de modelo expone nombres de clases. Los tres
>   `abort(403, '…')` con texto propio (`UserController`, `ProductController`) muestran el texto genérico de acceso denegado.
> - "Ir al inicio" solo aparece si el usuario tiene `dashboard.view`; sin sesión, el botón lleva al login.
> - Tests en `tests/Feature/ErrorPagesTest.php`.
> - El test de matriz de acceso `[rol, ruta, código]` no se escribió como dataset: su función la cumplen
>   `RoutePermissionMapTest` (cada ruta con su permiso) y `RolePermissionSeederTest` (permisos por rol).
>   **Límite conocido:** en las rutas que aceptan varios permisos (`can:viewAny`, `can:view`) el test solo comprueba que
>   exista un middleware de policy, no cuál; y ningún test recorre roles contra rutas reales. Queda como B17.

---

### 2.9 — Migrar roles existentes a los nuevos permisos

**Reubicación:** esta tarea **abre** la fase, no la cierra (ver 2.2, paso 1).

- Reescribir `RolePermissionSeeder`: crea los 5 roles + los ~100 permisos + las asignaciones, **idempotente** (`firstOrCreate` / `syncPermissions`), tal como exige `CLAUDE.md`.
- **Seeder, no migración.** Una migración de datos que siembre permisos queda congelada; el seeder se re-ejecuta en cada despliegue y reconcilia. Añadir `db:seed --class=RolePermissionSeeder` al procedimiento de deploy (y a la 5.3, CI/CD).
- `php artisan permission:cache-reset` post-deploy.
- Un comando de verificación (`rbac:audit`) que liste permisos en BD sin registrar en el `PermissionRegistry` y viceversa. Barato de escribir, evita meses de deriva.

**✅ Decidido — quién manda sobre los permisos de cada rol:**
- Los **5 roles del sistema** (`SystemRole`) se gestionan **solo en código**: el seeder les reasigna exactamente
  sus permisos por defecto en cada despliegue (`syncPermissions`). Un cambio en esos roles pasa por git y revisión.
  Consecuencia para la 2.4: la pantalla de roles muestra sus permisos en **solo lectura**.
- Los **roles creados desde la UI** son los únicos editables en pantalla, y el seeder **nunca** los toca.
- El seeder elimina los permisos de la BD que ya no existen en el enum `Permission`, así la BD no se desvía del código.

**Nota sobre C7:** verificado que la caché de Spatie **no** contamina los tests: `phpunit.xml` usa `CACHE_STORE=array`
y cada test arranca una aplicación nueva. No hace falta limpiarla en `TestCase`.

**Criterio de aceptación:** ejecutar el seeder dos veces seguidas no cambia nada en la BD.
**Estimación: 1 día** (contabilizado dentro de 2.2).

> **✅ Cierre de pendientes de la 2.9 (2026-09-15, lote A3 de `docs/REVISION_RAMA_RBAC.md`):**
> - **Procedimiento de despliegue documentado** en el `README` (sección *Despliegue a producción*): `migrate`,
>   `db:seed --class=RolePermissionSeeder`, `permission:cache-reset`. Hasta ahora no estaba escrito en ningún sitio, y el
>   entrypoint de producción no lo ejecuta. Automatizarlo queda para la 5.3 (CI/CD).
> - **`rbac:audit` descartado:** el seeder ya elimina de la BD los permisos que no existen en el enum, y
>   `PermissionRegistryTest` falla si el tipo `Permission` del frontend (`resources/js/types/permissions.ts`) difiere del enum.

---

### 2.6 — Soft deletes en modelos críticos

> **🔁 Replanteada (2026-09-18): la 2.6 y la 2.7 se funden en "Política de eliminación", definida en
> `docs/POLITICA_ELIMINACION.md`, que es su fuente de verdad.** Decisión: **no se usan soft deletes**; los datos
> maestros se desactivan (`is_active`) y solo se eliminan físicamente si nunca se usaron, con las claves foráneas en
> `RESTRICT` como guardia. Motivo verificado: el borrado lógico dejaba `NULL` las relaciones del historial (órdenes sin
> producto) y bloqueaba los códigos. El análisis de abajo se conserva como antecedente; los riesgos 1 (índices únicos),
> 2 (costeo FIFO) y 5 (auditoría de claves foráneas) quedan resueltos por la política. La corrección de movimientos MP
> (paso 14) se descartó el 2026-09-21 (ver abajo).
>
> **✅ Implementada (2026-09-18):** ver el estado en `docs/POLITICA_ELIMINACION.md` §5.1.

⚠️ **Esta tarea no es RBAC.** Comparte fase con lo anterior pero no comparte nada técnico. Recomiendo tratarla como **Fase 2B**, después de que 2.1-2.5/2.8/2.9 estén estables y mergeadas. Mezclar ambas en la misma rama hace la revisión imposible.

**Estado real:** 15 de 35 modelos ya tienen `SoftDeletes`. Faltan los que listas (`ProductionOrder`, `ProductionOrderDetail`, `InventoryMovement`, `RawMaterial`, `SalesOrderItem`, `QuotationItem`, `User`) más `InventoryBatch`, `FinishedInventory*`, `ProductionRemnant`, `ProductionCost`.

**Los cuatro riesgos que hay que resolver ANTES de escribir la primera migración**

1. **Índices únicos (C6).** `raw_materials.code` es `unique` sin condición. Al añadir SoftDeletes, un código borrado queda quemado para siempre. Se necesita índice único parcial (`WHERE deleted_at IS NULL`) en PostgreSQL — y `phpunit.xml` corre en **SQLite**, donde el soporte de índices parciales difiere. Hay que probar ambos motores: desde el PR #145 el CI corre la suite en SQLite y en PostgreSQL 16 en cada PR, así que
   cada migración de esta tarea queda probada en los dos. **El bug ya existe hoy en `products.code`.**
2. **Integridad del costeo FIFO.** `InventoryBatch` e `InventoryMovement` son la base del costo. Un `SoftDeletes` aplica `WHERE deleted_at IS NULL` **automáticamente a todas las consultas**, incluidas las sumas de `FifoStockAllocatorService` y los agregados de `InventoryService`. Borrar lógicamente un lote **cambia silenciosamente costos históricos ya facturados**. Mi recomendación: **NO poner SoftDeletes en `InventoryMovement` ni `InventoryBatch`** — son un libro mayor, y un libro mayor no se borra, se reversa con un movimiento de signo contrario. Si aun así se añade, hay que auditar cada agregado del módulo de inventario.
3. **`User` + FKs.** `users.id` está referenciado por `created_by` en media docena de tablas. El soft delete de usuario es lo correcto (`UserController::destroy` ya intenta esa política a mano, atrapando el `23503` de Postgres) — pero hay que revisar que `->with('creator')` no devuelva `null` en listados y PDFs cuando el usuario esté borrado. Bonus: `User::booted()` ya tiene escrita la guarda de firma para cuando llegue SoftDeletes (`app/Models/User.php:58-66`).
4. **`activity_log`** tiene retención de 180 días. Un registro borrado lógicamente sobrevive a su propio historial de auditoría. No es bloqueante, pero conviene documentarlo.

**5. Auditoría de claves foráneas (tras tu nota: se generaron con vibe coding).** 23 `cascadeOnDelete`, 20 `nullOnDelete`, 51 `restrictOnDelete`. Regla propuesta: tablas de historial (`price_lists`, `production_costs`, movimientos, lotes, detalles de orden) → `restrictOnDelete`; cascada solo para hijos sin valor propio (p. ej. `formula_details` de una fórmula borrable). Prioridad: la cascada `products` → historial de precios y costos.

**Decidido (tus notas):** `InventoryMovement` e `InventoryBatch` son libro mayor inmutable y **no** llevan SoftDeletes. Los errores se corrigen con un movimiento contrario registrado a mano y una nota (ver abajo:
la acción de reverso se descartó).

**Corrección de movimientos MP (paso 14 de la 2B) — ❌ descartado (2026-09-21).** Decisión del usuario: sin acción
"Revertir movimiento" ni motivo; un error se corrige a mano con un movimiento contrario y una nota (el campo `notes` ya
existe, hasta 2000 caracteres). Limitación aceptada: si una salida equivocada dejó un lote en cero, la entrada que la
corrige crea un lote nuevo (el selector solo lista lotes con stock); quien corrige usa el precio del lote original. Si
llega a molestar, se permite elegir lotes vacíos en el selector, sin crear la acción. Propuesta original, como
antecedente:
1. Columna `reason` en `inventory_movements` con un enum (compra, ajuste, devolución, corrección…), siguiendo
   `FinishedInventoryMovementReason`. Los movimientos existentes se migran con un valor por defecto.
2. Acción "Revertir movimiento": crea el movimiento compensatorio con `reverses_movement_id` hacia el original, motivo
   *corrección* y nota obligatoria; permite volver a entrar a un lote que quedó vacío (hoy el selector solo lista lotes
   con stock y obliga a crear otro). Un movimiento solo se puede revertir una vez y nunca si pertenece a una orden de
   producción.
3. Permiso nuevo en la matriz (p. ej. `inventory_movements.reverse`, solo Admin) y test de que la reversión recalcula
   el precio de referencia.

**Entregables:** una migración por grupo de tablas (no una gigante), auditoría de agregados, tests de que los borrados no aparecen en listados ni en cálculos de costo.

**Riesgo: ALTO** (afecta dinero). **Estimación: 3 días**, y recomiendo revisión específica del módulo de inventario.

---

### 2.7 — Lógica de eliminación inteligente

> **🔁 Fundida con la 2.6 en `docs/POLITICA_ELIMINACION.md` (2026-09-18).** El patrón extraído ya no comprueba
> relaciones a mano: intenta el borrado y traduce el rechazo de la clave foránea (`23503`) en "tiene historial".

**Buena noticia: ya está implementada y probada en un sitio.** `RawMaterialController::destroy` (líneas 208-247) hace exactamente lo que pides: bloqueo `lockForUpdate`, comprueba relaciones (`inventoryBatches`, `inventoryMovements`, `formulaDetails`, `productionOrderDetails`), borra físico si está limpio, desactiva si tiene historial, y rechaza si hay stock. **Ese es el patrón a extraer.**

`UserController::destroy` implementa una segunda variante (chequeo de actividad + captura de `QueryException` 23503).

**Plan:** extraer un `trait DeletesSafely` o un `SafeDeletionService` con un contrato tipo `relationsToCheck(): array` + `onSoftDelete()` (desactivar vs `deleted_at`), y aplicarlo a `Product` (hoy `ProductController::destroy` hace un `$product->delete()` pelado, **sin ninguna comprobación de relaciones** — ese es el hueco más grande), `Client`, `Formula`, `Warehouse`, `ProductVariant`, `UnitOfMeasure`, `ProductCategory`.

**✅ Decidido — ¿la auditoría cuenta como relación?** **No, salvo cuando el registro es el autor.**

- **Como historial del registro (`subject`): no bloquea el borrado.** Tres motivos:
  1. Todo registro nace con una entrada `created` en la auditoría. Si contara, ningún registro se podría borrar físicamente y la 2.7 no tendría sentido.
  2. El borrado se audita a sí mismo. Verificado en Spatie 4.12.3: el evento `deleted` guarda en `properties.old` los campos auditados, y el `causer` registra quién lo borró. La trazabilidad del borrado no se pierde.
  3. La retención de 180 días la hace inservible como criterio: el mismo registro sería borrable o no según la fecha.
- **Como autor (`causer`): sí bloquea.** Solo aplica a usuarios. Borrar físicamente a quien hizo acciones dejaría entradas de auditoría sin autor. Ya está implementado así en `User::hasActivity()`.
- **Qué sí cuenta como relación:** cualquier clave foránea desde tablas de negocio (lotes, movimientos, fórmulas, órdenes, cotizaciones, pedidos, listas de precios, costos…). Es el criterio que ya aplica `RawMaterialController::destroy`.
- **Condición previa: solo se borran físicamente modelos auditados**, porque sin auditoría el borrado no deja copia. Hoy `UnitOfMeasure`, `ProductCategory` y `RawMaterialCategory` **no** usan `LogsActivity`: hay que añadirlo antes de habilitar su borrado (llegan con los CRUDs de la Fase 3). `ProductVariant` sí audita, pero no declara `logOnly`: revisar qué campos guarda.

**Estimación: 2 días.**

---

## 5. Orden de ejecución recomendado

Tu numeración 2.1 → 2.9 no es un orden ejecutable: 2.9 depende de 2.1 pero 2.2 depende de 2.9, y 2.4 está bloqueada por un bug que no aparece en la lista.

```
FASE 2A — RBAC  (≈13 días)
  1.  2.1  Catálogo + decisiones                       1 d
  2.  ---  Test de matriz de acceso (baseline actual)  0,5 d   ← red de seguridad
  3.  2.9  Seeder de permisos + asignación a roles     1 d
  4.  ---  Infra de tests (helper, seeding, caché)     0,5 d
  5.  C2   Arreglar DashboardService                   0,5 d   ← desbloquea 2.4
  6.  2.2  Migrar policies + requests + scopes         3 d
  7.  2.3  Super-admin + blindajes                     1,5 d
  8.  2.8  Rutas can: + páginas de error (4.1)         2 d
  9.  2.5  Asignación de roles + permisos al frontend  2 d
  10. 2.4  CRUD de roles en UI                         3 d
  11. ---  Renombrar roles a inglés                    0,5 d   ← ✅ hecho
                                                     ────────
                                                      ≈15 días

FASE 2B — Integridad  (≈3 días)  ✅ cerrada
  12. 2.6+2.7  Política de eliminación                 3 d     ← docs/POLITICA_ELIMINACION.md
  14. ---      Corrección de movimientos MP            ❌ descartado: corrección manual con nota
```

**Paso 11 — renombrar los roles a inglés.** Los valores `'produccion'`, `'operador'` y `'comercial'` vienen del
vibe coding e incumplen la regla de código en inglés (`CLAUDE.md`). Al llegar aquí, las policies (2.2), los
middlewares (2.8), el sidebar (2.5) y los tests (helper `actingAsRole`) ya no usan esos textos: solo quedan en
`SystemRole` y en la tabla `roles`. El cambio es:
1. `SystemRole`: `'produccion'` → `'production'`, `'operador'` → `'operator'`, `'comercial'` → `'commercial'`.
2. ~~Una migración de datos que actualiza `roles.name`~~: descartada, no hay producción (ver el estado abajo).
3. Actualizar el test que hoy protege los nombres en español.

> **⚠️ Reestimación (auditoría 2026-09-15):** la premisa de que los tests ya no usan los nombres no se cumple: quedan
> **245 literales de rol en 38 archivos de test** (`assignRole('produccion')` y similares) que hay que pasar a
> `userWithRole(SystemRole::X)`. Además, el paso incluye retirar `UserRole` de `resources/js/types/auth.ts` y la prop
> compartida `role_names`, que ya nadie consume. Los seeders ya usan `SystemRole` (lote A3). **Estimación: 1,5 días.**
>
> **Recuento (2026-09-18):** de esos 245, solo **133 en 38 archivos** son nombres en español (`'produccion'`,
> `'operador'`, `'comercial'`); el resto son `'admin'` y `'super-admin'`, que no cambian de nombre. Fuera de los tests,
> los nombres en español solo quedan en `SystemRole` y en el tipo `UserRole` de `resources/js/types/auth.ts`.
> **Estimación: 1 día.**

> **✅ Estado paso 11 (2026-09-18, rama `feature/rbac-role-rename`):** implementado.
> - `SystemRole` en inglés (`production`, `operator`, `commercial`); las etiquetas visibles no cambian.
> - **Sin migración de datos:** el software aún no está en producción, así que las bases se recrean con
>   `migrate:fresh --seed` y el seeder crea los roles ya en inglés. Desde producción, cualquier renombrado de datos
>   existentes necesita su migración (el seeder crea filas nuevas, no renombra, y los usuarios se enlazan por `role_id`).
> - Los 133 literales de los tests pasan a `SystemRole::X->value`.
> - Retirados el tipo `UserRole` (TS), `User.role`/`User.roles`/`UserRoleRecord` sin uso y la prop compartida
>   `role_names` (**AU-08**, **RV-11**).
> - La descripción del log `role_changed` y la confirmación de `users:grant-super-admin` muestran la etiqueta del rol
>   ("de Operador a Administrador"); `properties.old_role/new_role` guardan el nombre interno. `SystemRole::labelFor()`
>   acepta `null` ("Sin rol") y sustituye al centinela `'none'`.
>
> **Revisión de la rama: la lógica deja de decidir por nombre de rol.** Solo quedan dos consultas por rol, ambas de
> SuperAdmin y ambas en `User` (`isSuperAdmin()` y el scope `superAdmins()`); un test lo exige ("no decide por nombre
> de rol fuera de User"). Cambios:
> - **Regla general contra la escalada (`User::holdsAllPermissions`)**: nadie edita, desactiva ni elimina a un usuario
>   con permisos que él no tiene (`UserPolicy`), y nadie asigna un rol con permisos que él no tiene
>   (`AssignableRoleService`, que ya no necesita casos especiales para SuperAdmin). Sustituye a "solo un SuperAdmin
>   gestiona a otro SuperAdmin" y cubre además a la Admin frente a un rol personalizado con `users.edit`.
> - **Se retira la protección "último Admin activo"**: protegía un nombre, no una capacidad. Queda solo "último
>   SuperAdmin activo", que evita quedarse sin acceso de recuperación.
> - **Se retira "no se elimina a un Admin ni a un SuperAdmin"**: nadie se elimina a sí mismo (siempre queda un
>   SuperAdmin) y `hasActivity()` ya bloquea el borrado de quien trabajó en el sistema.
> - **Se retira la excepción "conservar el rol actual"** al editar (`UserController::edit`, `UpdateUserRequest`): con la
>   regla general, quien puede editar a un usuario tiene todos sus permisos y, por tanto, puede asignar su rol.
> - **Sin rol preseleccionado** al crear un usuario (antes, Producción).
> - `SystemRole::reservedNames()` pasa a `isReservedLabel()`: reserva solo las etiquetas, sin distinguir mayúsculas ni
>   tildes (rechaza `produccion` junto a `Producción`). Los nombres internos ya los rechaza la validación de nombre
>   repetido, porque los roles del sistema existen antes que cualquier personalizado.
> - **Acciones por fila en el listado de usuarios** (`can.update` / `can.delete`, como en roles): la tabla ya no ofrece
>   editar o eliminar a quien la policy protege. Un test garantiza que Admin tiene todos los permisos no reservados,
>   que es lo que le deja gestionar a cualquier usuario salvo a los SuperAdmin.
> - **"Último SuperAdmin activo" dentro de la transacción**, con el rol `super-admin` bloqueado: dos SuperAdmins que se
>   desactivan a la vez ya no pueden dejar el sistema sin ninguno. Al editar sin cambiar el rol ya no se bloquea ni se
>   resincroniza. `destroy` aplica la misma regla y el mismo bloqueo (hallazgo de CodeRabbit en la PR #147): hasta
>   ahora lo impedía solo `hasActivity()`, porque iniciar sesión deja actividad, pero el log se purga a los 180 días.

**Regla desde ya:** el código nuevo nunca escribe el nombre de un rol a mano; siempre `SystemRole::X->value`.

Dos cambios de orden importantes frente a tu plan:
- **2.9 antes de 2.2** (sin permisos sembrados, migrar policies deja todo en 403).
- **2.5 antes de 2.4** (2.5 lleva los permisos al frontend; sin eso la UI de roles se construye a ciegas).

Tu estimación era de 8 días (días 6-13). El alcance real, incluyendo lo que la matriz no cubría, está en **≈20 días**. **✅ Decidido: la 2.4 (CRUD de roles) entra antes de producción.** Probar por primera vez la gestión de roles con datos reales sería más arriesgado que pulirla después; lo que falte se ajusta ya en producción.

---

## 6. Estado de las decisiones

| # | Decisión | Estado |
| --- | --- | --- |
| C1 | Estrategia de super-admin | ✅ Opción A: todos los permisos por seeder, sin `Gate::before` |
| C3 | Mono o multi-rol | ✅ Un rol por usuario, sin permisos directos (se pueden activar después sin migración) |
| C4 | Producción pierde fórmulas y creación/edición de productos | ✅ Intencional: Admin es la jefa de producción |
| — | Auto-eliminación de cuenta | ✅ Se elimina la función |
| — | SoftDeletes en `InventoryMovement` / `InventoryBatch` | ✅ No: libro mayor inmutable; los errores se corrigen con un movimiento contrario manual y una nota |
| — | Orden, pedido y cotización eliminables | ✅ No: se cancelan por estado |
| — | Desarrollo de pinturas | ✅ Solo Admin (y Comercial, las suyas). Producción y Operador sin acceso |
| — | Comercial en órdenes de producción | ✅ Sin acceso: lo cubre el estado del pedido (`MATRIZ_RBAC.md` §7.3) |
| — | `sales_orders.update_status` para Producción | ✅ Sí |
| — | Nomenclatura en módulos con dueño | ✅ `view_own` / `view_all` (no existe `view` a secas) |
| — | 2.4 (CRUD de roles) | ✅ Entra antes de producción |
| — | ¿La auditoría impide el borrado físico? (2.7) | ✅ No como historial; sí como autor (usuarios) |
| — | Roles base en código o editables en la UI | ✅ En código (`SystemRole` + `Permission::defaultRoles()`), reconciliados por el seeder; los casos especiales son roles personalizados (2.4). La lógica decide solo por permisos. Revisar si algún día la empresa necesita cambiar los permisos de un rol base sin desplegar |
| — | Soft deletes (2.6) | ✅ No se usan: desactivar + eliminar solo sin historial; claves foráneas `RESTRICT` hacia historial (`POLITICA_ELIMINACION.md`) |
| — | Clientes desactivables | ✅ Sí: columna `is_active` y permiso nuevo `clients.deactivate` (Admin); `clients.delete` solo sin historial |
| — | Protecciones de usuarios | ✅ Por permisos (`holdsAllPermissions`); la única regla por rol es "último SuperAdmin activo" |
