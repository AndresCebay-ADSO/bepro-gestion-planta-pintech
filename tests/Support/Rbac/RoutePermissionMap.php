<?php

declare(strict_types=1);

namespace Tests\Support\Rbac;

use App\Enums\Permission;

/**
 * Clasificación de cada ruta nombrada de la aplicación según docs/MATRIZ_RBAC.md.
 *
 * Una entrada puede ser:
 * - un Permission: la ruta exige ese permiso;
 * - una lista de Permission: basta con tener uno (p. ej. `view_own` o `view_all`);
 * - PUBLIC / AUTHENTICATED: acceso sin permiso, con o sin sesión;
 * - TO_REMOVE: la matriz elimina esa funcionalidad; la ruta desaparece en la Fase 2.
 */
final class RoutePermissionMap
{
    public const PUBLIC = 'public';

    public const AUTHENTICATED = 'authenticated';

    public const TO_REMOVE = 'to_remove';

    /**
     * Rutas de paquetes o del framework que no forman parte del control de acceso.
     */
    public const IGNORED_ROUTES = ['boost.browser-logs', 'storage.local', 'storage.local.upload'];

    public const IGNORED_ACTION_PREFIXES = ['Laravel\\Fortify\\'];

    /**
     * Rutas sin nombre permitidas (se identifican por URI).
     */
    public const UNNAMED_URIS = ['settings', 'up'];

