# MER / Diccionario de Datos - Pintech OS

Documento actualizado al estado real de migraciones consolidadas (abril 2026).

## 1. Contexto

- Motor: PostgreSQL 16
- Framework ORM: Eloquent (Laravel)
- Convenciones: `snake_case`, PK `bigIncrements`, FK con `foreignId`, timestamps estandar

## 2. Entidades de negocio (dominio)

### 2.1 units_of_measure
Catalogo de unidades de medida.

- `id` BIGINT PK
- `code` VARCHAR(20) UNIQUE
- `name` VARCHAR(100)
- `symbol` VARCHAR(10)
- `description` TEXT NULL
- `to_kg_conversion` DECIMAL(10,4) NULL
- `to_liter_conversion` DECIMAL(10,4) NULL
- `is_active` BOOLEAN DEFAULT true
- `created_at`, `updated_at`
- `deleted_at` (soft delete)

### 2.2 product_categories
Categorias de productos.

- `id` BIGINT PK
- `name` VARCHAR(100) UNIQUE
- `description` TEXT NULL
- `created_at`, `updated_at`
- `deleted_at`

### 2.3 warehouses
Bodegas de producto terminado.

- `id` BIGINT PK
- `name` VARCHAR(100) UNIQUE
- `city` VARCHAR(100)
- `address` VARCHAR(255) NULL
- `is_active` BOOLEAN DEFAULT true
- `created_at`, `updated_at`
- `deleted_at`

### 2.4 raw_materials
Materias primas.

- `id` BIGINT PK
- `code` VARCHAR(50) UNIQUE
- `unit_of_measure_id` BIGINT FK -> `units_of_measure.id`
- `current_price` DECIMAL(12,4)
- `previous_price` DECIMAL(12,4) NULL
- `minimum_stock` DECIMAL(12,4) DEFAULT 0
- `alert_days_before_expiry` INT DEFAULT 30
- `is_active` BOOLEAN DEFAULT true
- `created_at`, `updated_at`
- `deleted_at`

Nota: La materia prima se identifica por un codigo interno unico (ej: AC4).

### 2.5 inventory_batches
Lotes de inventario de materia prima.

- `id` BIGINT PK
- `raw_material_id` BIGINT FK -> `raw_materials.id`
- `initial_quantity` DECIMAL(12,4)
- `remaining_quantity` DECIMAL(12,4)
- `unit_price` DECIMAL(12,4)
- `entry_date` DATE
- `expiry_date` DATE NULL
- `supplier` VARCHAR(150) NULL
- `lot_number` VARCHAR(50) NULL
- `created_at`, `updated_at`

### 2.6 products
Catalogo de productos terminados.

- `id` BIGINT PK
- `code` VARCHAR(50) UNIQUE
- `name` VARCHAR(150)
- `category_id` BIGINT FK -> `product_categories.id`
- `unit_of_measure_id` BIGINT FK -> `units_of_measure.id`
- `current_cost` DECIMAL(12,4) NULL
- `profit_margin` DECIMAL(5,2) NULL
- `current_price` DECIMAL(12,4) NULL
- `price_threshold` DECIMAL(5,2) DEFAULT 3.00
- `is_active` BOOLEAN DEFAULT true
- `created_at`, `updated_at`
- `deleted_at`

### 2.7 finished_inventory
Stock de producto terminado por bodega.

- `id` BIGINT PK
- `product_id` BIGINT FK -> `products.id`
- `warehouse_id` BIGINT FK -> `warehouses.id`
- `quantity` DECIMAL(12,4) DEFAULT 0
- `created_at`, `updated_at`
- Restriccion UNIQUE (`product_id`, `warehouse_id`)

### 2.8 formulas
Formulas/versiones por producto.

- `id` BIGINT PK
- `product_id` BIGINT FK -> `products.id`
- `version` INT DEFAULT 1
- `is_active` BOOLEAN DEFAULT true
- `notes` TEXT NULL
- `created_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`
- `deleted_at`

### 2.9 formula_details
Detalle de materias primas por formula.

