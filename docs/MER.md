# MER / Diccionario de Datos - Pintech OS

Generado desde el esquema real de PostgreSQL 16 (`migrate:fresh`) el 2026-09-21: 48 tablas (sin `migrations`). Si cambias una
migración, actualiza este documento.

## 1. Contexto

- Motor: PostgreSQL 16 (los tests corren también en SQLite; el CI prueba ambos).
- ORM: Eloquent (Laravel 13). Convenciones: `snake_case`, PK `bigIncrements`, FK con `foreignId`, timestamps estándar.
- **Sin soft deletes:** ninguna tabla tiene `deleted_at`. Los datos maestros se desactivan (`is_active`) y solo se
  eliminan si nunca se usaron; los documentos se cancelan por estado; los movimientos son inmutables. Ver
  `POLITICA_ELIMINACION.md`.
- **Reglas de borrado de las claves foráneas:** `RESTRICT` hacia todo lo que es historial (la base de datos impide
  borrar lo que se usó); `CASCADE` solo en hijos sin valor propio; ningún `SET NULL`. Se indican en cada columna.
- Dinero y cantidades: `DECIMAL`, calculados siempre con `DecimalCalculator` (bcmath).

## 2. Entidades de negocio

## 2.1 Catálogos

### 2.1.1 `unit_of_measures`

Catálogo de unidades de medida.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `code` | VARCHAR(20) |  | UNIQUE |
| `name` | VARCHAR(100) |  |  |
| `symbol` | VARCHAR(10) |  |  |
| `description` | TEXT | sí |  |
| `to_kg_conversion` | DECIMAL(10,4) | sí |  |
| `to_liter_conversion` | DECIMAL(10,4) | sí |  |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.1.2 `product_categories`

Categorías de productos.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `name` | VARCHAR(100) |  | UNIQUE |
| `description` | TEXT | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.1.3 `raw_material_categories`

Categorías de materias primas (envases, pigmentos…).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `code` | VARCHAR(50) |  | UNIQUE |
| `name` | VARCHAR(100) |  |  |
| `description` | TEXT | sí |  |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.2 Bodegas

### 2.2.1 `warehouses`

Bodegas (fábrica y almacenamiento).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `name` | VARCHAR(100) |  | UNIQUE |
| `city` | VARCHAR(100) |  |  |
| `address` | VARCHAR(255) | sí |  |
| `type` | VARCHAR(255) |  | default `storage` |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.2.2 `warehouse_user`

Bodegas asignadas a cada usuario.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `user_id` | BIGINT |  | FK → `users` (CASCADE) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (CASCADE) |
| `is_default` | BOOLEAN |  | default `false` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `UNIQUE (user_id, warehouse_id)`

## 2.3 Materia prima

### 2.3.1 `raw_materials`

Materias primas. **Sin columna `name`**: se identifican solo por `code` (privacidad de fórmulas).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `code` | VARCHAR(50) |  | UNIQUE |
| `unit_of_measure_id` | BIGINT |  | FK → `unit_of_measures` (RESTRICT) |
| `current_price` | DECIMAL(12,4) | sí |  |
| `previous_price` | DECIMAL(12,4) | sí |  |
| `minimum_stock` | DECIMAL(12,4) |  | default `0` |
| `alert_days_before_expiry` | INTEGER |  | default `30` |
| `tracks_inventory` | BOOLEAN |  | default `true` |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `category_id` | BIGINT | sí | FK → `raw_material_categories` (RESTRICT) |
| `price_variation_threshold` | DECIMAL(8,2) | sí |  |

### 2.3.2 `inventory_batches`

Lotes de materia prima (base del costeo FIFO).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `raw_material_id` | BIGINT |  | FK → `raw_materials` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `initial_quantity` | DECIMAL(12,4) |  |  |
| `remaining_quantity` | DECIMAL(12,4) |  |  |
| `unit_price` | DECIMAL(12,4) |  |  |
| `entry_date` | DATE |  |  |
| `expiry_date` | DATE | sí |  |
| `supplier` | VARCHAR(150) | sí |  |
| `lot_number` | VARCHAR(50) | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `((unit_price >= (0)::numeric))` · `((initial_quantity >= (0)::numeric))` · `((remaining_quantity >= (0)::numeric))` · `((remaining_quantity <= initial_quantity))`

