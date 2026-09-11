<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Agrupación de permisos por módulo (encabezados de la pantalla de roles).
 */
enum PermissionModule: string
{
    case Dashboard = 'dashboard';
    case Users = 'users';
    case Roles = 'roles';
    case AuditLogs = 'audit_logs';
    case Catalogs = 'catalogs';
    case Products = 'products';
    case Costs = 'costs';
    case Formulas = 'formulas';
    case RawMaterials = 'raw_materials';
    case ProductionOrders = 'production_orders';
    case ProductionRemnants = 'production_remnants';
    case InventoryMovements = 'inventory_movements';
    case FinishedInventory = 'finished_inventory';
    case Quotations = 'quotations';
    case SalesOrders = 'sales_orders';
    case Clients = 'clients';
    case PriceLists = 'price_lists';
    case PaintDevelopmentRequests = 'paint_development_requests';
    case Alerts = 'alerts';
    case QrCodes = 'qr_codes';
    case Warehouses = 'warehouses';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => __('Dashboard'),
            self::Users => __('Usuarios'),
            self::Roles => __('Roles'),
            self::AuditLogs => __('Auditoría'),
            self::Catalogs => __('Catálogos'),
            self::Products => __('Productos'),
            self::Costs => __('Costos'),
            self::Formulas => __('Fórmulas'),
            self::RawMaterials => __('Materias primas'),
            self::ProductionOrders => __('Órdenes de producción'),
            self::ProductionRemnants => __('Saldos de producción'),
            self::InventoryMovements => __('Movimientos de materia prima'),
            self::FinishedInventory => __('Inventario de producto terminado'),
            self::Quotations => __('Cotizaciones'),
            self::SalesOrders => __('Pedidos de venta'),
            self::Clients => __('Clientes'),
            self::PriceLists => __('Listas de precios'),
            self::PaintDevelopmentRequests => __('Desarrollo de pinturas'),
            self::Alerts => __('Alertas'),
            self::QrCodes => __('Códigos QR'),
            self::Warehouses => __('Bodegas'),
        };
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => $permission->module() === $this,
        ));
    }
}
