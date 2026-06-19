<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ComercialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\Inventory\RawMaterialController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDocumentController;
use App\Http\Controllers\Production\LineAdjustmentController;
use App\Http\Controllers\Production\PackagingPlanController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PublicQrLandingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('/c/{token}', [PublicQrLandingController::class, 'show'])->name('qr.public.show');
Route::get('/c/{token}/qr.png', [PublicQrLandingController::class, 'qrImage'])->name('qr.public.image');
Route::get('/c/{token}/documents/{document}', [PublicQrLandingController::class, 'downloadDocument'])
    ->name('qr.public.documents.download');
Route::get('/c/{token}/product-documents/{document}', [PublicQrLandingController::class, 'downloadProductDocument'])
    ->name('qr.public.product-documents.download');

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
    Route::patch('raw-materials/{raw_material}/reactivate', [RawMaterialController::class, 'reactivate'])->name('raw-materials.reactivate');
    Route::resource('raw-materials', RawMaterialController::class)->except(['index', 'show']);
    Route::resource('warehouses', WarehouseController::class)->except(['index', 'show']);
    Route::get('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsersPage'])->name('warehouses.assign-users.form');
    Route::post('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsers'])->name('warehouses.assign-users');
    // Route::resource('roles', RoleController::class);
    // Route::resource('permissions', PermissionController::class);
});

// PRODUCCIÓN: Acceso a inventario, órdenes, formulaciones
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    // Route::resource('production-orders', ProductionOrderController::class);
    // Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
});

// COMERCIAL: Consulta de disponibilidad (solo lectura)
Route::middleware(['auth', 'verified', 'role:comercial'])->group(function () {
    Route::get('/availability', [ComercialController::class, 'index'])->name('availability.index');
    // Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    // Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
});

// ADMIN + PRODUCCIÓN: Alertas del sistema
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
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
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::resource('raw-materials', RawMaterialController::class)->only(['index']);
});

Route::middleware(['auth', 'verified', 'role:admin,produccion,comercial'])->group(function () {
    Route::resource('warehouses', WarehouseController::class)->only(['index']);
    Route::resource('products', ProductController::class);
    Route::post('products/{product}/documents', [ProductDocumentController::class, 'store'])->name('products.documents.store');
    Route::get('product-documents/{document}/download', [ProductDocumentController::class, 'download'])->name('products.documents.download');
    Route::delete('product-documents/{document}', [ProductDocumentController::class, 'destroy'])->name('products.documents.destroy');
    Route::resource('inventory-movements', InventoryMovementController::class)
        ->except(['create'])
        ->where(['inventory_movement' => '[0-9]+']);
});

// ADMIN + PRODUCCIÓN: Gestión de fórmulas y órdenes (escritura)
Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::resource('formulas', FormulaController::class);
    Route::post('formulas/{formula}/activate', [FormulaController::class, 'activate'])->name('formulas.activate');

    // Órdenes de Producción — mutaciones exclusivas de producción
    Route::get('production-orders/create', [ProductionOrderController::class, 'create'])->name('production-orders.create');
    Route::post('production-orders', [ProductionOrderController::class, 'store'])->name('production-orders.store');
    Route::post('production-orders/{production_order}/complete', [ProductionOrderController::class, 'complete'])->name('production-orders.complete');
    Route::post('production-orders/{production_order}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-orders.cancel');
    Route::post('production-orders/{production_order}/preview-costs', [ProductionOrderController::class, 'previewCosts'])
        ->middleware('throttle:production-preview-costs')
        ->name('production-orders.preview-costs');
    Route::post('production-orders/{production_order}/reject-review', [ProductionOrderController::class, 'rejectReview'])->name('production-orders.reject-review');

    Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
    Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
});

// ADMIN + PRODUCCIÓN + OPERADOR: Datos operativos de órdenes (ajustes de línea + envasado)
Route::middleware(['auth', 'verified', 'role:admin,produccion,operador'])->group(function () {
    // Ajustes de línea
    Route::post('production-orders/{production_order}/line-adjustments', [LineAdjustmentController::class, 'store'])->name('production-orders.line-adjustments.store');
    Route::delete('production-orders/{production_order}/line-adjustments/{adjustment}', [LineAdjustmentController::class, 'destroy'])->name('production-orders.line-adjustments.destroy');

    // Planes de envasado
    Route::post('production-orders/{production_order}/packaging-plans', [PackagingPlanController::class, 'store'])->name('production-orders.packaging-plans.store');
    Route::delete('production-orders/{production_order}/packaging-plans/{plan}', [PackagingPlanController::class, 'destroy'])->name('production-orders.packaging-plans.destroy');
});

// ADMIN + PRODUCCIÓN + OPERADOR: Órdenes de Producción (lectura + submit)
Route::middleware(['auth', 'verified', 'role:admin,produccion,operador'])->group(function () {
    Route::get('production-orders', [ProductionOrderController::class, 'index'])->name('production-orders.index');
    Route::get('production-orders/{production_order}', [ProductionOrderController::class, 'show'])->name('production-orders.show')->whereNumber('production_order');
    Route::get('production-orders/{production_order}/export-pdf', [ProductionOrderController::class, 'exportPdf'])->name('production-orders.export-pdf');
    Route::get('production-orders/{production_order}/export-excel', [ProductionOrderController::class, 'exportExcel'])->name('production-orders.export-excel');
    Route::post('production-orders/{production_order}/submit-for-review', [ProductionOrderController::class, 'submitForReview'])->name('production-orders.submit-for-review');
});

// OPERADOR: Dashboard del operador
Route::middleware(['auth', 'verified', 'role:operador'])->group(function () {
    Route::get('/operator', [OperatorController::class, 'index'])->name('operator.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('set-current-warehouse', [WarehouseController::class, 'setCurrentWarehouse'])->name('warehouses.set-current');
});

require __DIR__.'/settings.php';
