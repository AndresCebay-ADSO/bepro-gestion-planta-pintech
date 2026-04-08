# Prompt para Codex - Implementar CRUD de Bodegas (Warehouses)

## Contexto del Proyecto
Laravel 13 + Inertia.js + React + TypeScript + PostgreSQL. ERP para planta de pinturas.

## Estado Actual
- ✅ CRUD de Materias Primas completado
- ✅ Sistema de roles funcionando (admin, produccion, comercial)
- 🎯 Checkpoint Alfa: 17 de abril (en 9 días)

## Tarea
Implementar CRUD de Bodegas (Warehouses) con asignación de usuarios y selector de contexto.

## Modelo Existente (app/Models/Warehouse.php)
```php
// Tabla: warehouses
// Campos fillable: name, city, address, is_active
// Relaciones: finishedInventory()
// Usa SoftDeletes
```

## Requisitos Funcionales

### 1. Migración adicional: warehouse_user
Crear migración para la relación muchos-a-muchos entre usuarios y bodegas:
- `user_id` FK → users.id
- `warehouse_id` FK → warehouses.id
- `is_default` boolean (para saber cuál es la bodega principal del usuario)
- timestamps

### 2. Policy (app/Policies/WarehousePolicy.php)
- Admin: full access
- Producción: viewAny, view (solo sus bodegas asignadas)
- Comercial: viewAny (lista solo), ver disponibilidad de productos en sus bodegas

### 3. Form Requests (app/Http/Requests/Warehouses/)
StoreWarehouseRequest:
- name: required, string, max:100, unique:warehouses
- city: required, string, max:100
- address: nullable, string, max:255
- is_active: boolean

UpdateWarehouseRequest: mismas reglas excepto unique

AsignUsersRequest (para asignar usuarios a bodega):
- users: required, array
- users.*.user_id: required, exists:users,id
- users.*.is_default: boolean

### 4. Controller (app/Http/Controllers/Inventory/WarehouseController.php)
Namespace: App\Http\Controllers\Inventory

Métodos CRUD:
- index(): Listar con paginación. Producción solo ve sus bodegas asignadas.
- create(): Retornar formulario
- store(): Crear bodega
- show(): Ver detalle con usuarios asignados y stock de productos terminados
- edit(): Formulario de edición
- update(): Actualizar datos
- destroy(): Soft delete, verificar que no tenga finished_inventory

Métodos adicionales:
- assignUsers(): POST para asignar/desasignar usuarios
- setCurrentWarehouse(): POST para cambiar la bodega activa en sesión

### 5. Middleware/Service para contexto de bodega
Crear un servicio o usar sesión para mantener la bodega seleccionada:
- Al iniciar sesión, usuario debe tener una bodega por defecto
- Header/Sidebar debe mostrar selector de bodega si el usuario tiene > 1
- Cambiar bodega actualiza el contexto global de la aplicación

### 6. Rutas (routes/web.php)

```php
// Rutas de bodegas
Route::middleware(['auth', 'verified', 'role:admin'])
    ->group(function () {
        Route::resource('warehouses', Inventory\WarehouseController::class)
            ->except(['index', 'show']);
        Route::post('warehouses/{warehouse}/assign-users', 
            [Inventory\WarehouseController::class, 'assignUsers'])
            ->name('warehouses.assign-users');
    });

Route::middleware(['auth', 'verified', 'role:admin,produccion,comercial'])
    ->group(function () {
        Route::resource('warehouses', Inventory\WarehouseController::class)
            ->only(['index', 'show']);
    });

// Cambiar bodega actual (contexto)
Route::middleware(['auth', 'verified'])
    ->post('set-current-warehouse', [Inventory\WarehouseController::class, 'setCurrentWarehouse'])
    ->name('warehouses.set-current');
```

### 7. Modelo User - agregar relación
En `app/Models/User.php`, agregar:
```php
public function warehouses(): BelongsToMany
{
    return $this->belongsToMany(Warehouse::class, 'warehouse_user')
        ->withPivot('is_default')
        ->withTimestamps();
}

public function defaultWarehouse(): ?Warehouse
{
    return $this->warehouses()
        ->wherePivot('is_default', true)
        ->first();
}
```

### 8. Vistas React (resources/js/pages/Inventory/Warehouses/)
Basar en el patrón de RawMaterials.

Index.tsx:
- Tabla: Nombre, Ciudad, Dirección, Estado, Usuarios asignados
- Botón "Nueva Bodega" (solo admin)
- Acciones: Ver, Editar, Eliminar, Asignar Usuarios (modal)
- Producción: solo ve sus bodegas asignadas

Create.tsx / Edit.tsx:
- Campos: name, city, address, is_active
- Validaciones

Show.tsx:
- Información de la bodega
- Lista de usuarios asignados
- Stock actual de productos terminados (si aplica)

AssignUsers.tsx (modal o página):
- Lista de usuarios disponibles
- Checkbox para seleccionar/deseleccionar
- Radio button para marcar "bodega por defecto"
- Solo admin puede acceder

### 9. Componente de Selector de Bodega (Header/Sidebar)
En el layout principal, agregar un selector dropdown:
- Muestra la bodega actual seleccionada
- Si el usuario tiene > 1 bodega, muestra lista para cambiar
- Al cambiar, hace POST a `set-current-warehouse` y recarga la página
- Debe persistir en sesión/localStorage

## Consideraciones Específicas

### Contexto de Bodega
- Almacenar en sesión: `session(['current_warehouse_id' => $id])`
- O en cookie cifrada: `cookie('current_warehouse', $id, ...)`
- Middleware opcional para inyectar la bodega actual en todas las respuestas Inertia

### Producción - Restricción de acceso
- Producción SOLO puede ver sus bodegas asignadas
- En el controller, filtrar:
```php
if ($user->hasRole('produccion')) {
    $warehouses = $user->warehouses()->with(...)->paginate();
} else {
    $warehouses = Warehouse::with(...)->paginate();
}
```

### Comercial - Solo lectura
- Comercial ve bodegas para consultar disponibilidad de productos
- No ve el módulo de administración de bodegas
- Solo accede a `/warehouses` para ver lista y detalle (sin editar)

## Datos de prueba (Seeders)
Crear en Database\Seeders:
- WarehouseSeeder con 3 bodegas: "Planta Neiva", "Planta Cali", "Depósito Auxiliar"
- Asignar usuarios de prueba a bodegas:
  - admin@pintech.com: acceso a todas
  - produccion@pintech.com: Planta Neiva (default)
  - comercial@pintech.com: todas (solo lectura)

## Checklist de Implementación
- [ ] Migración warehouse_user
- [ ] Relación belongsToMany en User model
- [ ] Policy WarehousePolicy
- [ ] Form Requests Store/Update/AssignUsers
- [ ] WarehouseController completo
- [ ] Rutas registradas
- [ ] Vistas React: Index, Create, Edit, Show, AssignUsers
- [ ] Componente WarehouseSelector en Header/Sidebar
- [ ] Seeders con datos de prueba
- [ ] Tests básicos (solo si hay tiempo)

## Estructura Esperada de Respuesta
1. Código de la migración warehouse_user
2. Código de la Policy
3. Código de los Form Requests
4. Código del Controller completo
5. Código de las rutas a agregar
6. Actualización del modelo User (relación warehouses)
7. Código de los componentes React
8. Código del componente WarehouseSelector
9. Seeders actualizados

## Prioridad
**Alta** - Checkpoint Alfa requiere esto para la demo de usuarios reales.

---

**Nota**: Seguir los mismos patrones usados en RawMaterials para mantener consistencia.