### 2.3.3 `inventory_movements`

Movimientos de materia prima (libro contable: inmutable).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `raw_material_id` | BIGINT |  | FK → `raw_materials` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `batch_id` | BIGINT | sí | FK → `inventory_batches` (RESTRICT) |
| `production_order_id` | BIGINT | sí | FK → `production_orders` (RESTRICT) |
| `type` | VARCHAR(255) |  |  |
| `quantity` | DECIMAL(12,4) |  |  |
| `cost_price` | DECIMAL(12,4) |  |  |
| `movement_date` | DATE |  |  |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `((quantity > (0)::numeric))` · `((cost_price >= (0)::numeric))`

## 2.4 Productos y fórmulas

### 2.4.1 `products`

Productos terminados.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `code` | VARCHAR(50) | sí | UNIQUE |
| `name` | VARCHAR(150) |  |  |
| `brand` | VARCHAR(100) |  | default `BEPRO` |
| `description` | TEXT | sí |  |
| `category_id` | BIGINT |  | FK → `product_categories` (RESTRICT) |
| `unit_of_measure_id` | BIGINT |  | FK → `unit_of_measures` (RESTRICT) |
| `current_cost` | DECIMAL(12,4) | sí |  |
| `cif_percentage` | DECIMAL(5,2) | sí |  |
| `sales_margin` | DECIMAL(5,2) | sí |  |
| `current_price` | DECIMAL(12,4) | sí |  |
| `price_threshold` | DECIMAL(5,2) |  | default `3` |
| `quality_viscosity_lower` | DECIMAL(4,1) | sí |  |
| `quality_viscosity_upper` | DECIMAL(4,1) | sí |  |
| `quality_fineness_lower` | DECIMAL(4,1) | sí |  |
| `quality_fineness_upper` | DECIMAL(4,1) | sí |  |
| `quality_solids_lower` | DECIMAL(4,1) | sí |  |
| `quality_solids_upper` | DECIMAL(4,1) | sí |  |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.4.2 `product_variants`

Presentaciones (SKU) de cada producto.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `code` | VARCHAR(80) |  | UNIQUE |
| `name` | VARCHAR(100) |  |  |
| `unit_of_measure_id` | BIGINT |  | FK → `unit_of_measures` (RESTRICT) |
| `presentation_value` | DECIMAL(12,4) | sí |  |
| `presentation_label` | VARCHAR(50) | sí |  |
| `current_cost` | DECIMAL(12,4) | sí |  |
| `current_price` | DECIMAL(12,4) | sí |  |
| `package_raw_material_id` | BIGINT | sí | FK → `raw_materials` (RESTRICT) |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.4.3 `product_documents`

