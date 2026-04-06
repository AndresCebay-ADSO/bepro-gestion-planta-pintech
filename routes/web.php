<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Rutas autenticadas (todos los roles)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// ============ RUTAS PROTEGIDAS POR ROL ============

// Solo ADMIN: Acceso a configuración, usuarios, auditoría
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    // Aquí irían rutas de administración
    // Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    // Route::resource('users', UserController::class);
});

// PRODUCCIÓN: Acceso a inventario, órdenes, formulaciones
Route::middleware(['auth', 'verified', 'role:produccion'])->group(function () {
    // Aquí irían rutas de producción
    // Route::resource('production-orders', ProductionOrderController::class);
    // Route::resource('formulas', FormulaController::class);
    // Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
});

// COMERCIAL: Consulta de disponibilidad (solo lectura)
Route::middleware(['auth', 'verified', 'role:comercial'])->group(function () {
    // Aquí irían rutas comerciales (solo lectura)
    // Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
    // Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});

// ADMIN + PRODUCCIÓN: Acceso a costos y precios
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    // Aquí irían rutas compartidas entre admin y producción
    // Route::resource('production-costs', ProductionCostController::class);
    // Route::resource('price-list', PriceListController::class);
});

require __DIR__.'/settings.php';
