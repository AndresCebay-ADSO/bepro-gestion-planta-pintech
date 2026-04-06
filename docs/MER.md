# PT-DIC-01. Diccionario de Datos
## PINTECH COLOMBIA S.A.S — Versión 1.0

---

**HISTORIAL DE REVISIÓN**

| Versión | Fecha Elaboración | Responsable Elaboración | Fecha Aprobación | Responsable Aprobación |
|---------|-------------------|------------------------|------------------|------------------------|
| 1.0 | 01/04/2026 | Andrés Stiven Cebay Ceballos | | |

**CAMBIOS RESPECTO A LA VERSIÓN ANTERIOR**

| Versión | Modificación |
|---------|-------------|
| 1.0 | Creación del documento |

---

## 1. Introducción

El presente documento describe la estructura de la base de datos del sistema de gestión de planta para PINTECH COLOMBIA S.A.S. Para cada tabla se especifican sus campos, tipos de datos, restricciones y relaciones, con el fin de servir como guía técnica para el proceso de desarrollo e implementación.

La base de datos está diseñada para el motor **PostgreSQL 16**, utilizando el ORM Eloquent de Laravel 12.

### 1.1 Responsables e Involucrados

| Nombre | Tipo | Rol | Cargo |
|--------|------|-----|-------|
| Andrés Stiven Cebay Ceballos | Responsable | Analista/Desarrollador | Practicante ADSO |
| PINTECH COLOMBIA S.A.S | Involucrado | Organización | Administrador |

### 1.2 Convenciones

| Convención | Descripción |
|------------|-------------|
| PK | Llave primaria (Primary Key) |
| FK | Llave foránea (Foreign Key) |
| NN | Not Null (campo obligatorio) |
| UQ | Unique (valor único) |
| AI | Auto Increment |
| DEFAULT | Valor por defecto |

---

## 2. Diagrama de Relaciones (Resumen)

```
users ──────────── roles
  │
  └── production_orders ──── production_order_details ──── raw_materials
                                                              │
                                        inventory_batches ───┘
                                              │
                                    inventory_movements

products ──── formulas ──── formula_details ──── raw_materials
   │
   ├── finished_inventory ──── warehouses
   ├── production_costs
   ├── price_list
   └── qr_codes ──── qr_documents

alerts (relacionada con raw_materials y products)
```

---

## 3. Tablas

---

### 3.0 product_categories
**Descripción:** Categorías de productos para mayor flexibilidad que usar ENUM.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------||
| id | BIGINT | PK, AI, NN | Identificador único de la categoría |
| name | VARCHAR(100) | NN, UQ | Nombre de la categoría (Industrial, Automotriz, Arquitectónico) |
| description | TEXT | NULL | Descripción detallada de la categoría |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

---

### 3.1 users
**Descripción:** Almacena los usuarios del sistema con sus credenciales y estado.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del usuario |
| name | VARCHAR(100) | NN | Nombre completo del usuario |
| email | VARCHAR(150) | NN, UQ | Correo electrónico (usado como usuario) |
| password | VARCHAR(255) | NN | Contraseña encriptada con bcrypt |
| is_active | BOOLEAN | NN, DEFAULT true | Estado del usuario (activo/inactivo) |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación del registro |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de última actualización |

**Relaciones:**
- Un usuario tiene un rol asignado a través de la tabla `model_has_roles` (Spatie)
- Un usuario puede registrar múltiples órdenes de producción

---

### 3.2 roles
**Descripción:** Define los roles del sistema gestionados por el paquete Spatie Laravel Permission. Los roles base son: Administrador, Asistente de Producción y Comercial.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del rol |
| name | VARCHAR(125) | NN, UQ | Nombre del rol |
| guard_name | VARCHAR(125) | NN | Guard de autenticación (por defecto: web) |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Nota:** Las tablas `permissions`, `model_has_roles`, `model_has_permissions` y `role_has_permissions` son generadas automáticamente por Spatie Laravel Permission y no se detallan aquí.

---

