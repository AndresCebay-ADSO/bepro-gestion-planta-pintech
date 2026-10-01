# Politica De Costos De Materia Prima

## Objetivo
Unificar la regla de costo de referencia para evitar diferencias entre:
- costo teorico de formulas (`raw_materials.current_price`)
- costo real de consumo en OP (FIFO por lotes)

## Que Se Implemento
Se agrego una politica configurable para actualizar `raw_materials.current_price` usando la informacion real de lotes.

### Archivos principales
- [RawMaterialReferencePriceService.php](/Users/pintech.it/proyecto/Pintech-OS-Estable/app/Services/RawMaterialReferencePriceService.php)
- [InventoryService.php](/Users/pintech.it/proyecto/Pintech-OS-Estable/app/Services/InventoryService.php)
- [InventoryBatchSeeder.php](/Users/pintech.it/proyecto/Pintech-OS-Estable/database/seeders/InventoryBatchSeeder.php)
- [production.php](/Users/pintech.it/proyecto/Pintech-OS-Estable/config/production.php)

## Politicas disponibles
Se define en `config/production.php`:

```php
'raw_material_reference_price_policy' => env('RAW_MATERIAL_REFERENCE_PRICE_POLICY', 'conservative_max')
```

Valores permitidos:
1. `conservative_max` (default)
2. `weighted_average`
3. `last_lot`

## Regla de cada politica
1. `conservative_max`:
- Toma el maximo entre:
  - precio actual de materia prima
  - precio del ultimo lote
  - promedio ponderado del stock disponible
  - precio mas alto de lote disponible
- Uso recomendado cuando negocio prioriza no perder margen por lotes caros.

2. `weighted_average`:
- Usa promedio ponderado del stock disponible.
- Si no hay stock, cae a ultimo lote o precio actual.

3. `last_lot`:
- Usa el ultimo lote ingresado.
- Si no existe, cae a ponderado o precio actual.

## Cuando se recalcula el precio de referencia
Ahora se sincroniza automaticamente en:
1. registro de movimientos de inventario
2. edicion de movimientos de inventario
3. eliminacion de movimientos de inventario
4. seeder de lotes (`InventoryBatchSeeder`)

## Impacto en costos de produccion
Cuando cambia `raw_materials.current_price` por esta politica, se dispara
`ProductionCostRecalculationService::recalculateForRawMaterial`:
- los productos cuya fórmula activa usa la materia prima se recalculan completos (granel y presentaciones);
- las presentaciones que la usan como **envase o etiqueta** se recalculan solas, con el costo de granel que ya tiene su
  producto (`repriceVariantsUsingPackagingMaterial`). No se recalcula el granel, que no cambió, ni se deja un registro
  sin variación en su historial. Cubre también los productos sin fórmula activa, pero **no** los que no tienen costo de
  granel (`products.current_cost` vacío): sus presentaciones quedarían en envase + etiqueta, así que no se tocan.

Guardar una presentación la recalcula de la misma forma (`repriceVariantsOfProduct`), con la misma excepción.

Costo teórico de una presentación: `(granel × presentación + envase + etiqueta) × (1 + CIF)` (`VariantPricingService`).
El precio solo cambia si el costo varía más que el umbral del producto.

Esto no altera el costo real FIFO del cierre de OP.

## Materias primas sin control de inventario
Las que no se compran por lotes ni se cuentan (`tracks_inventory = false`: agua, etiquetas) no tienen compras de donde
sacar el precio. Su `current_price` se escribe a mano en el formulario de la materia prima, solo con el permiso
`costs.update`, y al cambiarlo se guarda el anterior en `previous_price` y se encola
`RecalculateRawMaterialDependentCosts`. La OP registra su consumo sin lote y lo costea a ese precio.

- El precio es **obligatorio**, aunque sea 0 escrito a propósito, y no se puede borrar: vacío, los costos lo tomarían
  como 0 sin que nadie lo decidiera. Por eso solo quien tiene `costs.update` crea materias primas sin control de
  inventario o cambia el control de una, en cualquier sentido.
- Activar el control de una materia prima que usan OP abiertas pide confirmación: desde ahí necesita saldo y esas OP no
  se podrán completar hasta registrar sus compras.
- Al cambiar el control se reevalúa la alerta de stock bajo: sin control no hay alerta (la abierta se resuelve).
- No admiten movimientos manuales (compras ni salidas): un lote suyo nunca se descontaría.
- El cambio dispara la misma alerta de variación de precio que las compras, si la materia prima tiene umbral.
- El recálculo desde lotes nunca toca su precio, aunque tenga lotes (una compra registrada por error o de cuando sí
  controlaba inventario).
- Una materia prima con control de inventario no acepta precio manual.
- No se puede quitar el control de inventario con saldo en bodega (quedaría congelado); activarlo siempre se puede, y
  desde ese momento el precio sale de sus lotes (si ya tiene, se recalcula al activarlo).

## Como cambiar la politica mas adelante
1. Definir variable en `.env`:

```env
RAW_MATERIAL_REFERENCE_PRICE_POLICY=weighted_average
```

2. Limpiar cache de config:

```bash
php artisan config:clear
```

## Recomendacion operativa
Si deseas modo conservador (criterio de gerencia actual), mantener `conservative_max`.
Si en el futuro desean precios mas cercanos al costo medio real del inventario vivo, migrar a `weighted_average`.