Fichas técnicas y hojas de seguridad por producto.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `document_type` | VARCHAR(255) |  |  |
| `file_name` | VARCHAR(255) |  |  |
| `file_path` | VARCHAR(500) |  |  |
| `file_size` | BIGINT |  | default `0` |
| `mime_type` | VARCHAR(100) |  | default `application/pdf` |
| `version` | INTEGER |  | default `1` |
| `is_current` | BOOLEAN |  | default `true` |
| `uploaded_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.4.4 `formulas`

Versiones de fórmula por producto (una activa).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `version` | INTEGER |  | default `1` |
| `is_active` | BOOLEAN |  | default `true` |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.4.5 `formula_details`

Ingredientes de cada fórmula.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `formula_id` | BIGINT |  | FK → `formulas` (CASCADE) |
| `raw_material_id` | BIGINT |  | FK → `raw_materials` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `unit_of_measure_id` | BIGINT |  | FK → `unit_of_measures` (RESTRICT) |
| `step_order` | SMALLINT |  | default `0` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.5 Producción

### 2.5.1 `production_orders`

Órdenes de producción (OP-YYYY-XXXX).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `order_number` | VARCHAR(20) |  | UNIQUE |
| `lot_number` | INTEGER | sí |  |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `formula_id` | BIGINT |  | FK → `formulas` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `actual_quantity` | DECIMAL(12,4) | sí |  |
| `yield_real_quantity` | DECIMAL(12,4) | sí |  |
| `yield_theoretical_quantity` | DECIMAL(12,4) | sí |  |
| `yield_variance_quantity` | DECIMAL(12,4) | sí |  |
| `yield_percentage` | DECIMAL(5,2) | sí |  |
| `status` | VARCHAR(255) |  | default `pending` |
| `planned_date` | DATE |  |  |
| `completion_date` | DATE | sí |  |
| `notes` | TEXT | sí |  |
| `agitation_start_time` | TIMESTAMP | sí |  |
| `agitation_end_time` | TIMESTAMP | sí |  |
| `viscosity_ku` | DECIMAL(8,2) | sí |  |
| `grinding_hg` | DECIMAL(8,2) | sí |  |
| `quality_solids` | DECIMAL(5,2) | sí |  |
| `responsible_name` | VARCHAR(150) | sí |  |
| `packaging_start_time` | TIMESTAMP | sí |  |
| `packaging_end_time` | TIMESTAMP | sí |  |
| `spillage_quantity` | DECIMAL(12,4) |  | default `0` |
| `density_kg_per_gallon` | DECIMAL(10,4) | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `submitted_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `submitted_at` | TIMESTAMP | sí |  |
| `reviewed_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `reviewed_at` | TIMESTAMP | sí |  |
| `rejection_reason` | TEXT | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `quality_responsible_user_id` | BIGINT | sí | FK → `users` (RESTRICT) |

### 2.5.2 `production_order_details`

Consumo de materia prima por lote en cada orden.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `production_order_id` | BIGINT |  | FK → `production_orders` (CASCADE) |
| `batch_id` | BIGINT | sí | FK → `inventory_batches` (RESTRICT) |
| `raw_material_id` | BIGINT |  | FK → `raw_materials` (RESTRICT) |
| `planned_quantity` | DECIMAL(12,4) |  |  |
| `actual_quantity` | DECIMAL(12,4) | sí |  |
| `unit_cost` | DECIMAL(12,4) |  |  |
| `total_cost` | DECIMAL(12,4) |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `step_order` | SMALLINT |  | default `0` |

### 2.5.3 `production_order_line_adjustments`

Ajustes de línea registrados durante la orden.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `production_order_id` | BIGINT |  | FK → `production_orders` (CASCADE) |
| `raw_material_id` | BIGINT |  | FK → `raw_materials` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `reason` | VARCHAR(500) |  |  |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.5.4 `production_order_packaging_plan`

Plan de envasado por presentación.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `production_order_id` | BIGINT |  | FK → `production_orders` (CASCADE) |
| `product_variant_id` | BIGINT |  | FK → `product_variants` (RESTRICT) |
| `planned_units` | DECIMAL(12,4) |  |  |
| `actual_units` | DECIMAL(12,4) | sí |  |
| `notes` | TEXT | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.5.5 `production_remnants`

Saldos de producción sobrantes de una orden.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `source_order_id` | BIGINT |  | FK → `production_orders` (RESTRICT); UNIQUE |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `original_quantity_gallons` | DECIMAL(12,4) |  |  |
| `original_quantity_kg` | DECIMAL(12,4) |  |  |
| `available_quantity_gallons` | DECIMAL(12,4) |  |  |
| `available_quantity_kg` | DECIMAL(12,4) |  |  |
| `density_kg_per_gallon` | DECIMAL(10,4) |  |  |
| `cost_per_gallon` | DECIMAL(12,4) | sí |  |
| `status` | VARCHAR(255) |  | default `available` |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.5.6 `remnant_consumptions`

Consumo de saldos en otras órdenes.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `remnant_id` | BIGINT |  | FK → `production_remnants` (RESTRICT) |
| `target_order_id` | BIGINT | sí | FK → `production_orders` (RESTRICT) |
| `consumed_cost` | DECIMAL(16,4) | sí |  |
| `quantity_gallons` | DECIMAL(12,4) |  |  |
| `quantity_kg` | DECIMAL(12,4) |  |  |
| `consumed_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `consumed_at` | TIMESTAMP |  |  |
| `notes` | TEXT | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.5.7 `production_costs`

