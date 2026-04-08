# Prompt para Codex - Implementar CRUD de Materias Primas

## Contexto del Proyecto
Laravel 13 + Inertia.js + React + TypeScript + PostgreSQL. ERP para planta de pinturas.

## Tarea
Implementar CRUD completo de Materias Primas (Raw Materials) siguiendo los patrones existentes en el proyecto.

## Modelo Existente (app/Models/RawMaterial.php)
```php
// Tabla: raw_materials
// Campos fillable: name, unit_of_measure_id, current_price, previous_price, minimum_stock, alert_days_before_expiry, is_active
// Relaciones: unitOfMeasure(), inventoryBatches(), formulaDetails()
// Usa SoftDeletes y casts para decimales
```

## Patron a Seguir (basado en ProductController)

### 1. Crear Policy (app/Policies/RawMaterialPolicy.php)
- Admin: full access
- Produccion: viewAny, view
- Comercial: solo viewAny (lectura)

### 2. Crear Form Requests (app/Http/Requests/RawMaterials/)
StoreRawMaterialRequest:
- name: required, string, max:150, unique:raw_materials
- unit_of_measure_id: required, exists:units_of_measure,id
- current_price: required, numeric, min:0, max:99999999.9999
- previous_price: nullable, numeric, min:0
- minimum_stock: required, numeric, min:0, default:0
- alert_days_before_expiry: required, integer, min:1, default:30
- is_active: boolean

UpdateRawMaterialRequest: mismas reglas excepto unique que debe ignorar el registro actual

### 3. Crear Controller (app/Http/Controllers/Inventory/RawMaterialController.php)
Namespace: App\Http\Controllers\Inventory

Metodos:
- index(): Listar con paginacion, eager load unitOfMeasure, busqueda por nombre
- create(): Retornar unidades de medida activas
- store(): Crear y redirigir a index con mensaje success
- show(): Cargar relaciones unitOfMeasure e inventoryBatches
- edit(): Similar a create pero con el modelo
- update(): Actualizar y redirigir
- destroy(): Soft delete, verificar si tiene batches activos (con stock > 0)

### 4. Registrar Rutas (routes/web.php)
Dentro del grupo de middleware auth, verified, role:admin (para create, store, edit, update, destroy):
```php
Route::resource('raw-materials', Inventory\RawMaterialController::class);
```

### 5. Crear Vistas React (resources/js/pages/Inventory/RawMaterials/)
Basar en resources/js/pages/Products/ pero simplificar:

Index.tsx:
- Tabla con columnas: Nombre, Unidad, Precio Actual, Stock Minimo, Estado
- Boton "Nueva Materia Prima" (solo admin)
- Acciones: Ver, Editar, Eliminar (con confirmacion)
- Busqueda por nombre

Create.tsx:
- Formulario con campos: name, unit_of_measure_id (select), current_price, previous_price, minimum_stock, alert_days_before_expiry, is_active (checkbox)
- Validaciones del frontend
- Boton guardar y cancelar

Edit.tsx:
- Similar a Create pero cargando datos existentes

Show.tsx:
- Vista de detalle con informacion completa
- Lista de lotes asociados (inventoryBatches)

## Consideraciones Especificas
- Usar UnitOfMeasure::where('is_active', true) para los selects
- Al eliminar, verificar que no tenga batches con remaining_quantity > 0
- Usar ziggy para las rutas: route('raw-materials.store')
- Mensajes de exito: "Materia prima registrada exitosamente", "Materia prima actualizada exitosamente", etc.
- Usar componentes de shadcn/ui que ya existen en el proyecto

## Estructura Esperada de Respuesta
1. Codigo del Policy completo
2. Codigo de los Form Requests
3. Codigo del Controller
4. Codigo de las rutas a agregar
5. Codigo de los componentes React (TypeScript)

Seguir el estilo PSR-12, tipos estrictos donde sea posible, y mantener consistencia con los controllers existentes.

---

**Nota**: Este prompt fue creado para el Checkpoint Alfa (Semana 3) del proyecto Pintech OS.
Fecha de creacion: Abril 2026
