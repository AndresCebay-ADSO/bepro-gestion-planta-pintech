# Flujo Integral de Operaciones - Pintech OS

Este documento describe el proceso de negocio de inicio a fin dentro de la plataforma Pintech OS, desde la configuración inicial de materias primas hasta la disponibilidad de producto terminado para el equipo comercial.

---

## 🏗️ Fase 1: Configuración y Catálogo (Admin)

### 1.1 Estructura Base
El administrador define los cimientos del sistema:
- **Unidades de Medida**: Registro de `kg`, `L`, `gal`, `cuñete`. El sistema utiliza un catálogo normalizado para asegurar precisión decimal.
- **Bodegas**: 
    - **Cali Fábrica** (Tipo: `factory`): Donde ocurre la transformación.
    - **Neiva/Otras** (Tipo: `storage`): Centros de distribución.
- **Categorías**: Clasificación del portafolio (Epóxicos, Automotriz, etc.).

### 1.2 Materias Primas e Inventario Inicial
Registro de insumos químicos (S-7, R-5, etc.) con sus precios actuales.
- **Entrada de Stock**: Se crean `InventoryBatch` (Lotes). Cada lote rastrea:
    - Cantidad inicial vs. Cantidad remanente.
    - Costo unitario de compra (para cálculo de costo de producción real).
    - Fecha de vencimiento.

### 1.3 Catálogo de Productos y Variantes SKU
Siguiendo la arquitectura "Variante-Primero":
- **Producto Base**: Definición técnica (ej: "Primer Epoxi HS").
- **Variantes SKU**: Referencias comerciales específicas.
    - *Ejemplo*: SKU `EPO-HS-GAL` (Presentación Galón, Color Blanco).
    - Cada variante tiene su propio volumen en litros, permitiendo la conversión automática en producción.

### 1.4 Fórmulas (Ficha Técnica)
El administrador vincula el Producto Base con sus materias primas.
- La fórmula define la cantidad necesaria por **1 unidad base** (ej: 1 Litro).
- **Consumo Teórico**: El sistema usará estos valores para proyectar necesidades.

---

## 🏭 Fase 2: Ciclo de Producción (Planta Cali)

### 2.1 Creación de Orden de Producción
El usuario de `produccion` crea una nueva orden:
1. **Selección**: Escoge el producto y la cantidad total a fabricar (litros).
2. **Validación de Stock (Guardia)**: El sistema verifica en tiempo real si hay suficiente materia prima en los lotes de Cali. Si falta stock, la orden no se puede crear.
3. **Plan de Envasado**: Se definen cuántas unidades de qué SKU se esperan obtener (ej: 10 galones, 2 cuñetes).

### 2.2 Ejecución y Registro de Resultados (Paso C)
Durante la fabricación, el operario interactúa con la interfaz de resultados:
- **Consumos Reales**: Registro de cuánto se gastó realmente de cada lote (ajustando la desviación del teórico).
- **Pruebas de Calidad**: Registro de Viscosidad (KU) y Molienda (HG).
- **Tiempos**: Registro de tiempos de agitación y envasado.
- **Envasado Final**: Conteo real de unidades obtenidas por SKU.

### 2.3 Cierre Automático e Impacto en Inventario
Al presionar "Completar Orden":
1. **Salida de MP**: El sistema descuenta automáticamente la cantidad real de los lotes de materia prima seleccionados (FIFO/PEPS).
2. **Entrada de PT**: Se crea stock en `FinishedInventory` para cada variante producida en la bodega de Cali.
3. **Costeo Real**: Se calcula el costo exacto de la producción basado en los precios de los lotes consumidos.

---

## 🚚 Fase 3: Logística y Distribución (Traslados)

> [!NOTE]
> *Estado: El modelo de datos está implementado. La interfaz de usuario de traslados está en el roadmap inmediato.*

### 3.1 Traslado a Bodegas de Venta (Neiva)
1. **Origen**: Cali (Fábrica).
2. **Destino**: Neiva (Bodega).
3. **Flujo de Estados**:
    - **Pendiente**: Creación del documento de traslado.
    - **Enviado**: El stock sale de Cali y queda "en tránsito".
    - **Recibido**: Al llegar a Neiva, el encargado confirma y el stock se suma al inventario disponible en esa ciudad.

---

## 📊 Fase 4: Visibilidad Comercial

### 4.1 Consulta de Disponibilidad
El rol `comercial` accede a la vista de inventario:
- Filtra por Bodega (ej: Neiva).
- Visualiza el stock real disponible por SKU.
- El sistema muestra alertas si el stock es crítico para garantizar el cumplimiento de pedidos.

---

## 🛠️ Resumen Técnico del Flujo

1. **Materia Prima**: `RawMaterial` -> `InventoryBatch`
2. **Definición**: `Product` -> `ProductVariant` -> `Formula`
3. **Operación**: `ProductionOrder` -> `ProductionOrderDetail`
4. **Cierre**: `InventoryMovement` (Salida MP) -> `FinishedInventory` (Entrada PT)
5. **Logística**: `Transfer` (Cali -> Neiva)
6. **Consulta**: `FinishedInventory` (Vista Comercial)