Historial de costos calculados.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `formula_id` | BIGINT |  | FK → `formulas` (RESTRICT) |
| `cost` | DECIMAL(12,4) |  |  |
| `variation_percentage` | DECIMAL(8,4) | sí |  |
| `calculated_at` | TIMESTAMP |  | default `CURRENT_TIMESTAMP` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `production_order_id` | BIGINT | sí | FK → `production_orders` (RESTRICT); UNIQUE |
| `unit_cost` | DECIMAL(12,4) | sí |  |

### 2.5.8 `price_lists`

Historial de precios.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `price` | DECIMAL(12,4) |  |  |
| `cost_at_time` | DECIMAL(12,4) |  |  |
| `profit_margin` | DECIMAL(5,2) |  |  |
| `update_type` | VARCHAR(255) |  |  |
| `variation_percentage` | DECIMAL(8,4) | sí |  |
| `valid_from` | DATE |  |  |
| `valid_to` | DATE | sí |  |
| `created_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.6 Producto terminado

### 2.6.1 `finished_inventories`

Stock de producto terminado por presentación y bodega (saldo calculado).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  | default `0` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `UNIQUE (product_id, product_variant_id, warehouse_id)`

### 2.6.2 `finished_product_batches`

Lotes de producto terminado.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `production_order_id` | BIGINT | sí | FK → `production_orders` (RESTRICT) |
| `initial_quantity` | DECIMAL(12,4) |  |  |
| `entry_date` | DATE |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.6.3 `finished_product_batch_stocks`

Stock de cada lote de producto terminado por bodega.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `finished_product_batch_id` | BIGINT |  | FK → `finished_product_batches` (CASCADE) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `UNIQUE (finished_product_batch_id, warehouse_id)`

### 2.6.4 `finished_inventory_movements`

Movimientos de producto terminado (libro contable: inmutable).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `production_order_id` | BIGINT | sí | FK → `production_orders` (RESTRICT) |
| `type` | VARCHAR(255) |  |  |
| `reason` | VARCHAR(50) |  |  |
| `quantity` | DECIMAL(12,4) |  |  |
| `movement_date` | DATE |  |  |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `cost_price` | DECIMAL(12,4) | sí |  |
| `finished_product_batch_id` | BIGINT | sí | FK → `finished_product_batches` (RESTRICT) |

### 2.6.5 `transfers`

Traslados de producto terminado entre bodegas.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `source_warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `destination_warehouse_id` | BIGINT |  | FK → `warehouses` (RESTRICT) |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `status` | VARCHAR(255) |  | default `pending` |
| `notes` | TEXT | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `sent_at` | TIMESTAMP | sí |  |
| `received_at` | TIMESTAMP | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.7 QR

### 2.7.1 `qr_codes`

QR por orden de producción para la landing pública.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `production_order_id` | BIGINT |  | FK → `production_orders` (CASCADE); UNIQUE |
| `token` | VARCHAR(100) |  | UNIQUE |
| `url` | VARCHAR(500) |  |  |
| `is_active` | BOOLEAN |  | default `true` |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.7.2 `qr_documents`