### 3.3 raw_materials
**Descripción:** Catálogo de materias primas utilizadas en el proceso de fabricación de pinturas.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único de la materia prima |
| name | VARCHAR(150) | NN, UQ | Nombre de la materia prima (ej: Titanio, Resina acrílica) |
| unit_of_measure | VARCHAR(20) | NN | Unidad de medida (kg, lt, g, ml, unidad) |
| current_price | DECIMAL(12,4) | NN | Precio actual por unidad de medida |
| previous_price | DECIMAL(12,4) | NULL | Precio anterior (para calcular variación) |
| minimum_stock | DECIMAL(12,4) | NN, DEFAULT 0 | Nivel mínimo de stock para generar alerta |
| alert_days_before_expiry | INT | NN, DEFAULT 30 | Días de anticipación para alerta de vencimiento |
| is_active | BOOLEAN | NN, DEFAULT true | Estado de la materia prima |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Una materia prima puede tener múltiples lotes (`inventory_batches`)
- Una materia prima puede aparecer en múltiples formulaciones (`formula_details`)
- Una materia prima puede tener múltiples alertas (`alerts`)

---

### 3.4 inventory_batches
**Descripción:** Registra los lotes de entrada de materia prima. Cada entrada al inventario genera un lote independiente para aplicar metodología PEPS.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del lote |
| raw_material_id | BIGINT | FK, NN | Referencia a la materia prima |
| initial_quantity | DECIMAL(12,4) | NN | Cantidad inicial del lote al ingresar |
| remaining_quantity | DECIMAL(12,4) | NN | Cantidad disponible actualmente en el lote |
| unit_price | DECIMAL(12,4) | NN | Precio unitario al momento de la entrada |
| entry_date | DATE | NN | Fecha de ingreso del lote al inventario |
| expiry_date | DATE | NULL | Fecha de vencimiento del lote |
| supplier | VARCHAR(150) | NULL | Proveedor del lote |
| lot_number | VARCHAR(50) | NULL | Número de lote del proveedor |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación del registro |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Pertenece a una materia prima (`raw_materials`)
- Puede tener múltiples movimientos (`inventory_movements`)

**Nota PEPS:** Los lotes se consumen ordenados por `entry_date` ascendente. El campo `remaining_quantity` se descuenta con cada salida.

---

### 3.5 inventory_movements
**Descripción:** Registra todos los movimientos de entrada y salida de materia prima para trazabilidad completa.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del movimiento |
| raw_material_id | BIGINT | FK, NN | Referencia a la materia prima |
| batch_id | BIGINT | FK, NULL | Lote afectado (aplica en salidas PEPS) |
| production_order_id | BIGINT | FK, NULL | Orden de producción asociada (si aplica) |
| type | ENUM('entrada','salida') | NN | Tipo de movimiento |
| quantity | DECIMAL(12,4) | NN | Cantidad del movimiento |
| cost_price | DECIMAL(12,4) | NN | Precio de costo unitario al momento del movimiento |
| movement_date | DATE | NN | Fecha del movimiento |
| notes | TEXT | NULL | Observaciones adicionales |
| created_by | BIGINT | FK, NN | Usuario que registró el movimiento |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Pertenece a una materia prima (`raw_materials`)
- Puede pertenecer a un lote (`inventory_batches`)
- Puede estar asociado a una orden de producción (`production_orders`)
- Registrado por un usuario (`users`)

---

### 3.6 products
**Descripción:** Catálogo de productos terminados fabricados por Pintech Colombia S.A.S.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del producto |
| code | VARCHAR(50) | NN, UQ | Código de referencia del producto |
| name | VARCHAR(150) | NN | Nombre del producto (ej: Poliuretano Blanco) |
| category_id | BIGINT | FK, NN | Categoría del producto (referencia a product_categories) |
| unit_of_measure | VARCHAR(20) | NN | Unidad de medida del producto (lt, kg, galón) |
| current_cost | DECIMAL(12,4) | NULL | Costo de producción actual calculado |
| profit_margin | DECIMAL(5,2) | NULL | Margen de ganancia en porcentaje |
| current_price | DECIMAL(12,4) | NULL | Precio de venta actual |
| price_threshold | DECIMAL(5,2) | NN, DEFAULT 3.00 | Umbral de variación de costo para actualización automática de precio (%) |
| is_active | BOOLEAN | NN, DEFAULT true | Estado del producto |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Pertenece a una categoría (`product_categories`)
- Un producto tiene una formulación activa (`formulas`)
- Un producto tiene historial de costos (`production_costs`)
- Un producto tiene historial de precios (`price_list`)
- Un producto tiene stock en bodegas (`finished_inventory`)
- Un producto tiene un código QR (`qr_codes`)

---