    /**
     * @return array<string, Permission|list<Permission>|string>
     */
    public static function routes(): array
    {
        return [
            // Públicas y generales
            'home' => self::PUBLIC,
            'qr.public.show' => self::PUBLIC,
            'qr.public.image' => self::PUBLIC,
            'qr.public.documents.download' => self::PUBLIC,
            'qr.public.product-documents.download' => self::PUBLIC,
            'dashboard' => Permission::DashboardView,
            'profile.edit' => self::AUTHENTICATED,
            'profile.update' => self::AUTHENTICATED,
            'appearance.edit' => self::AUTHENTICATED,
            'warehouses.set-current' => self::AUTHENTICATED,

            // Usuarios y auditoría
            'users.index' => Permission::UsersView,
            'users.create' => Permission::UsersCreate,
            'users.store' => Permission::UsersCreate,
            'users.edit' => Permission::UsersEdit,
            'users.update' => Permission::UsersEdit,
            'users.destroy' => Permission::UsersDelete,
            'audit-logs.index' => Permission::AuditLogsView,

            // Productos
            'products.index' => Permission::ProductsView,
            'products.show' => Permission::ProductsView,
            'products.create' => Permission::ProductsCreate,
            'products.store' => Permission::ProductsCreate,
            'products.edit' => Permission::ProductsEdit,
            'products.update' => Permission::ProductsEdit,
            'products.destroy' => Permission::ProductsDelete,
            'products.variants.store' => Permission::ProductsManageVariants,
            'products.variants.update' => Permission::ProductsManageVariants,
            'products.variants.destroy' => Permission::ProductsManageVariants,
            'products.documents.store' => Permission::ProductsManageDocuments,
            'products.documents.destroy' => Permission::ProductsManageDocuments,
            'products.documents.download' => Permission::ProductsDownloadDocuments,

            // Costos y precios
            'admin.costs.index' => Permission::CostsView,
            'admin.costs.update' => Permission::CostsUpdate,
            'prices.index' => Permission::PriceListsView,

            // Fórmulas
            'formulas.index' => Permission::FormulasView,
            'formulas.show' => Permission::FormulasView,
            'formulas.create' => Permission::FormulasCreate,
            'formulas.store' => Permission::FormulasCreate,
            'formulas.edit' => Permission::FormulasEdit,
            'formulas.update' => Permission::FormulasEdit,
            'formulas.activate' => Permission::FormulasActivate,
            'formulas.destroy' => Permission::FormulasDelete,

            // Materias primas
            'raw-materials.index' => Permission::RawMaterialsView,
            'raw-materials.show' => Permission::RawMaterialsView,
            'raw-materials.create' => Permission::RawMaterialsCreate,
            'raw-materials.store' => Permission::RawMaterialsCreate,
            'raw-materials.edit' => Permission::RawMaterialsEdit,
            'raw-materials.update' => Permission::RawMaterialsEdit,
            'raw-materials.reactivate' => Permission::RawMaterialsReactivate,
            // Desactiva; el borrado físico dentro exige raw_materials.delete. Se separan en 2.7.
            'raw-materials.destroy' => Permission::RawMaterialsDeactivate,

            // Órdenes de producción
            'production-orders.index' => Permission::ProductionOrdersView,
            'production-orders.show' => Permission::ProductionOrdersView,
            'production-orders.create' => Permission::ProductionOrdersCreate,
            'production-orders.store' => Permission::ProductionOrdersCreate,
            'production-orders.start' => Permission::ProductionOrdersOperate,
            'production-orders.line-adjustments.store' => Permission::ProductionOrdersOperate,
            'production-orders.line-adjustments.destroy' => Permission::ProductionOrdersOperate,
            'production-orders.packaging-plans.store' => Permission::ProductionOrdersOperate,
            'production-orders.packaging-plans.destroy' => Permission::ProductionOrdersOperate,
            'production-orders.available-remnants' => Permission::ProductionOrdersOperate,
            'production-orders.consume-remnant' => Permission::ProductionOrdersOperate,
            'production-orders.submit-for-review' => Permission::ProductionOrdersSubmitForReview,
            'production-orders.reject-review' => Permission::ProductionOrdersRejectReview,
            'production-orders.complete' => Permission::ProductionOrdersComplete,
            'production-orders.cancel' => Permission::ProductionOrdersCancel,
            'production-orders.export-pdf' => Permission::ProductionOrdersExport,
            'production-orders.export-excel' => Permission::ProductionOrdersExport,
            'production-orders.preview-costs' => Permission::CostsView,
            'production.remnants.index' => Permission::ProductionRemnantsView,

            // Inventario
            'inventory-movements.index' => Permission::InventoryMovementsView,
            'inventory-movements.show' => Permission::InventoryMovementsView,
            'inventory-movements.store' => Permission::InventoryMovementsCreate,
            'finished-inventory.index' => Permission::FinishedInventoryView,
            'finished-inventory-movements.index' => Permission::FinishedInventoryMovementsView,
            'finished-inventory-movements.show' => Permission::FinishedInventoryMovementsView,
            'finished-inventory-movements.store' => Permission::FinishedInventoryMovementsCreate,

            // Cotizaciones
            'quotations.index' => [Permission::QuotationsViewOwn, Permission::QuotationsViewAll],
            'quotations.show' => [Permission::QuotationsViewOwn, Permission::QuotationsViewAll],
            'quotations.create' => Permission::QuotationsCreate,
            'quotations.store' => Permission::QuotationsCreate,
            'quotations.edit' => Permission::QuotationsEdit,
            'quotations.update' => Permission::QuotationsEdit,
            'quotations.update-status' => Permission::QuotationsUpdateStatus,
            'quotations.convert-to-order' => Permission::QuotationsConvertToOrder,
            'quotations.export-pdf' => Permission::QuotationsExportPdf,

            // Pedidos de venta
            'sales-orders.index' => [Permission::SalesOrdersViewOwn, Permission::SalesOrdersViewAll],
            'sales-orders.show' => [Permission::SalesOrdersViewOwn, Permission::SalesOrdersViewAll],
            'sales-orders.create' => Permission::SalesOrdersCreate,
            'sales-orders.store' => Permission::SalesOrdersCreate,
            'sales-orders.update' => Permission::SalesOrdersEdit,
            'sales-orders.update-status' => Permission::SalesOrdersUpdateStatus,

            // Clientes
            'clients.index' => Permission::ClientsView,
            'clients.create' => Permission::ClientsCreate,
            'clients.store' => Permission::ClientsCreate,
            'clients.edit' => Permission::ClientsEdit,
            'clients.update' => Permission::ClientsEdit,
            'clients.destroy' => Permission::ClientsDelete,

            // Desarrollo de pinturas
            'paint-development-requests.index' => [Permission::PaintDevelopmentRequestsViewOwn, Permission::PaintDevelopmentRequestsViewAll],
            'paint-development-requests.show' => [Permission::PaintDevelopmentRequestsViewOwn, Permission::PaintDevelopmentRequestsViewAll],
            'paint-development-requests.create' => Permission::PaintDevelopmentRequestsCreate,
            'paint-development-requests.store' => Permission::PaintDevelopmentRequestsCreate,
            'paint-development-requests.edit' => Permission::PaintDevelopmentRequestsEdit,
            'paint-development-requests.update' => Permission::PaintDevelopmentRequestsEdit,
            'paint-development-requests.submit' => Permission::PaintDevelopmentRequestsSubmit,
            'paint-development-requests.update-status' => Permission::PaintDevelopmentRequestsUpdateStatus,
            'paint-development-requests.export-pdf' => Permission::PaintDevelopmentRequestsExportPdf,

            // Controles
            'alerts.index' => Permission::AlertsView,
            'alerts.resolve' => Permission::AlertsResolve,
            'qr-codes.index' => Permission::QrCodesView,
            'qr-codes.show' => Permission::QrCodesView,
            'qr-codes.qr-image' => Permission::QrCodesView,
            'qr-codes.documents.download' => Permission::QrCodesView,
            'qr-codes.update' => Permission::QrCodesUpdate,

            // Bodegas
            'warehouses.index' => Permission::WarehousesView,
            'warehouses.show' => Permission::WarehousesView,
            'warehouses.create' => Permission::WarehousesCreate,
            'warehouses.store' => Permission::WarehousesCreate,
            'warehouses.edit' => Permission::WarehousesEdit,
            'warehouses.update' => Permission::WarehousesEdit,
            'warehouses.destroy' => Permission::WarehousesDelete,
            'warehouses.assign-users.form' => Permission::WarehousesAssignUsers,
            'warehouses.assign-users' => Permission::WarehousesAssignUsers,
        ];
    }

