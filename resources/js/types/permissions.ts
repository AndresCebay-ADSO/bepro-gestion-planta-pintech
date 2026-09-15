/**
 * Permisos del sistema: espejo de `App\Enums\Permission` (docs/MATRIZ_RBAC.md).
 *
 * `PermissionRegistryTest` falla si esta lista y el enum difieren: al añadir, renombrar o retirar un permiso
 * en el backend hay que actualizarla aquí, y TypeScript señalará cada uso afectado.
 */
export type Permission =
    // Dashboard
    | 'dashboard.view'
    // Usuarios
    | 'users.view'
    | 'users.create'
    | 'users.edit'
    | 'users.delete'
    | 'users.manage_roles'
    // Roles
    | 'roles.view'
    | 'roles.create'
    | 'roles.edit'
    | 'roles.delete'
    // Auditoría
    | 'audit_logs.view'
    // Catálogos
    | 'catalogs.view'
    | 'catalogs.create'
    | 'catalogs.edit'
    | 'catalogs.delete'
    // Productos
    | 'products.view'
    | 'products.create'
    | 'products.edit'
    | 'products.deactivate'
    | 'products.delete'
    | 'products.manage_variants'
    | 'products.manage_documents'
    | 'products.download_documents'
    // Costos
    | 'costs.view'
    | 'costs.update'
    // Fórmulas
    | 'formulas.view'
    | 'formulas.create'
    | 'formulas.edit'
    | 'formulas.activate'
    | 'formulas.delete'
    // Materias primas
    | 'raw_materials.view'
    | 'raw_materials.create'
    | 'raw_materials.edit'
    | 'raw_materials.deactivate'
    | 'raw_materials.reactivate'
    | 'raw_materials.delete'
    // Órdenes de producción
    | 'production_orders.view'
    | 'production_orders.create'
    | 'production_orders.operate'
    | 'production_orders.submit_for_review'
    | 'production_orders.reject_review'
    | 'production_orders.complete'
    | 'production_orders.cancel'
    | 'production_orders.export'
    // Saldos de producción
    | 'production_remnants.view'
    // Movimientos de materia prima
    | 'inventory_movements.view'
    | 'inventory_movements.create'
    // Inventario de producto terminado
    | 'finished_inventory.view'
    | 'finished_inventory_movements.view'
    | 'finished_inventory_movements.create'
    // Cotizaciones
    | 'quotations.view_own'
    | 'quotations.view_all'
    | 'quotations.create'
    | 'quotations.edit'
    | 'quotations.update_status'
    | 'quotations.convert_to_order'
    | 'quotations.export_pdf'
    // Pedidos de venta
    | 'sales_orders.view_own'
    | 'sales_orders.view_all'
    | 'sales_orders.create'
    | 'sales_orders.edit'
    | 'sales_orders.update_status'
    // Clientes
    | 'clients.view'
    | 'clients.create'
    | 'clients.edit'
    | 'clients.delete'
    // Listas de precios
    | 'price_lists.view'
    // Desarrollo de pinturas
    | 'paint_development_requests.view_own'
    | 'paint_development_requests.view_all'
    | 'paint_development_requests.create'
    | 'paint_development_requests.edit'
    | 'paint_development_requests.submit'
    | 'paint_development_requests.update_status'
    | 'paint_development_requests.export_pdf'
    // Alertas
    | 'alerts.view'
    | 'alerts.resolve'
    // Códigos QR
    | 'qr_codes.view'
    | 'qr_codes.update'
    // Bodegas
    | 'warehouses.view'
    | 'warehouses.view_all'
    | 'warehouses.create'
    | 'warehouses.edit'
    | 'warehouses.assign_users'
    | 'warehouses.delete';