### 3.7 warehouses
**Descripción:** Bodegas donde se almacena el producto terminado. Actualmente Pintech opera con bodegas en Neiva y Cali.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único de la bodega |
| name | VARCHAR(100) | NN, UQ | Nombre de la bodega (ej: Bodega Cali, Bodega Neiva) |
| city | VARCHAR(100) | NN | Ciudad donde se ubica la bodega |
| address | VARCHAR(255) | NULL | Dirección de la bodega |
| is_active | BOOLEAN | NN, DEFAULT true | Estado de la bodega |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Una bodega puede tener múltiples registros de inventario de producto terminado (`finished_inventory`)

---

### 3.8 finished_inventory
**Descripción:** Controla el stock de producto terminado discriminado por producto y bodega.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del registro |
| product_id | BIGINT | FK, NN | Referencia al producto |
| warehouse_id | BIGINT | FK, NN | Referencia a la bodega |
| quantity | DECIMAL(12,4) | NN, DEFAULT 0 | Stock disponible actual |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Restricción única:** La combinación `(product_id, warehouse_id)` debe ser única.

**Relaciones:**
- Pertenece a un producto (`products`)
- Pertenece a una bodega (`warehouses`)

---

### 3.9 finished_inventory_movements
**Descripción:** Registra los movimientos de entrada y salida de producto terminado para trazabilidad.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del movimiento |
| product_id | BIGINT | FK, NN | Referencia al producto |
| warehouse_id | BIGINT | FK, NN | Referencia a la bodega |
| production_order_id | BIGINT | FK, NULL | Orden de producción asociada (en entradas) |
| type | ENUM('entrada','salida') | NN | Tipo de movimiento |
| quantity | DECIMAL(12,4) | NN | Cantidad del movimiento |
| movement_date | DATE | NN | Fecha del movimiento |
| notes | TEXT | NULL | Observaciones |
| created_by | BIGINT | FK, NN | Usuario que registró el movimiento |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

---

### 3.10 formulas
**Descripción:** Almacena las formulaciones activas e históricas de cada producto. Cada producto puede tener múltiples versiones de formulación pero solo una activa a la vez.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único de la formulación |
| product_id | BIGINT | FK, NN | Producto al que pertenece la formulación |
| version | INT | NN, DEFAULT 1 | Número de versión de la formulación |
| is_active | BOOLEAN | NN, DEFAULT true | Indica si es la formulación activa del producto |
| notes | TEXT | NULL | Observaciones sobre la formulación |
| created_by | BIGINT | FK, NN | Usuario que creó la formulación |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Restricción:** Solo puede existir una formulación con `is_active = true` por producto.

**Relaciones:**
- Pertenece a un producto (`products`)
- Tiene múltiples detalles de ingredientes (`formula_details`)

---

### 3.11 formula_details
**Descripción:** Detalle de cada materia prima que compone una formulación, con su cantidad requerida por unidad de producción.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del detalle |
| formula_id | BIGINT | FK, NN | Referencia a la formulación |
| raw_material_id | BIGINT | FK, NN | Referencia a la materia prima |
| quantity | DECIMAL(12,4) | NN | Cantidad de materia prima por unidad producida |
| unit_of_measure | VARCHAR(20) | NN | Unidad de medida de la cantidad |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Restricción única:** La combinación `(formula_id, raw_material_id)` debe ser única.

---

### 3.12 production_costs
**Descripción:** Historial de costos de producción calculados para cada producto. Cada vez que cambia el costo se registra una nueva entrada.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del registro de costo |
| product_id | BIGINT | FK, NN | Referencia al producto |
| formula_id | BIGINT | FK, NN | Formulación usada para el cálculo |
| cost | DECIMAL(12,4) | NN | Costo calculado |
| variation_percentage | DECIMAL(8,4) | NULL | Variación porcentual respecto al costo anterior |
| calculated_at | TIMESTAMP | NN, DEFAULT now() | Fecha y hora del cálculo |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |

**Relaciones:**
- Pertenece a un producto (`products`)
- Referencia la formulación usada en el cálculo (`formulas`)

---

### 3.13 price_list
**Descripción:** Historial de precios de venta de cada producto. Registra tanto cambios manuales como actualizaciones automáticas.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del registro de precio |
| product_id | BIGINT | FK, NN | Referencia al producto |
| price | DECIMAL(12,4) | NN | Precio de venta |
| cost_at_time | DECIMAL(12,4) | NN | Costo en el momento de fijar el precio |
| profit_margin | DECIMAL(5,2) | NN | Margen de ganancia aplicado (%) |
| update_type | ENUM('manual','automatico') | NN | Tipo de actualización |
| variation_percentage | DECIMAL(8,4) | NULL | Variación porcentual respecto al precio anterior |
| valid_from | DATE | NN | Fecha desde la cual es vigente este precio |
| valid_to | DATE | NULL | Fecha hasta la cual es vigente (NULL si está vigente) |
| created_by | BIGINT | FK, NULL | Usuario que fijó el precio (NULL si fue automático) |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |

