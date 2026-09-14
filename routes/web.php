<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\Inventory\FinishedInventoryController;
use App\Http\Controllers\Inventory\FinishedInventoryMovementController;
use App\Http\Controllers\Inventory\RawMaterialController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\PaintDevelopmentRequestController;
use App\Http\Controllers\Pricing\CostController;
use App\Http\Controllers\Pricing\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDocumentController;
use App\Http\Controllers\Production\LineAdjustmentController;
use App\Http\Controllers\Production\PackagingPlanController;
use App\Http\Controllers\Production\RemnantConsumptionController;
use App\Http\Controllers\Production\RemnantController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PublicQrLandingController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\UserController;
use App\Models\PaintDevelopmentRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('/c/{token}', [PublicQrLandingController::class, 'show'])->name('qr.public.show');
Route::get('/c/{token}/qr.png', [PublicQrLandingController::class, 'qrImage'])->name('qr.public.image');
Route::get('/c/{token}/documents/{document}', [PublicQrLandingController::class, 'downloadDocument'])
    ->name('qr.public.documents.download');
Route::get('/c/{token}/product-documents/{document}', [PublicQrLandingController::class, 'downloadProductDocument'])
    ->name('qr.public.product-documents.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:'.Permission::DashboardView->value)
        ->name('dashboard');
});

// ============ RUTAS PROTEGIDAS POR PERMISO (docs/MATRIZ_RBAC.md) ============
// Los módulos se mueven aquí a medida que su policy se migra a permisos (tarea 2.2).