Documentos asociados al QR.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `qr_code_id` | BIGINT |  | FK → `qr_codes` (CASCADE) |
| `document_type` | VARCHAR(255) |  |  |
| `file_name` | VARCHAR(255) |  |  |
| `file_path` | VARCHAR(500) |  |  |
| `file_size` | BIGINT |  | default `0` |
| `mime_type` | VARCHAR(100) |  | default `application/pdf` |
| `version` | INTEGER |  | default `1` |
| `is_current` | BOOLEAN |  | default `true` |
| `uploaded_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.8 Comercial

### 2.8.1 `clients`

Clientes.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `business_name` | VARCHAR(255) |  |  |
| `nit` | VARCHAR(255) | sí |  |
| `contact_name` | VARCHAR(255) | sí |  |
| `phone` | VARCHAR(255) | sí |  |
| `shipping_address` | VARCHAR(255) | sí |  |
| `is_active` | BOOLEAN |  | default `true` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.8.2 `quotations`

Cotizaciones (COT-YYYY-XXXX). Se cancelan por estado, nunca se eliminan.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `client_id` | BIGINT |  | FK → `clients` (RESTRICT) |
| `client_business_name` | VARCHAR(255) | sí |  |
| `client_nit` | VARCHAR(255) | sí |  |
| `client_contact_name` | VARCHAR(255) | sí |  |
| `client_phone` | VARCHAR(255) | sí |  |
| `quotation_number` | INTEGER |  | UNIQUE |
| `technology` | VARCHAR(255) | sí |  |
| `line` | VARCHAR(255) | sí |  |
| `thickness_mils` | VARCHAR(255) | sí |  |
| `application_method` | VARCHAR(255) | sí |  |
| `quotation_date` | DATE | sí |  |
| `validity_days` | SMALLINT | sí |  |
| `payment_method` | VARCHAR(255) | sí |  |
| `delivery_time` | VARCHAR(255) | sí |  |
| `area` | VARCHAR(255) | sí |  |
| `notes` | TEXT | sí |  |
| `subtotal` | DECIMAL(16,4) |  |  |
| `iva_percentage` | DECIMAL(5,2) |  |  |
| `iva_amount` | DECIMAL(16,4) |  |  |
| `total` | DECIMAL(16,4) |  |  |
| `status` | VARCHAR(20) |  | default `draft` |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `convert_to_order_id` | BIGINT | sí | FK → `sales_orders` (RESTRICT); UNIQUE |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.8.3 `quotation_items`

Ítems de cada cotización.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `quotation_id` | BIGINT |  | FK → `quotations` (RESTRICT) |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT |  | FK → `product_variants` (RESTRICT) |
| `type` | VARCHAR(20) | sí |  |
| `description` | VARCHAR(255) | sí |  |
| `color` | VARCHAR(100) | sí |  |
| `quantity` | DECIMAL(12,4) |  |  |
| `list_unit_price` | DECIMAL(16,4) |  |  |
| `price_adjustment_pct` | DECIMAL(8,4) |  | default `0` |
| `unit_price` | DECIMAL(16,4) |  |  |
| `subtotal` | DECIMAL(16,4) |  |  |
| `sort_order` | INTEGER |  | default `0` |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.8.4 `sales_orders`

Pedidos de venta. Se cancelan por estado, nunca se eliminan.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `client_id` | BIGINT |  | FK → `clients` (RESTRICT) |
| `status` | VARCHAR(255) |  | default `pending` |
| `required_date` | DATE | sí |  |
| `estimated_delivery_date` | DATE | sí |  |
| `notes` | TEXT | sí |  |
| `shipping_address` | TEXT | sí |  |
| `client_business_name` | VARCHAR(255) | sí |  |
| `client_nit` | VARCHAR(255) | sí |  |
| `client_contact_name` | VARCHAR(255) | sí |  |
| `client_phone` | VARCHAR(255) | sí |  |
| `priority` | VARCHAR(255) |  | default `medium` |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |
| `quotation_id` | BIGINT | sí | FK → `quotations` (RESTRICT); UNIQUE |

### 2.8.5 `sales_order_items`

Ítems de cada pedido.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `sales_order_id` | BIGINT |  | FK → `sales_orders` (RESTRICT) |
| `product_id` | BIGINT |  | FK → `products` (RESTRICT) |
| `product_variant_id` | BIGINT | sí | FK → `product_variants` (RESTRICT) |
| `quantity` | DECIMAL(12,4) |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 2.8.6 `paint_development_requests`

Solicitudes de desarrollo de pintura.

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `request_number` | INTEGER |  | UNIQUE |
| `status` | VARCHAR(20) |  | default `draft` |
| `client_name` | VARCHAR(255) |  |  |
| `project_name` | VARCHAR(255) |  |  |
| `responsible` | VARCHAR(255) |  |  |
| `city` | VARCHAR(255) |  |  |
| `sample_due_date` | DATE |  |  |
| `current_product` | VARCHAR(255) | sí |  |
| `context_payload` | JSONB | sí |  |
| `performance_payload` | JSONB | sí |  |
| `application_payload` | JSONB | sí |  |
| `specifications_payload` | JSONB | sí |  |
| `schema_version` | SMALLINT |  | default `1` |
| `review_notes` | TEXT | sí |  |
| `reviewed_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `reviewed_at` | TIMESTAMP | sí |  |
| `created_by` | BIGINT |  | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 2.9 Alertas

