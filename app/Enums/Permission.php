<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catálogo de permisos del sistema (fuente: docs/MATRIZ_RBAC.md).
 *
 * Formato de la key: `modulo.accion`. En los módulos con dueño (cotizaciones, pedidos,
 * desarrollo de pinturas) no existe `view` a secas: `view_own` ve los registros propios
 * y `view_all` los de todos.
 */
enum Permission: string
{
    // Dashboard
    case DashboardView = 'dashboard.view';

    // Usuarios
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersEdit = 'users.edit';
    case UsersDelete = 'users.delete';
    case UsersManageRoles = 'users.manage_roles';

    // Roles
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesEdit = 'roles.edit';
    case RolesDelete = 'roles.delete';

    // Auditoría
    case AuditLogsView = 'audit_logs.view';

    // Catálogos
    case CatalogsView = 'catalogs.view';
    case CatalogsCreate = 'catalogs.create';
    case CatalogsEdit = 'catalogs.edit';
    case CatalogsDelete = 'catalogs.delete';

    // Productos
    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsEdit = 'products.edit';
    case ProductsDeactivate = 'products.deactivate';
    case ProductsDelete = 'products.delete';
    case ProductsManageVariants = 'products.manage_variants';
    case ProductsManageDocuments = 'products.manage_documents';
    case ProductsDownloadDocuments = 'products.download_documents';

    // Costos
    case CostsView = 'costs.view';
    case CostsUpdate = 'costs.update';

    // Fórmulas
    case FormulasView = 'formulas.view';
    case FormulasCreate = 'formulas.create';
    case FormulasEdit = 'formulas.edit';
    case FormulasActivate = 'formulas.activate';
    case FormulasDelete = 'formulas.delete';

    // Materias primas
    case RawMaterialsView = 'raw_materials.view';
    case RawMaterialsCreate = 'raw_materials.create';
    case RawMaterialsEdit = 'raw_materials.edit';
    case RawMaterialsDeactivate = 'raw_materials.deactivate';
    case RawMaterialsReactivate = 'raw_materials.reactivate';
    case RawMaterialsDelete = 'raw_materials.delete';

    // Órdenes de producción
    case ProductionOrdersView = 'production_orders.view';
    case ProductionOrdersCreate = 'production_orders.create';
    case ProductionOrdersOperate = 'production_orders.operate';
    case ProductionOrdersSubmitForReview = 'production_orders.submit_for_review';
    case ProductionOrdersRejectReview = 'production_orders.reject_review';
    case ProductionOrdersComplete = 'production_orders.complete';
    case ProductionOrdersCancel = 'production_orders.cancel';
    case ProductionOrdersExport = 'production_orders.export';

    // Saldos de producción
    case ProductionRemnantsView = 'production_remnants.view';

    // Movimientos de materia prima
    case InventoryMovementsView = 'inventory_movements.view';
    case InventoryMovementsCreate = 'inventory_movements.create';

    // Inventario de producto terminado
    case FinishedInventoryView = 'finished_inventory.view';
    case FinishedInventoryMovementsView = 'finished_inventory_movements.view';
    case FinishedInventoryMovementsCreate = 'finished_inventory_movements.create';

    // Cotizaciones
    case QuotationsViewOwn = 'quotations.view_own';
    case QuotationsViewAll = 'quotations.view_all';
    case QuotationsCreate = 'quotations.create';
    case QuotationsEdit = 'quotations.edit';
    case QuotationsUpdateStatus = 'quotations.update_status';
    case QuotationsConvertToOrder = 'quotations.convert_to_order';
    case QuotationsExportPdf = 'quotations.export_pdf';

    // Pedidos de venta
    case SalesOrdersViewOwn = 'sales_orders.view_own';
    case SalesOrdersViewAll = 'sales_orders.view_all';
    case SalesOrdersCreate = 'sales_orders.create';
    case SalesOrdersEdit = 'sales_orders.edit';
    case SalesOrdersUpdateStatus = 'sales_orders.update_status';

