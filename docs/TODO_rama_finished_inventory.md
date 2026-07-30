# TODO: Rama feature/finished-inventory-pivot

Este documento es un registro de deuda técnica, mejoras potenciales y tareas pendientes identificadas durante las revisiones de código de esta rama.

## 1. Refactorización de Form Requests (Finished Inventory Movements)
**Archivos involucrados:**
- `app/Http/Requests/FinishedInventory/StoreFinishedInventoryMovementRequest.php`
- `app/Http/Requests/FinishedInventory/UpdateFinishedInventoryMovementRequest.php`

**Descripción del problema:**
Existe código duplicado entre las validaciones de creación y actualización de movimientos de inventario terminado. Específicamente, el método `validateBatchBelongsToWarehouse` está copiado y pegado en ambos archivos. Las reglas base (`quantity`, `movement_date`, `warehouse_id`) también se repiten, y la validación cruzada entre `type` y `reason` tiene lógica redundante.

**Posibles Soluciones (A evaluar más adelante):**
1. **(Recomendado) Crear Custom Rules:** Extraer las validaciones pesadas (como verificar si el lote tiene stock en la bodega) a clases de regla independientes (ej. `BatchHasAvailableStockRule`). Esto limpiaría el bloque `after()` y haría la regla testeable unitariamente.
2. **Clase Base:** Crear un `BaseFinishedInventoryMovementRequest` del cual hereden ambos Requests para compartir métodos comunes.
3. **Trait:** Aislar los métodos de validación en un Trait compartido.

## 2. Refactorización de FinishedInventoryMovementController
**Archivos involucrados:**
- `app/Http/Controllers/Inventory/FinishedInventoryMovementController.php`

**Descripción del problema y Soluciones (A evaluar más adelante):**
1. **Fuga de datos (Data Leakage) en `edit()`:** Actualmente, el método `edit` pasa colecciones de Eloquent crudas (`FinishedProductBatch::query()->get()`) directo a Inertia/React, exponiendo toda la estructura interna de la base de datos (fechas de creación, columnas sensibles, etc.).
   - **Solución:** Implementar **API Resources** (ej. `FinishedProductBatchResource::collection(...)`) tanto en `index` como en `edit` para estandarizar exactamente qué datos viajan al cliente y proteger la base de datos.
2. **Repetición y código "PHP puro" en el parseo de fechas en `store()`:** El código `new \DateTimeImmutable($validated['movement_date'])` se repite en tres bloques condicionales distintos.
   - **Solución:** Extraerlo al inicio del método aprovechando las facilidades de Laravel: `$movementDate = $request->date('movement_date');`, lo que limpia los bloques condicionales y usa la abstracción nativa (Carbon).

## 3. Refactorización de CompleteProductionOrderAction (God Class)
**Archivos involucrados:**
- `app/Actions/Production/CompleteProductionOrderAction.php`

**Descripción del problema y Soluciones (A evaluar más adelante):**
La clase actualmente actúa como una "God Class" (hace demasiadas cosas: valida estados, calcula costos, consume materias primas, crea lotes, registra movimientos y despacha Jobs). Además, corre todas estas operaciones dentro de una única transacción de base de datos gigante que puede ser pesada.
- **Soluciones sugeridas:**
  1. **Separación de Responsabilidades:** Extraer la lógica de cálculo financiero (costos y mermas) a un Action separado (ej. `CalculateProductionFinancialsAction`).
  2. **Eventos (Pub/Sub):** Emitir un evento `OrderCompletedEvent` y mover las tareas secundarias (alertas de stock, certificados de calidad) a *Listeners* (síncronos o asíncronos) para aligerar la transacción principal.

## 4. Ampliar Cobertura de Pruebas (Tests)
**Archivos involucrados:**
- `tests/Feature/Inventory/FinishedProductBatchStockServiceTest.php`
- `tests/Feature/Inventory/FinishedInventoryMovementValidationTest.php`

**Descripción del problema y Soluciones:**
Existen escenarios clave o "Edge Cases" de la lógica de inventario que aún no están siendo cubiertos por los tests automatizados y que representan deuda técnica:
1. **Validaciones cruzadas de Traslados (Store):** Falta testear que el endpoint POST rechace intentos de mover mercancía si `warehouse_id` y `destination_warehouse_id` son iguales.
2. **Defensas contra números negativos:** Falta un test que confirme que `incrementStock` y `decrementStock` lanzan un `\DomainException` si se intenta inyectar un `$quantity <= 0`.
3. **Condiciones de Carrera (Race Conditions):** Escribir un test de concurrencia avanzado para confirmar empíricamente que el combo `lockForUpdate() -> createOrFirst()` funciona correctamente bajo estrés asíncrono.