### 2.9.1 `alerts`

Alertas (visibles según el permiso de su tipo).

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `type` | VARCHAR(50) |  |  |
| `raw_material_id` | BIGINT | sí | FK → `raw_materials` (CASCADE) |
| `batch_id` | BIGINT | sí | FK → `inventory_batches` (CASCADE) |
| `severity` | VARCHAR(255) |  | default `media` |
| `message` | TEXT |  |  |
| `is_resolved` | BOOLEAN |  | default `false` |
| `resolved_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `resolved_at` | TIMESTAMP | sí |  |
| `updated_by` | BIGINT | sí | FK → `users` (RESTRICT) |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

## 3. Autenticación, auditoría, permisos e infraestructura

Tablas de Laravel y de paquetes (Fortify, Spatie Activity Log, Spatie Permission, cola y caché).

### 3.1 `users`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `name` | VARCHAR(255) |  |  |
| `email` | VARCHAR(255) |  | UNIQUE |
| `phone` | VARCHAR(255) | sí |  |
| `job_title` | VARCHAR(255) | sí |  |
| `email_verified_at` | TIMESTAMP | sí |  |
| `password` | VARCHAR(255) |  |  |
| `is_active` | BOOLEAN |  | default `true` |
| `signature_path` | VARCHAR(255) | sí |  |
| `last_login_at` | TIMESTAMP | sí |  |
| `remember_token` | VARCHAR(100) | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 3.2 `password_reset_tokens`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `email` | VARCHAR(255) |  |  |
| `token` | VARCHAR(255) |  |  |
| `created_at` | TIMESTAMP | sí |  |

### 3.3 `activity_logs`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `log_name` | VARCHAR(255) | sí |  |
| `description` | TEXT |  |  |
| `subject_type` | VARCHAR(255) | sí |  |
| `subject_id` | BIGINT | sí |  |
| `event` | VARCHAR(255) | sí |  |
| `causer_type` | VARCHAR(255) | sí |  |
| `causer_id` | BIGINT | sí |  |
| `attribute_changes` | JSON | sí |  |
| `properties` | JSON | sí |  |
| `batch_uuid` | UUID | sí |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

### 3.4 `cache`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `key` | VARCHAR(255) |  |  |
| `value` | TEXT |  |  |
| `expiration` | INTEGER |  |  |

### 3.5 `cache_locks`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `key` | VARCHAR(255) |  |  |
| `owner` | VARCHAR(255) |  |  |
| `expiration` | INTEGER |  |  |

### 3.6 `jobs`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `queue` | VARCHAR(255) |  |  |
| `payload` | TEXT |  |  |
| `attempts` | SMALLINT |  |  |
| `reserved_at` | INTEGER | sí |  |
| `available_at` | INTEGER |  |  |
| `created_at` | INTEGER |  |  |

### 3.7 `job_batches`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | VARCHAR(255) |  | PK |
| `name` | VARCHAR(255) |  |  |
| `total_jobs` | INTEGER |  |  |
| `pending_jobs` | INTEGER |  |  |
| `failed_jobs` | INTEGER |  |  |
| `failed_job_ids` | TEXT |  |  |
| `options` | TEXT | sí |  |
| `cancelled_at` | INTEGER | sí |  |
| `created_at` | INTEGER |  |  |
| `finished_at` | INTEGER | sí |  |

### 3.8 `failed_jobs`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `uuid` | VARCHAR(255) |  | UNIQUE |
| `connection` | TEXT |  |  |
| `queue` | TEXT |  |  |
| `payload` | TEXT |  |  |
| `exception` | TEXT |  |  |
| `failed_at` | TIMESTAMP |  | default `CURRENT_TIMESTAMP` |

### 3.9 `permissions`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `name` | VARCHAR(255) |  |  |
| `guard_name` | VARCHAR(255) |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `UNIQUE (name, guard_name)`

### 3.10 `roles`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `id` | BIGINT |  | PK |
| `name` | VARCHAR(255) |  |  |
| `guard_name` | VARCHAR(255) |  |  |
| `created_at` | TIMESTAMP | sí |  |
| `updated_at` | TIMESTAMP | sí |  |

Restricciones: `UNIQUE (name, guard_name)`

### 3.11 `model_has_permissions`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `permission_id` | BIGINT |  | FK → `permissions` (CASCADE) |
| `model_type` | VARCHAR(255) |  |  |
| `model_id` | BIGINT |  |  |

### 3.12 `model_has_roles`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `role_id` | BIGINT |  | FK → `roles` (CASCADE) |
| `model_type` | VARCHAR(255) |  |  |
| `model_id` | BIGINT |  |  |

### 3.13 `role_has_permissions`

| Columna | Tipo | Nulo | Notas |
| --- | --- | :-: | --- |
| `permission_id` | BIGINT |  | FK → `permissions` (CASCADE) |
| `role_id` | BIGINT |  | FK → `roles` (CASCADE) |

## 4. Enums PHP (`app/Enums/`)

Las columnas de estado y tipo se guardan como `VARCHAR` y el modelo las castea a su enum (con `label()` en español).
Algunas tienen además una restricción `CHECK` en PostgreSQL.

| Enum | Columna(s) | Valores |
| --- | --- | --- |
| `AlertType` | `alerts.type` | stock_bajo, vencimiento_proximo, variacion_precio, paint_development_request |
| `AlertSeverity` | `alerts.severity` | baja, media, alta |
| `WarehouseType` | `warehouses.type` | factory, storage |
| `InventoryMovementType` | `inventory_movements.type`, `finished_inventory_movements.type` | entry, exit |
| `FinishedInventoryMovementReason` | `finished_inventory_movements.reason` | production, return, adjustment, sale, sample, transfer, transformation, deterioration |
| `ProductionOrderStatus` | `production_orders.status` | pending, in_progress, pending_review, completed, cancelled |
| `RemnantStatus` | `production_remnants.status` | available, partially_consumed, consumed |
| `TransferStatus` | `transfers.status` | pending, sent, received, cancelled |
| `PriceUpdateType` | `price_lists.update_type` | manual, automatico |
| `QrDocumentType` | `qr_documents.document_type`, `product_documents.document_type` | technical_data_sheet, safety_data_sheet, quality_certificate |
| `QuotationStatus` | `quotations.status` | draft, sent, accepted, rejected |
| `QuotationValidity` | `quotations.validity_days` | 30, 45, 60 |
| `QuotationItemType` | `quotation_items.type` | primer, self_priming, topcoat, intermediate_coat, reducer |
| `PaymentMethod` | `quotations.payment_method` | cash, credit |
| `SalesOrderStatus` | `sales_orders.status` | pending, in_progress, ready, delivered, cancelled |
| `SalesOrderPriority` | `sales_orders.priority` | low, medium, high |
| `PaintDevelopmentRequestStatus` | `paint_development_requests.status` | draft, submitted, in_review, approved, rejected |

Enums sin columna propia: `SystemRole` (nombres de `roles.name` de los roles del sistema), `Permission` y
`PermissionModule` (catálogo de `permissions.name`, ver `MATRIZ_RBAC.md`) y `DashboardProfile` (vista del dashboard).

## 5. Notas de implementación

- Las migraciones originales se editan mientras no haya producción (las bases se recrean con `migrate:fresh --seed`).
  Desde producción, todo cambio de esquema va en una migración nueva.
- Consecutivos (`production_orders.order_number`, `quotations.quotation_number`) generados con
  `pg_advisory_xact_lock` en PostgreSQL.
- La autoría en `activity_logs` (`causer_type`/`causer_id`) es la única relación sin clave foránea.
- Todos los modelos usan el atributo `#[Fillable]` y anotaciones `@property`.