    /**
     * Permisos que no protegen ninguna ruta, con el motivo.
     *
     * @return array<string, string>
     */
    public static function permissionsWithoutRoute(): array
    {
        return [
            Permission::UsersManageRoles->value => 'Campo del formulario de usuario.',
            Permission::RolesView->value => 'Rutas nuevas en la tarea 2.4.',
            Permission::RolesCreate->value => 'Rutas nuevas en la tarea 2.4.',
            Permission::RolesEdit->value => 'Rutas nuevas en la tarea 2.4.',
            Permission::RolesDelete->value => 'Rutas nuevas en la tarea 2.4.',
            Permission::CatalogsView->value => 'CRUDs de catálogos en la Fase 3 (3.1, 3.2).',
            Permission::CatalogsCreate->value => 'CRUDs de catálogos en la Fase 3 (3.1, 3.2).',
            Permission::CatalogsEdit->value => 'CRUDs de catálogos en la Fase 3 (3.1, 3.2).',
            Permission::CatalogsDelete->value => 'CRUDs de catálogos en la Fase 3 (3.1, 3.2).',
            Permission::ProductsDeactivate->value => 'Casilla "Producto activo" dentro de products.update.',
            Permission::RawMaterialsDelete->value => 'Se aplica dentro de raw-materials.destroy; endpoint propio en la tarea 2.7.',
            Permission::WarehousesViewAll->value => 'Alcance de datos: bodegas visibles y selector de bodega.',
        ];
    }
}