    // Clientes
    case ClientsView = 'clients.view';
    case ClientsCreate = 'clients.create';
    case ClientsEdit = 'clients.edit';
    case ClientsDeactivate = 'clients.deactivate';
    case ClientsDelete = 'clients.delete';

    // Listas de precios
    case PriceListsView = 'price_lists.view';

    // Desarrollo de pinturas
    case PaintDevelopmentRequestsViewOwn = 'paint_development_requests.view_own';
    case PaintDevelopmentRequestsViewAll = 'paint_development_requests.view_all';
    case PaintDevelopmentRequestsCreate = 'paint_development_requests.create';
    case PaintDevelopmentRequestsEdit = 'paint_development_requests.edit';
    case PaintDevelopmentRequestsSubmit = 'paint_development_requests.submit';
    case PaintDevelopmentRequestsUpdateStatus = 'paint_development_requests.update_status';
    case PaintDevelopmentRequestsExportPdf = 'paint_development_requests.export_pdf';

    // Alertas
    case AlertsView = 'alerts.view';
    case AlertsResolve = 'alerts.resolve';

    // Códigos QR
    case QrCodesView = 'qr_codes.view';
    case QrCodesUpdate = 'qr_codes.update';

    // Bodegas
    case WarehousesView = 'warehouses.view';
    case WarehousesViewAll = 'warehouses.view_all';
    case WarehousesCreate = 'warehouses.create';
    case WarehousesEdit = 'warehouses.edit';
    case WarehousesAssignUsers = 'warehouses.assign_users';
    case WarehousesDelete = 'warehouses.delete';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => __('Ver el dashboard'),
            self::UsersView => __('Ver usuarios'),
            self::UsersCreate => __('Crear usuarios'),
            self::UsersEdit => __('Editar usuarios'),
            self::UsersDelete => __('Eliminar usuarios'),
            self::UsersManageRoles => __('Asignar roles a usuarios'),
            self::RolesView => __('Ver roles'),
            self::RolesCreate => __('Crear roles'),
            self::RolesEdit => __('Editar roles y sus permisos'),
            self::RolesDelete => __('Eliminar roles'),
            self::AuditLogsView => __('Ver la auditoría'),
            self::CatalogsView => __('Ver catálogos'),
            self::CatalogsCreate => __('Crear valores de catálogo'),
            self::CatalogsEdit => __('Editar valores de catálogo'),
            self::CatalogsDelete => __('Eliminar valores de catálogo'),
            self::ProductsView => __('Ver productos'),
            self::ProductsCreate => __('Crear productos'),
            self::ProductsEdit => __('Editar productos'),
            self::ProductsDeactivate => __('Desactivar productos'),
            self::ProductsDelete => __('Eliminar productos'),
            self::ProductsManageVariants => __('Gestionar presentaciones'),
            self::ProductsManageDocuments => __('Gestionar documentos de producto'),
            self::ProductsDownloadDocuments => __('Descargar documentos de producto'),
            self::CostsView => __('Ver costos'),
            self::CostsUpdate => __('Modificar márgenes y parámetros de costo'),
            self::FormulasView => __('Ver fórmulas'),
            self::FormulasCreate => __('Crear fórmulas'),
            self::FormulasEdit => __('Editar fórmulas'),
            self::FormulasActivate => __('Activar versiones de fórmula'),
            self::FormulasDelete => __('Eliminar fórmulas'),
            self::RawMaterialsView => __('Ver materias primas'),
            self::RawMaterialsCreate => __('Crear materias primas'),
            self::RawMaterialsEdit => __('Editar materias primas'),
            self::RawMaterialsDeactivate => __('Desactivar materias primas'),
            self::RawMaterialsReactivate => __('Reactivar materias primas'),
            self::RawMaterialsDelete => __('Eliminar materias primas'),
            self::ProductionOrdersView => __('Ver órdenes de producción'),
            self::ProductionOrdersCreate => __('Crear órdenes de producción'),
            self::ProductionOrdersOperate => __('Operar órdenes de producción'),
            self::ProductionOrdersSubmitForReview => __('Enviar órdenes a revisión'),
            self::ProductionOrdersRejectReview => __('Rechazar revisiones de órdenes'),
            self::ProductionOrdersComplete => __('Completar órdenes de producción'),
            self::ProductionOrdersCancel => __('Cancelar órdenes de producción'),
            self::ProductionOrdersExport => __('Exportar órdenes de producción'),
            self::ProductionRemnantsView => __('Ver saldos de producción'),
            self::InventoryMovementsView => __('Ver movimientos de materia prima'),
            self::InventoryMovementsCreate => __('Registrar movimientos de materia prima'),
            self::FinishedInventoryView => __('Ver inventario de producto terminado'),
            self::FinishedInventoryMovementsView => __('Ver movimientos de producto terminado'),
            self::FinishedInventoryMovementsCreate => __('Registrar movimientos de producto terminado'),
            self::QuotationsViewOwn => __('Ver cotizaciones propias'),
            self::QuotationsViewAll => __('Ver todas las cotizaciones'),
            self::QuotationsCreate => __('Crear cotizaciones'),
            self::QuotationsEdit => __('Editar cotizaciones'),
            self::QuotationsUpdateStatus => __('Cambiar el estado de cotizaciones'),
            self::QuotationsConvertToOrder => __('Convertir cotizaciones en pedido'),
            self::QuotationsExportPdf => __('Exportar cotizaciones a PDF'),
            self::SalesOrdersViewOwn => __('Ver pedidos propios'),
            self::SalesOrdersViewAll => __('Ver todos los pedidos'),
            self::SalesOrdersCreate => __('Crear pedidos'),
            self::SalesOrdersEdit => __('Editar pedidos'),
            self::SalesOrdersUpdateStatus => __('Cambiar el estado de pedidos'),
            self::ClientsView => __('Ver clientes'),
            self::ClientsCreate => __('Crear clientes'),
            self::ClientsEdit => __('Editar clientes'),
            self::ClientsDeactivate => __('Desactivar clientes'),
            self::ClientsDelete => __('Eliminar clientes'),
            self::PriceListsView => __('Ver listas de precios'),
            self::PaintDevelopmentRequestsViewOwn => __('Ver solicitudes de desarrollo propias'),
            self::PaintDevelopmentRequestsViewAll => __('Ver todas las solicitudes de desarrollo'),
            self::PaintDevelopmentRequestsCreate => __('Crear solicitudes de desarrollo'),
            self::PaintDevelopmentRequestsEdit => __('Editar solicitudes de desarrollo'),
            self::PaintDevelopmentRequestsSubmit => __('Enviar solicitudes de desarrollo'),
            self::PaintDevelopmentRequestsUpdateStatus => __('Revisar solicitudes de desarrollo'),
            self::PaintDevelopmentRequestsExportPdf => __('Exportar solicitudes de desarrollo a PDF'),
            self::AlertsView => __('Ver alertas'),
            self::AlertsResolve => __('Resolver alertas'),
            self::QrCodesView => __('Ver códigos QR'),
            self::QrCodesUpdate => __('Editar códigos QR'),
            self::WarehousesView => __('Ver bodegas asignadas'),
            self::WarehousesViewAll => __('Ver todas las bodegas'),
            self::WarehousesCreate => __('Crear bodegas'),
            self::WarehousesEdit => __('Editar bodegas'),
            self::WarehousesAssignUsers => __('Asignar usuarios a bodegas'),
            self::WarehousesDelete => __('Eliminar bodegas'),
        };
    }

    public function module(): PermissionModule
    {
        return match ($this) {
            self::DashboardView => PermissionModule::Dashboard,
            self::UsersView,
            self::UsersCreate,
            self::UsersEdit,
            self::UsersDelete,
            self::UsersManageRoles => PermissionModule::Users,
            self::RolesView,
            self::RolesCreate,
            self::RolesEdit,
            self::RolesDelete => PermissionModule::Roles,
            self::AuditLogsView => PermissionModule::AuditLogs,
            self::CatalogsView,
            self::CatalogsCreate,
            self::CatalogsEdit,
            self::CatalogsDelete => PermissionModule::Catalogs,
            self::ProductsView,
            self::ProductsCreate,
            self::ProductsEdit,
            self::ProductsDeactivate,
            self::ProductsDelete,
            self::ProductsManageVariants,
            self::ProductsManageDocuments,
            self::ProductsDownloadDocuments => PermissionModule::Products,
            self::CostsView,
            self::CostsUpdate => PermissionModule::Costs,
            self::FormulasView,
            self::FormulasCreate,
            self::FormulasEdit,
            self::FormulasActivate,
            self::FormulasDelete => PermissionModule::Formulas,
            self::RawMaterialsView,
            self::RawMaterialsCreate,
            self::RawMaterialsEdit,
            self::RawMaterialsDeactivate,
            self::RawMaterialsReactivate,
            self::RawMaterialsDelete => PermissionModule::RawMaterials,
            self::ProductionOrdersView,
            self::ProductionOrdersCreate,
            self::ProductionOrdersOperate,
            self::ProductionOrdersSubmitForReview,
            self::ProductionOrdersRejectReview,
            self::ProductionOrdersComplete,
            self::ProductionOrdersCancel,
            self::ProductionOrdersExport => PermissionModule::ProductionOrders,
            self::ProductionRemnantsView => PermissionModule::ProductionRemnants,
            self::InventoryMovementsView,
            self::InventoryMovementsCreate => PermissionModule::InventoryMovements,
            self::FinishedInventoryView,
            self::FinishedInventoryMovementsView,
            self::FinishedInventoryMovementsCreate => PermissionModule::FinishedInventory,
            self::QuotationsViewOwn,
            self::QuotationsViewAll,
            self::QuotationsCreate,
            self::QuotationsEdit,
            self::QuotationsUpdateStatus,
            self::QuotationsConvertToOrder,
            self::QuotationsExportPdf => PermissionModule::Quotations,
            self::SalesOrdersViewOwn,
            self::SalesOrdersViewAll,
            self::SalesOrdersCreate,
            self::SalesOrdersEdit,
            self::SalesOrdersUpdateStatus => PermissionModule::SalesOrders,
            self::ClientsView,
            self::ClientsCreate,
            self::ClientsEdit,
            self::ClientsDeactivate,
            self::ClientsDelete => PermissionModule::Clients,
            self::PriceListsView => PermissionModule::PriceLists,
            self::PaintDevelopmentRequestsViewOwn,
            self::PaintDevelopmentRequestsViewAll,
            self::PaintDevelopmentRequestsCreate,
            self::PaintDevelopmentRequestsEdit,
            self::PaintDevelopmentRequestsSubmit,
            self::PaintDevelopmentRequestsUpdateStatus,
            self::PaintDevelopmentRequestsExportPdf => PermissionModule::PaintDevelopmentRequests,
            self::AlertsView,
            self::AlertsResolve => PermissionModule::Alerts,
            self::QrCodesView,
            self::QrCodesUpdate => PermissionModule::QrCodes,
            self::WarehousesView,
            self::WarehousesViewAll,
            self::WarehousesCreate,
            self::WarehousesEdit,
            self::WarehousesAssignUsers,
            self::WarehousesDelete => PermissionModule::Warehouses,
        };
    }

    /**
     * Permisos reservados a SuperAdmin: un rol creado desde la UI nunca puede tenerlos (docs/PLAN_FASE_2_RBAC.md, 2.4).
     */
    public function isReserved(): bool
    {
        return match ($this) {
            self::RolesView,
            self::RolesCreate,
            self::RolesEdit,
            self::RolesDelete,
            self::AuditLogsView,
            self::CatalogsCreate,
            self::CatalogsEdit,
            self::CatalogsDelete,
            self::UsersDelete,
            self::ProductsDelete,
            self::FormulasDelete,
            self::RawMaterialsDelete,
            self::WarehousesDelete => true,
            default => false,
        };
    }

    /**
     * Dependencias directas: un rol no puede tener este permiso sin ellas, porque dejaría pantallas o acciones que
     * responden 403 o, en el caso de los costos, formularios que no se pueden completar.
     *
     * @return array<int, Permission>
     */
    public function requires(): array
    {
        return match ($this) {
            self::UsersCreate,
            self::UsersEdit,
            self::UsersDelete,
            self::UsersManageRoles => [self::UsersView],
            self::RolesCreate,
            self::RolesEdit,
            self::RolesDelete => [self::RolesView],
            // Las entradas de auditoría incluyen precios y costos (B20).
            self::AuditLogsView => [self::CostsView],
            self::CatalogsCreate,
            self::CatalogsEdit,
            self::CatalogsDelete => [self::CatalogsView],
            self::ProductsCreate,
            self::ProductsEdit,
            self::ProductsDelete,
            self::ProductsManageVariants,
            self::ProductsManageDocuments,
            self::ProductsDownloadDocuments => [self::ProductsView],
            // La casilla "Producto activo" vive en el formulario de edición.
            self::ProductsDeactivate => [self::ProductsEdit],
            self::CostsUpdate => [self::CostsView],
            self::FormulasCreate,
            self::FormulasEdit,
            self::FormulasActivate,
            self::FormulasDelete => [self::FormulasView],
            self::RawMaterialsCreate,
            self::RawMaterialsEdit,
            self::RawMaterialsDeactivate,
            self::RawMaterialsReactivate,
            self::RawMaterialsDelete => [self::RawMaterialsView],
            self::ProductionOrdersCreate,
            self::ProductionOrdersOperate,
            self::ProductionOrdersSubmitForReview,
            self::ProductionOrdersRejectReview,
            self::ProductionOrdersComplete,
            self::ProductionOrdersCancel,
            self::ProductionOrdersExport => [self::ProductionOrdersView],
            // Registrar una entrada exige escribir el precio del lote (B19).
            self::InventoryMovementsCreate => [self::InventoryMovementsView, self::CostsView],
            self::FinishedInventoryMovementsView => [self::FinishedInventoryView],
            self::FinishedInventoryMovementsCreate => [self::FinishedInventoryMovementsView],
            self::QuotationsViewAll,
            self::QuotationsCreate,
            self::QuotationsEdit,
            self::QuotationsUpdateStatus,
            self::QuotationsExportPdf => [self::QuotationsViewOwn],
            // QuotationPolicy::convertToOrder exige además crear pedidos.
            self::QuotationsConvertToOrder => [self::QuotationsViewOwn, self::SalesOrdersCreate],
            self::SalesOrdersViewAll,
            self::SalesOrdersCreate,
            self::SalesOrdersEdit,
            self::SalesOrdersUpdateStatus => [self::SalesOrdersViewOwn],
            self::ClientsCreate,
            self::ClientsEdit,
            self::ClientsDelete => [self::ClientsView],
            self::ClientsDeactivate => [self::ClientsEdit],
            self::PaintDevelopmentRequestsViewAll,
            self::PaintDevelopmentRequestsCreate,
            self::PaintDevelopmentRequestsEdit,
            self::PaintDevelopmentRequestsSubmit,
            self::PaintDevelopmentRequestsUpdateStatus,
            self::PaintDevelopmentRequestsExportPdf => [self::PaintDevelopmentRequestsViewOwn],
            self::AlertsResolve => [self::AlertsView],
            self::QrCodesUpdate => [self::QrCodesView],
            self::WarehousesViewAll,
            self::WarehousesCreate,
            self::WarehousesEdit,
            self::WarehousesAssignUsers,
            self::WarehousesDelete => [self::WarehousesView],
            default => [],
        };
    }

    /**
     * Dependencias directas e indirectas (p. ej. `products.deactivate` → `products.edit` → `products.view`).
     *
     * @return array<int, Permission>
     */
    public function dependencies(): array
    {
        $resolved = [];
        $pending = $this->requires();

        while ($pending !== []) {
            $permission = array_shift($pending);

            if (in_array($permission, $resolved, true)) {
                continue;
            }

            $resolved[] = $permission;
            array_push($pending, ...$permission->requires());
        }

        return $resolved;
    }

    /**
     * Roles del sistema que reciben este permiso por defecto.
     *
     * SuperAdmin no aparece: recibe todos los permisos por definición (ver SystemRole).
     *
     * @return array<int, SystemRole>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::DashboardView,
            self::FinishedInventoryView => [
                SystemRole::Admin,
                SystemRole::Production,
                SystemRole::Operator,
                SystemRole::Commercial,
            ],
            self::UsersView,
            self::UsersCreate,
            self::UsersEdit,
            self::UsersManageRoles,
            self::CatalogsView,
            self::ProductsCreate,
            self::ProductsEdit,
            self::ProductsDeactivate,
            self::ProductsManageDocuments,
            self::CostsView,
            self::CostsUpdate,
            self::FormulasView,
            self::FormulasCreate,
            self::FormulasEdit,
            self::FormulasActivate,
            self::RawMaterialsCreate,
            self::RawMaterialsEdit,
            self::RawMaterialsDeactivate,
            self::RawMaterialsReactivate,
            self::ProductionOrdersCancel,
            self::InventoryMovementsCreate,
            self::QuotationsViewAll,
            self::SalesOrdersEdit,
            self::ClientsEdit,
            self::ClientsDeactivate,
            self::ClientsDelete,
            self::PaintDevelopmentRequestsViewAll,
            self::PaintDevelopmentRequestsUpdateStatus,
            self::AlertsResolve,
            self::QrCodesUpdate,
            self::WarehousesViewAll,
            self::WarehousesCreate,
            self::WarehousesEdit,
            self::WarehousesAssignUsers => [
                SystemRole::Admin,
            ],
            self::UsersDelete,
            self::RolesView,
            self::RolesCreate,
            self::RolesEdit,
            self::RolesDelete,
            self::AuditLogsView,
            self::CatalogsCreate,
            self::CatalogsEdit,
            self::CatalogsDelete,
            self::ProductsDelete,
            self::FormulasDelete,
            self::RawMaterialsDelete,
            self::WarehousesDelete => [],
            self::ProductsView,
            self::ProductsDownloadDocuments,
            self::SalesOrdersViewOwn,
            self::QrCodesView,
            self::WarehousesView => [
                SystemRole::Admin,
                SystemRole::Production,
                SystemRole::Commercial,
            ],
            self::ProductsManageVariants,
            self::RawMaterialsView,
            self::ProductionOrdersCreate,
            self::ProductionOrdersRejectReview,
            self::ProductionOrdersComplete,
            self::FinishedInventoryMovementsView,
            self::FinishedInventoryMovementsCreate,
            self::SalesOrdersViewAll,
            self::SalesOrdersUpdateStatus,
            self::AlertsView => [
                SystemRole::Admin,
                SystemRole::Production,
            ],
            self::ProductionOrdersView,
            self::ProductionOrdersOperate,
            self::ProductionOrdersSubmitForReview,
            self::ProductionOrdersExport,
            self::ProductionRemnantsView,
            self::InventoryMovementsView => [
                SystemRole::Admin,
                SystemRole::Production,
                SystemRole::Operator,
            ],
            self::QuotationsViewOwn,
            self::QuotationsCreate,
            self::QuotationsEdit,
            self::QuotationsUpdateStatus,
            self::QuotationsConvertToOrder,
            self::QuotationsExportPdf,
            self::SalesOrdersCreate,
            self::ClientsView,
            self::ClientsCreate,
            self::PriceListsView,
            self::PaintDevelopmentRequestsViewOwn,
            self::PaintDevelopmentRequestsCreate,
            self::PaintDevelopmentRequestsEdit,
            self::PaintDevelopmentRequestsSubmit,
            self::PaintDevelopmentRequestsExportPdf => [
                SystemRole::Admin,
                SystemRole::Commercial,
            ],
        };
    }
}