Route::middleware(['auth', 'verified'])->group(function () {
    // Alertas
    Route::get('alerts', [AlertController::class, 'index'])
        ->middleware('can:'.Permission::AlertsView->value)
        ->name('alerts.index');
    Route::patch('alerts/{alert}/resolve', [AlertController::class, 'resolve'])
        ->middleware('can:'.Permission::AlertsResolve->value)
        ->name('alerts.resolve');

    // Códigos QR
    Route::middleware('can:'.Permission::QrCodesView->value)->group(function () {
        Route::get('qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
        Route::get('qr-codes/{qrCode}', [QrCodeController::class, 'show'])->name('qr-codes.show');
        Route::get('qr-codes/{qrCode}/qr.png', [QrCodeController::class, 'qrImage'])->name('qr-codes.qr-image');
        Route::get('qr-codes/{qrCode}/documents/{document}/download', [QrCodeController::class, 'downloadDocument'])
            ->name('qr-codes.documents.download');
    });
    Route::patch('qr-codes/{qrCode}', [QrCodeController::class, 'update'])
        ->middleware('can:'.Permission::QrCodesUpdate->value)
        ->name('qr-codes.update');

    // Saldos de producción
    Route::get('production/remnants', [RemnantController::class, 'index'])
        ->middleware('can:'.Permission::ProductionRemnantsView->value)
        ->name('production.remnants.index');

    // Materias primas
    Route::patch('raw-materials/{raw_material}/reactivate', [RawMaterialController::class, 'reactivate'])
        ->middleware('can:'.Permission::RawMaterialsReactivate->value)
        ->name('raw-materials.reactivate');
    Route::resource('raw-materials', RawMaterialController::class)
        ->middlewareFor(['index', 'show'], 'can:'.Permission::RawMaterialsView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::RawMaterialsCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::RawMaterialsEdit->value)
        // Desactiva; el borrado físico dentro exige además raw_materials.delete (tarea 2.7).
        ->middlewareFor('destroy', 'can:'.Permission::RawMaterialsDeactivate->value);

    // Fórmulas
    Route::resource('formulas', FormulaController::class)
        ->middlewareFor(['index', 'show'], 'can:'.Permission::FormulasView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::FormulasCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::FormulasEdit->value)
        ->middlewareFor('destroy', 'can:'.Permission::FormulasDelete->value);
    Route::post('formulas/{formula}/activate', [FormulaController::class, 'activate'])
        ->middleware('can:'.Permission::FormulasActivate->value)
        ->name('formulas.activate');

    // Bodegas
    Route::get('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsersPage'])
        ->middleware('can:'.Permission::WarehousesAssignUsers->value)
        ->name('warehouses.assign-users.form');
    Route::post('warehouses/{warehouse}/assign-users', [WarehouseController::class, 'assignUsers'])
        ->middleware('can:'.Permission::WarehousesAssignUsers->value)
        ->name('warehouses.assign-users');
    Route::resource('warehouses', WarehouseController::class)
        ->middlewareFor(['index', 'show'], 'can:'.Permission::WarehousesView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::WarehousesCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::WarehousesEdit->value)
        ->middlewareFor('destroy', 'can:'.Permission::WarehousesDelete->value);

    // Clientes
    Route::get('clients', [ClientController::class, 'index'])
        ->middleware('can:'.Permission::ClientsView->value)
        ->name('clients.index');
    Route::get('clients/create', [ClientController::class, 'create'])
        ->middleware('can:'.Permission::ClientsCreate->value)
        ->name('clients.create');
    Route::post('clients', [ClientController::class, 'store'])
        ->middleware('can:'.Permission::ClientsCreate->value)
        ->name('clients.store');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])
        ->middleware('can:'.Permission::ClientsEdit->value)
        ->name('clients.edit');
    Route::put('clients/{client}', [ClientController::class, 'update'])
        ->middleware('can:'.Permission::ClientsEdit->value)
        ->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])
        ->middleware('can:'.Permission::ClientsDelete->value)
        ->name('clients.destroy');

    // Productos, presentaciones y documentos
    Route::resource('products', ProductController::class)
        ->middlewareFor(['index', 'show'], 'can:'.Permission::ProductsView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::ProductsCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::ProductsEdit->value)
        ->middlewareFor('destroy', 'can:'.Permission::ProductsDelete->value);
    Route::middleware('can:'.Permission::ProductsManageVariants->value)->group(function () {
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
        Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
    });
    Route::post('products/{product}/documents', [ProductDocumentController::class, 'store'])
        ->middleware('can:'.Permission::ProductsManageDocuments->value)
        ->name('products.documents.store');
    Route::delete('product-documents/{document}', [ProductDocumentController::class, 'destroy'])
        ->middleware('can:'.Permission::ProductsManageDocuments->value)
        ->name('products.documents.destroy');
    Route::get('product-documents/{document}/download', [ProductDocumentController::class, 'download'])
        ->middleware('can:'.Permission::ProductsDownloadDocuments->value)
        ->name('products.documents.download');

    // Movimientos de materia prima (inmutables: sin editar ni borrar)
    Route::resource('inventory-movements', InventoryMovementController::class)
        ->only(['index', 'store', 'show'])
        ->where(['inventory_movement' => '[0-9]+'])
        ->middlewareFor(['index', 'show'], 'can:'.Permission::InventoryMovementsView->value)
        ->middlewareFor('store', 'can:'.Permission::InventoryMovementsCreate->value);

    // Costos
    Route::get('/admin/costs', [CostController::class, 'index'])
        ->middleware('can:'.Permission::CostsView->value)
        ->name('admin.costs.index');
    Route::patch('/admin/costs/{product}', [CostController::class, 'update'])
        ->middleware('can:'.Permission::CostsUpdate->value)
        ->name('admin.costs.update');

    // Listas de precios
    Route::get('/prices', [PriceListController::class, 'index'])
        ->middleware('can:'.Permission::PriceListsView->value)
        ->name('prices.index');

    // Usuarios
    Route::resource('users', UserController::class)
        ->except(['show'])
        ->middlewareFor('index', 'can:'.Permission::UsersView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::UsersCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::UsersEdit->value)
        ->middlewareFor('destroy', 'can:'.Permission::UsersDelete->value);

    // Auditoría
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:'.Permission::AuditLogsView->value)
        ->name('audit-logs.index');

    // Cotizaciones (listado y detalle: la policy decide propias o todas)
    Route::get('quotations', [QuotationController::class, 'index'])
        ->middleware('can:viewAny,'.Quotation::class)
        ->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])
        ->middleware('can:'.Permission::QuotationsCreate->value)
        ->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])
        ->middleware('can:'.Permission::QuotationsCreate->value)
        ->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('can:view,quotation')
        ->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])
        ->middleware('can:'.Permission::QuotationsEdit->value)
        ->name('quotations.edit');
    Route::put('quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware('can:'.Permission::QuotationsEdit->value)
        ->name('quotations.update');
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])
        ->middleware('can:'.Permission::QuotationsUpdateStatus->value)
        ->name('quotations.update-status');
    Route::post('quotations/{quotation}/convert-to-order', [QuotationController::class, 'convertToOrder'])
        ->middleware('can:'.Permission::QuotationsConvertToOrder->value)
        ->name('quotations.convert-to-order');
    Route::get('quotations/{quotation}/export-pdf', [QuotationController::class, 'exportPdf'])
        ->middleware('can:'.Permission::QuotationsExportPdf->value)
        ->name('quotations.export-pdf');

    // Desarrollo de pinturas (listado y detalle: la policy decide propias o todas)
    Route::get('paint-development-requests', [PaintDevelopmentRequestController::class, 'index'])
        ->middleware('can:viewAny,'.PaintDevelopmentRequest::class)
        ->name('paint-development-requests.index');
    Route::get('paint-development-requests/create', [PaintDevelopmentRequestController::class, 'create'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsCreate->value)
        ->name('paint-development-requests.create');
    Route::post('paint-development-requests', [PaintDevelopmentRequestController::class, 'store'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsCreate->value)
        ->name('paint-development-requests.store');
    Route::get('paint-development-requests/{paintDevelopmentRequest}', [PaintDevelopmentRequestController::class, 'show'])
        ->middleware('can:view,paintDevelopmentRequest')
        ->name('paint-development-requests.show');
    Route::get('paint-development-requests/{paintDevelopmentRequest}/edit', [PaintDevelopmentRequestController::class, 'edit'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsEdit->value)
        ->name('paint-development-requests.edit');
    Route::put('paint-development-requests/{paintDevelopmentRequest}', [PaintDevelopmentRequestController::class, 'update'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsEdit->value)
        ->name('paint-development-requests.update');
    Route::patch('paint-development-requests/{paintDevelopmentRequest}/submit', [PaintDevelopmentRequestController::class, 'submit'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsSubmit->value)
        ->name('paint-development-requests.submit');
    Route::patch('paint-development-requests/{paintDevelopmentRequest}/status', [PaintDevelopmentRequestController::class, 'updateStatus'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsUpdateStatus->value)
        ->name('paint-development-requests.update-status');
    Route::get('paint-development-requests/{paintDevelopmentRequest}/export-pdf', [PaintDevelopmentRequestController::class, 'exportPdf'])
        ->middleware('can:'.Permission::PaintDevelopmentRequestsExportPdf->value)
        ->name('paint-development-requests.export-pdf');

    // Pedidos de venta (listado y detalle: la policy decide propios o todos)
    Route::get('sales-orders', [SalesOrderController::class, 'index'])
        ->middleware('can:viewAny,'.SalesOrder::class)
        ->name('sales-orders.index');
    Route::get('sales-orders/create', [SalesOrderController::class, 'create'])
        ->middleware('can:'.Permission::SalesOrdersCreate->value)
        ->name('sales-orders.create');
    Route::post('sales-orders', [SalesOrderController::class, 'store'])
        ->middleware('can:'.Permission::SalesOrdersCreate->value)
        ->name('sales-orders.store');
    Route::get('sales-orders/{sales_order}', [SalesOrderController::class, 'show'])
        ->middleware('can:view,sales_order')
        ->name('sales-orders.show');
    Route::patch('sales-orders/{sales_order}', [SalesOrderController::class, 'update'])
        ->middleware('can:'.Permission::SalesOrdersEdit->value)
        ->name('sales-orders.update');
    Route::patch('sales-orders/{sales_order}/status', [SalesOrderController::class, 'updateStatus'])
        ->middleware('can:'.Permission::SalesOrdersUpdateStatus->value)
        ->name('sales-orders.update-status');

    // Inventario de producto terminado
    Route::get('finished-inventory', [FinishedInventoryController::class, 'index'])
        ->middleware('can:'.Permission::FinishedInventoryView->value)
        ->name('finished-inventory.index');
    Route::resource('finished-inventory-movements', FinishedInventoryMovementController::class)
        ->only(['index', 'store', 'show'])
        ->where(['finished_inventory_movement' => '[0-9]+'])
        ->middlewareFor(['index', 'show'], 'can:'.Permission::FinishedInventoryMovementsView->value)
        ->middlewareFor('store', 'can:'.Permission::FinishedInventoryMovementsCreate->value);
});

// ============ RUTAS PROTEGIDAS POR ROL ============

Route::middleware(['auth', 'verified', 'role:admin,produccion'])->group(function () {
    Route::get('production-orders/create', [ProductionOrderController::class, 'create'])->name('production-orders.create');
    Route::post('production-orders', [ProductionOrderController::class, 'store'])->name('production-orders.store');
    Route::post('production-orders/{production_order}/complete', [ProductionOrderController::class, 'complete'])->name('production-orders.complete');
    Route::post('production-orders/{production_order}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-orders.cancel');
    Route::post('production-orders/{production_order}/preview-costs', [ProductionOrderController::class, 'previewCosts'])
        ->middleware('throttle:production-preview-costs')
        ->name('production-orders.preview-costs');
    Route::post('production-orders/{production_order}/reject-review', [ProductionOrderController::class, 'rejectReview'])->name('production-orders.reject-review');

});

Route::middleware(['auth', 'verified', 'role:admin,produccion,operador'])->group(function () {
    Route::post('production-orders/{production_order}/line-adjustments', [LineAdjustmentController::class, 'store'])->name('production-orders.line-adjustments.store');
    Route::delete('production-orders/{production_order}/line-adjustments/{adjustment}', [LineAdjustmentController::class, 'destroy'])->name('production-orders.line-adjustments.destroy');

    Route::post('production-orders/{production_order}/packaging-plans', [PackagingPlanController::class, 'store'])->name('production-orders.packaging-plans.store');
    Route::delete('production-orders/{production_order}/packaging-plans/{plan}', [PackagingPlanController::class, 'destroy'])->name('production-orders.packaging-plans.destroy');

    Route::post('production-orders/{production_order}/consume-remnant', [RemnantConsumptionController::class, 'store'])->name('production-orders.consume-remnant');
    Route::get('production-orders/{production_order}/available-remnants', [RemnantConsumptionController::class, 'availableRemnants'])->name('production-orders.available-remnants');

    Route::get('production-orders', [ProductionOrderController::class, 'index'])->name('production-orders.index');
    Route::get('production-orders/{production_order}', [ProductionOrderController::class, 'show'])->name('production-orders.show')->whereNumber('production_order');
    Route::get('production-orders/{production_order}/export-pdf', [ProductionOrderController::class, 'exportPdf'])->name('production-orders.export-pdf');
    Route::get('production-orders/{production_order}/export-excel', [ProductionOrderController::class, 'exportExcel'])->name('production-orders.export-excel');
    Route::post('production-orders/{production_order}/start', [ProductionOrderController::class, 'startProduction'])->name('production-orders.start')->whereNumber('production_order');
    Route::post('production-orders/{production_order}/submit-for-review', [ProductionOrderController::class, 'submitForReview'])->name('production-orders.submit-for-review');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('set-current-warehouse', [WarehouseController::class, 'setCurrentWarehouse'])->name('warehouses.set-current');
});

require __DIR__.'/settings.php';