- `id` BIGINT PK
- `formula_id` BIGINT FK -> `formulas.id`
- `raw_material_id` BIGINT FK -> `raw_materials.id`
- `quantity` DECIMAL(12,4)
- `unit_of_measure_id` BIGINT FK -> `units_of_measure.id`
- `created_at`, `updated_at`
- Restriccion UNIQUE (`formula_id`, `raw_material_id`)

### 2.10 production_orders
Ordenes de produccion.

- `id` BIGINT PK
- `order_number` VARCHAR(20) UNIQUE
- `product_id` BIGINT FK -> `products.id`
- `formula_id` BIGINT FK -> `formulas.id`
- `warehouse_id` BIGINT FK -> `warehouses.id`
- `quantity` DECIMAL(12,4)
- `actual_quantity` DECIMAL(12,4) NULL
- `yield_percentage` DECIMAL(5,2) NULL
- `status` ENUM(`pendiente`,`en_proceso`,`finalizada`,`cancelada`) DEFAULT `pendiente`
- `planned_date` DATE
- `completion_date` DATE NULL
- `notes` TEXT NULL
- `created_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`

### 2.11 production_order_details
Consumo detallado por lote dentro de cada orden.

- `id` BIGINT PK
- `production_order_id` BIGINT FK -> `production_orders.id`
- `batch_id` BIGINT FK -> `inventory_batches.id`
- `raw_material_id` BIGINT FK -> `raw_materials.id`
- `quantity` DECIMAL(12,4)
- `unit_cost` DECIMAL(12,4)
- `total_cost` DECIMAL(12,4)
- `created_at`, `updated_at`

### 2.12 inventory_movements
Movimientos de inventario de materia prima.

- `id` BIGINT PK
- `raw_material_id` BIGINT FK -> `raw_materials.id`
- `batch_id` BIGINT FK NULL -> `inventory_batches.id`
- `production_order_id` BIGINT FK NULL -> `production_orders.id`
- `type` ENUM(`entrada`,`salida`)
- `quantity` DECIMAL(12,4)
- `cost_price` DECIMAL(12,4)
- `movement_date` DATE
- `notes` TEXT NULL
- `created_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`

### 2.13 finished_inventory_movements
Movimientos de producto terminado.

- `id` BIGINT PK
- `product_id` BIGINT FK -> `products.id`
- `warehouse_id` BIGINT FK -> `warehouses.id`
- `production_order_id` BIGINT FK NULL -> `production_orders.id`
- `type` ENUM(`entrada`,`salida`)
- `quantity` DECIMAL(12,4)
- `movement_date` DATE
- `notes` TEXT NULL
- `created_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`

### 2.14 production_costs
Historial de costos calculados.

- `id` BIGINT PK
- `product_id` BIGINT FK -> `products.id`
- `formula_id` BIGINT FK -> `formulas.id`
- `cost` DECIMAL(12,4)
- `variation_percentage` DECIMAL(8,4) NULL
- `calculated_at` TIMESTAMP DEFAULT current_timestamp
- `created_at`, `updated_at`

### 2.15 price_list
Historial de precios.

- `id` BIGINT PK
- `product_id` BIGINT FK -> `products.id`
- `price` DECIMAL(12,4)
- `cost_at_time` DECIMAL(12,4)
- `profit_margin` DECIMAL(5,2)
- `update_type` ENUM(`manual`,`automatico`)
- `variation_percentage` DECIMAL(8,4) NULL
- `valid_from` DATE
- `valid_to` DATE NULL
- `created_by` BIGINT FK NULL -> `users.id`
- `created_at`, `updated_at`

### 2.16 qr_codes
QR por producto para documentos.

- `id` BIGINT PK
- `product_id` BIGINT FK UNIQUE -> `products.id`
- `token` VARCHAR(100) UNIQUE
- `url` VARCHAR(500)
- `is_active` BOOLEAN DEFAULT true
- `created_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`
- `deleted_at`

### 2.17 qr_documents
Documentos asociados al QR.

