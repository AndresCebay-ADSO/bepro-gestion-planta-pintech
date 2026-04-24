# Guía Técnica: Exportación de Órdenes de Producción (PDF y Excel)

Esta guía documenta la implementación técnica de las funcionalidades de exportación para las Órdenes de Producción en Pintech OS.

## 1. Arquitectura de Datos

Para asegurar que tanto la pantalla, el PDF y el Excel muestren la misma información, se centralizó la lógica de datos en el controlador.

- **Archivo**: `app/Http/Controllers/ProductionOrderController.php`
- **Método Clave**: `buildOrderData(ProductionOrder $productionOrder)`
  - Este método realiza el `eager loading` de todas las relaciones necesarias (producto, fórmula, bodega, movimientos de inventario).
  - Transforma los datos crudos de Eloquent en un array estructurado y limpio.
  - Calcula costos totales y rendimientos en un solo lugar.

> [!TIP]
> Si necesitas añadir un nuevo campo a la exportación, primero añádelo en `buildOrderData` para que esté disponible en todos los formatos.

---

## 2. Exportación a PDF (Ficha FPR-01)

Se utiliza la librería `barryvdh/laravel-dompdf` para generar un documento que cumpla con los estándares industriales de Pintech.

### Archivos Relacionados
- **Vista Blade**: `resources/views/pdf/production-order.blade.php`
- **Estilos**: Inline CSS dentro de la misma vista (necesario para DomPDF).

### Detalles de Implementación
- **Consistencia Visual**: La plantilla está dividida en 8 secciones numeradas que replican el formulario físico.
- **Optimización de Imagen**: El logo se redimensionó de 24MP a 600px para reducir el tiempo de generación de 10s a <0.5s.
- **Base64**: El logo se inyecta como string Base64 para evitar problemas de permisos de archivos o rutas relativas dentro del motor PDF.

### Cómo editar el diseño del PDF
1. Abre `resources/views/pdf/production-order.blade.php`.
2. Modifica el bloque `<style>` para cambiar colores (como el verde de las cabeceras `#4a7c59`).
3. Usa clases como `.section-header` o `.label` para mantener la uniformidad.

---

## 3. Exportación a Excel

Se utiliza `maatwebsite/excel` bajo un patrón de "Múltiples Hojas" para organizar la información de manera lógica.

### Archivos Relacionados
- **Clase Principal**: `app/Exports/ProductionOrderExport.php`
- **Hoja General**: `app/Exports/Sheets/ProductionOrderGeneralSheet.php`
- **Hoja Ingredientes**: `app/Exports/Sheets/ProductionOrderIngredientsSheet.php`

### Diseño y Estilos
- **ShouldAutoSize**: Ajusta el ancho de las columnas automáticamente.
- **WithStyles**: Aplica colores de cabecera y bordes.
- **WithColumnFormatting**: Asegura que los precios tengan signo de pesos (`$`) y los pesos tengan decimales fijos.

### Cómo editar el Excel
- **Cambiar campos**: Edita el método `array()` en las clases de `Sheets`.
- **Cambiar colores**: Edita el método `styles()` en las mismas clases.

---

## 4. Integración Frontend

### Rutas y Wayfinder
- Las rutas se definieron en `routes/web.php` con nombres específicos:
  - `production-orders.export-pdf`
  - `production-orders.export-excel`
- Se ejecutó `php artisan wayfinder:generate` para crear las funciones de TypeScript en `@/actions/ProductionOrderController`.

### UI (React)
- **Archivo**: `resources/js/pages/Production/Orders/Show.tsx`
- Se añadieron los botones en el componente de cabecera utilizando el hook `useHttp` o redirección directa para descargas de archivos.

---

## 5. Notas de Mantenimiento y Solución de Problemas

- **Error "Array offset on null"**: Se corrigió asegurando que el acceso a propiedades de objetos relacionados (como el código del producto) sea seguro mediante validaciones de existencia.
- **Lentitud**: La causa principal era una imagen de logo de 24MP. Se optimizó a 600px.
- **Rendimiento Financiero**: El resumen financiero solo se muestra si el estado de la orden es `completed`.

---

## 6. Deuda Técnica y Refactorizaciones Pendientes (Próximamente)

Para futuras versiones (v1.1+), se han identificado las siguientes mejoras:

- **[ ] Reorganizar Diseño de Envases (Excel)**: Actualmente el listado de envases está en una celda combinada. Se podría desglosar en una tabla más específica si la jefa lo requiere.
- **[ ] Refactorización del Controlador**: Mover la lógica de `buildOrderData` del controlador a un **Eloquent Resource** o un **Service** dedicado para mantener el controlador "delgado" (Slim Controller).
- **[ ] Helper Global de Formato**: Crear un helper en PHP para estandarizar el formato de números y moneda, similar al `formatters.ts` del frontend.

---

## Comandos Útiles usados en esta rama
```bash
# Generar tipos de rutas para el frontend
php artisan wayfinder:generate

# Formatear código PHP según estándares del proyecto
./vendor/bin/pint --format agent

# Ejecutar tests de exportación
./vendor/bin/pest tests/Feature/Production/ProductionOrderExportTest.php
```
