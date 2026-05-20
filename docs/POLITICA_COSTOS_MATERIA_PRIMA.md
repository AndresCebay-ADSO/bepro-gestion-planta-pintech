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
Cuando cambia `raw_materials.current_price` por esta politica, se dispara:
- recálculo de costos teoricos de productos afectados (`ProductionCostRecalculationService`)

Esto no altera el costo real FIFO del cierre de OP.

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
