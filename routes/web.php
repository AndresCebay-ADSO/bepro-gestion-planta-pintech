<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ComercialController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;

Route::redirect('/', '/login')->name('home');

// Rutas autenticadas (todos los roles)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
});

// ============ RUTAS PROTEGIDAS POR ROL ============

// Solo ADMIN: Acceso a configuración, usuarios, auditoría
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::resource('users', UserController::class);
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

// ADMIN + PRODUCCIÓN: Acceso a costos y precios
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    // Route::resource('production-costs', ProductionCostController::class);
    // Route::resource('price-list', PriceListController::class);
});

require __DIR__.'/settings.php';
