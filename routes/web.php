<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ComercialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\RawMaterialController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Rutas autenticadas (todos los roles)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
});

// ============ RUTAS PROTEGIDAS POR ROL ============

// Solo ADMIN: Acceso a configuración, usuarios, auditoría
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('raw-materials', RawMaterialController::class)->except(['index', 'show']);
    Route::resource('warehouses', WarehouseController::class)->except(['index', 'show']);
    Route::get('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsersPage'])->name('warehouses.assign-users.form');
    Route::post('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsers'])->name('warehouses.assign-users');
    // Route::resource('roles', RoleController::class);
    // Route::resource('permissions', PermissionController::class);
});

// PRODUCCIÓN: Acceso a inventario, órdenes, formulaciones
Route::middleware(['auth', 'verified', 'role:produccion'])->group(function () {
    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    // Route::resource('production-orders', ProductionOrderController::class);
    // Route::resource('formulas', FormulaController::class);
    // Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
});

// COMERCIAL: Consulta de disponibilidad (solo lectura)
Route::middleware(['auth', 'verified', 'role:comercial'])->group(function () {
    Route::get('/availability', [ComercialController::class, 'index'])->name('availability.index');
    // Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    // Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
});

// ADMIN + PRODUCCIÓN: Consulta detallada de materias primas
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::resource('raw-materials', RawMaterialController::class)->only(['show']);
});

Route::middleware(['auth', 'verified', 'role:admin,produccion,comercial'])->group(function () {
    Route::resource('warehouses', WarehouseController::class)->only(['show']);
});

// ADMIN + PRODUCCIÓN: Acceso a costos y precios
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    // Route::resource('production-costs', ProductionCostController::class);
    // Route::resource('price-list', PriceListController::class);
});

// ADMIN + PRODUCCIÓN + COMERCIAL: Consulta de catálogos e inventarios (según policy)
Route::middleware(['auth', 'verified', 'role:admin,produccion,comercial'])->group(function () {
    Route::resource('raw-materials', RawMaterialController::class)->only(['index']);
    Route::resource('warehouses', WarehouseController::class)->only(['index']);
    Route::resource('products', ProductController::class);
    Route::resource('inventory-movements', InventoryMovementController::class);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('set-current-warehouse', [WarehouseController::class, 'setCurrentWarehouse'])->name('warehouses.set-current');
});

require __DIR__.'/settings.php';