- `id` BIGINT PK
- `qr_code_id` BIGINT FK -> `qr_codes.id`
- `document_type` ENUM(`ficha_tecnica`,`ficha_seguridad`,`certificado_calidad`)
- `file_name` VARCHAR(255)
- `file_path` VARCHAR(500)
- `version` INT DEFAULT 1
- `is_current` BOOLEAN DEFAULT true
- `uploaded_by` BIGINT FK -> `users.id`
- `created_at`, `updated_at`
- `deleted_at`

### 2.18 alerts
Alertas del sistema.

- `id` BIGINT PK
- `type` ENUM(`stock_bajo`,`vencimiento_proximo`,`variacion_precio`)
- `raw_material_id` BIGINT FK NULL -> `raw_materials.id`
- `batch_id` BIGINT FK NULL -> `inventory_batches.id`
- `severity` ENUM(`baja`,`media`,`alta`) DEFAULT `media`
- `message` TEXT
- `is_resolved` BOOLEAN DEFAULT false
- `resolved_by` BIGINT FK NULL -> `users.id`
- `resolved_at` TIMESTAMP NULL
- `updated_by` BIGINT FK NULL -> `users.id`
- `created_at`, `updated_at`

## 3. Entidades de autenticacion, cola, cache y permisos

### 3.1 Auth base
Usuarios y tokens de acceso.

- `users`
    - `id` BIGINT PK
    - `name` VARCHAR(255)
    - `email` VARCHAR(255) UNIQUE
    - `email_verified_at` TIMESTAMP NULL
    - `password` VARCHAR(255)
    - `is_active` BOOLEAN DEFAULT true
    - `last_login_at` TIMESTAMP NULL
    - `remember_token` VARCHAR(100) NULL
    - `created_at`, `updated_at`

- `password_reset_tokens`

### 3.2 Actividad y Auditoría (Spatie Activity Log)
Registro histórico de acciones administrativas y de negocio.

- `activity_log`
    - `id` BIGINT PK
    - `log_name` VARCHAR(255) NULL (ej: default, auth, role_change)
    - `description` TEXT (descripcion legible del evento)
    - `subject_id` BIGINT NULL (ID del modelo afectado)
    - `subject_type` VARCHAR(255) NULL (Clase del modelo afectado)
    - `causer_id` BIGINT NULL (ID del usuario que realizo la accion)
    - `causer_type` VARCHAR(255) NULL
    - `properties` JSON NULL (datos antiguos vs nuevos)
    - `batch_uuid` UUID NULL
    - `event` VARCHAR(255) NULL (created, updated, deleted, failed_login)
    - `created_at`, `updated_at`

### 3.3 Cache/locks
- `cache`
- `cache_locks`

### 3.3 Queue
- `jobs`
- `job_batches`
- `failed_jobs`

### 3.4 Spatie Permission
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

## 4. Relaciones principales (resumen)

- `products` -> `product_categories`
- `products` -> `units_of_measure`
- `raw_materials` -> `units_of_measure`
- `formulas` -> `products`
- `formula_details` -> `formulas`, `raw_materials`, `units_of_measure`
- `inventory_batches` -> `raw_materials`
- `inventory_movements` -> `raw_materials`, `inventory_batches`, `production_orders`
- `production_orders` -> `products`, `formulas`, `warehouses`
- `production_order_details` -> `production_orders`, `inventory_batches`, `raw_materials`
- `finished_inventory` -> `products`, `warehouses`
- `finished_inventory_movements` -> `products`, `warehouses`, `production_orders`
- `production_costs` -> `products`, `formulas`
- `price_list` -> `products`, `users`
- `qr_codes` -> `products`
- `qr_documents` -> `qr_codes`, `users`
- `alerts` -> `raw_materials`, `inventory_batches`, `users`

## 5. Notas de implementacion

- Se consolidaron migraciones intermedias `add_*` dentro de migraciones `create_*`.
- Se eliminaron migraciones obsoletas de 2FA y columnas agregadas posteriormente.
- La notificacion de reset de password esta personalizada en `App\\Notifications\\ResetPasswordNotification`.
- Locale de aplicacion en espanol (`APP_LOCALE=es`), con archivos de traduccion `lang/es/*`.