**Restricción:** Solo puede existir un registro con `valid_to = NULL` por producto en un momento dado.

---

### 3.14 production_orders
**Descripción:** Órdenes de producción generadas en la planta para fabricar un producto específico.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único de la orden |
| order_number | VARCHAR(20) | NN, UQ | Número consecutivo de la orden (ej: OP-2026-001) |
| product_id | BIGINT | FK, NN | Producto a fabricar |
| formula_id | BIGINT | FK, NN | Formulación a usar en la producción |
| warehouse_id | BIGINT | FK, NN | Bodega donde se almacenará el producto terminado |
| quantity | DECIMAL(12,4) | NN | Cantidad planificada a producir |
| actual_quantity | DECIMAL(12,4) | NULL | Cantidad realmente producida |
| yield_percentage | DECIMAL(5,2) | NULL | Porcentaje de rendimiento (actual_quantity/quantity * 100) |
| status | ENUM('pendiente','en_proceso','finalizada','cancelada') | NN, DEFAULT 'pendiente' | Estado actual de la orden |
| planned_date | DATE | NN | Fecha planificada de producción |
| completion_date | DATE | NULL | Fecha real de finalización |
| notes | TEXT | NULL | Observaciones de la orden |
| created_by | BIGINT | FK, NN | Usuario que creó la orden |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Pertenece a un producto (`products`)
- Usa una formulación (`formulas`)
- Destina el producto a una bodega (`warehouses`)
- Tiene múltiples registros de consumo de MP (`inventory_movements`)

---

### 3.15 production_order_details
**Descripción:** Detalle de lotes de materia prima consumidos en cada orden de producción. Permite trazabilidad completa PEPS.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------||
| id | BIGINT | PK, AI, NN | Identificador único del detalle |
| production_order_id | BIGINT | FK, NN | Referencia a la orden de producción |
| batch_id | BIGINT | FK, NN | Lote específico de materia prima consumido |
| raw_material_id | BIGINT | FK, NN | Materia prima consumida |
| quantity | DECIMAL(12,4) | NN | Cantidad consumida del lote |
| unit_cost | DECIMAL(12,4) | NN | Costo unitario del lote al momento del consumo |
| total_cost | DECIMAL(12,4) | NN | Cantidad * unit_cost |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |

**Relaciones:**
- Pertenece a una orden de producción (`production_orders`)
- Referencia un lote específico (`inventory_batches`)

---

### 3.16 alerts
**Descripción:** Registro de alertas generadas automáticamente por el sistema.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único de la alerta |
| type | ENUM('stock_bajo','vencimiento_proximo','variacion_precio') | NN | Tipo de alerta |
| raw_material_id | BIGINT | FK, NULL | Materia prima relacionada (si aplica) |
| batch_id | BIGINT | FK, NULL | Lote relacionado (en alertas de vencimiento) |
| severity | ENUM('baja','media','alta') | NN, DEFAULT 'media' | Nivel de urgencia de la alerta |
| message | TEXT | NN | Mensaje descriptivo de la alerta |
| is_resolved | BOOLEAN | NN, DEFAULT false | Estado de la alerta |
| resolved_by | BIGINT | FK, NULL | Usuario que resolvió la alerta |
| resolved_at | TIMESTAMP | NULL | Fecha y hora de resolución |
| updated_by | BIGINT | FK, NULL | Usuario que realizó cambios en la alerta |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Puede estar asociada a una materia prima (`raw_materials`)
- Puede estar asociada a un lote (`inventory_batches`)
- Puede ser resuelta por un usuario (`users`)

---

### 3.17 qr_codes
**Descripción:** Códigos QR generados por producto para enlazar documentación técnica en los envases.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del código QR |
| product_id | BIGINT | FK, NN, UQ | Referencia al producto (uno por producto) |
| token | VARCHAR(100) | NN, UQ | Token único que forma parte de la URL pública |
| url | VARCHAR(500) | NN | URL pública generada para el QR |
| is_active | BOOLEAN | NN, DEFAULT true | Estado del código QR |
| created_by | BIGINT | FK, NN | Usuario que generó el QR |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Relaciones:**
- Pertenece a un producto (`products`)
- Tiene múltiples documentos asociados (`qr_documents`)

---

### 3.18 qr_documents
**Descripción:** Documentos técnicos asociados a cada código QR. Soporta múltiples versiones por tipo de documento.

| Campo | Tipo | Restricciones | Descripción |
|-------|------|---------------|-------------|
| id | BIGINT | PK, AI, NN | Identificador único del documento |
| qr_code_id | BIGINT | FK, NN | Referencia al código QR |
| document_type | ENUM('ficha_tecnica','ficha_seguridad','certificado_calidad') | NN | Tipo de documento |
| file_name | VARCHAR(255) | NN | Nombre del archivo almacenado |
| file_path | VARCHAR(500) | NN | Ruta del archivo en el servidor |
| version | INT | NN, DEFAULT 1 | Versión del documento |
| is_current | BOOLEAN | NN, DEFAULT true | Indica si es la versión vigente |
| uploaded_by | BIGINT | FK, NN | Usuario que subió el documento |
| created_at | TIMESTAMP | NN, DEFAULT now() | Fecha de creación |
| updated_at | TIMESTAMP | NN, DEFAULT now() | Fecha de actualización |

**Restricción:** Solo puede existir un documento con `is_current = true` por combinación `(qr_code_id, document_type)`.

---

## 4. Resumen de Tablas

| # | Tabla | Descripción | Registros estimados |
|---|-------|-------------|---------------------|
| 0 | product_categories | Categorías de productos | 3-5 |
| 1 | users | Usuarios del sistema | ~10 |
| 2 | roles | Roles del sistema | 3 |
| 3 | raw_materials | Catálogo de materias primas | ~50-100 |
| 4 | inventory_batches | Lotes de materia prima | ~500+ |
| 5 | inventory_movements | Movimientos de inventario MP | ~5000+ |
| 6 | products | Catálogo de productos terminados | ~50-100 |
| 7 | warehouses | Bodegas (Neiva y Cali) | 2 |
| 8 | finished_inventory | Stock de PT por bodega | ~200 |
| 9 | finished_inventory_movements | Movimientos de PT | ~1000+ |
| 10 | formulas | Formulaciones de productos | ~100+ |
| 11 | formula_details | Detalle de ingredientes por fórmula | ~1000+ |
| 12 | production_orders | Órdenes de producción | ~200+ |
| 13 | production_order_details | Lotes consumidos por orden | ~1000+ |
| 14 | production_costs | Historial de costos | ~500+ |
| 15 | price_list | Historial de precios | ~500+ |
| 16 | alerts | Alertas del sistema | ~100+ |
| 17 | qr_codes | Códigos QR por producto | ~50-100 |
| 18 | qr_documents | Documentos técnicos | ~300+ |

---

## 5. Índices Recomendados

| Tabla | Campo(s) | Tipo | Justificación |
|-------|----------|------|---------------|
| products | category_id | FK INDEX | Filtrar productos por categoría |
| inventory_batches | (raw_material_id, entry_date) | INDEX | Consultas PEPS ordenadas por fecha |
| inventory_batches | remaining_quantity | INDEX | Filtrar lotes con stock disponible |
| inventory_batches | expiry_date | INDEX | Alertas de vencimiento |
| inventory_movements | (raw_material_id, movement_date) | INDEX | Curvas de consumo por período |
| finished_inventory | (product_id, warehouse_id) | UNIQUE INDEX | Garantizar unicidad por producto/bodega |
| production_orders | status | INDEX | Consultas de órdenes por estado |
| production_costs | (product_id, calculated_at) | INDEX | Historial de costos por producto |
| price_list | (product_id, valid_to) | INDEX | Precio vigente por producto |
| alerts | (is_resolved, type) | INDEX | Consulta de alertas activas por tipo |

---

## 6. Referencias

- PT-PP-01: Planteamiento del Problema — Pintech Colombia S.A.S (2026)
- PT-ERS-01: Especificación de Requisitos — Pintech Colombia S.A.S (2026)
- PT-CU-01: Especificación de Casos de Uso — Pintech Colombia S.A.S (2026)
- PostgreSQL 16 Documentation: https://www.postgresql.org/docs/16/
- Laravel Eloquent ORM: https://laravel.com/docs/12.x/eloquent