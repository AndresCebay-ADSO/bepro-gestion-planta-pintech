import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BellRing,
    Boxes,
    Calculator,
    ClipboardList,
    FileText,
    Factory,
    FlaskConical,
    KeyRound,
    LayoutGrid,
    Package,
    QrCode,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Users,
    WalletCards,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminCostsIndex } from '@/routes/admin/costs';
import { index as alertsIndex } from '@/routes/alerts';
import { edit as editAppearance } from '@/routes/appearance';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as clientsIndex } from '@/routes/clients';
import { index as finishedInventoryIndex } from '@/routes/finished-inventory';
import { index as finishedInventoryMovementsIndex } from '@/routes/finished-inventory-movements';
import { index as formulasIndex } from '@/routes/formulas';
import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import { index as paintDevIndex } from '@/routes/paint-development-requests';
import { index as pricesIndex } from '@/routes/prices';
import { index as remnantsIndex } from '@/routes/production/remnants';
import { index as productionOrdersIndex } from '@/routes/production-orders';
import { index as productsIndex } from '@/routes/products';
import { index as qrCodesIndex } from '@/routes/qr-codes';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as rawMaterialsIndex } from '@/routes/raw-materials';
import { index as rolesIndex } from '@/routes/roles';
import { index as salesOrdersIndex } from '@/routes/sales-orders';
import { index as usersIndex } from '@/routes/users';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { NavGroup } from '@/types/navigation';
import type { Permission } from '@/types/permissions';

const navigationGroups: NavGroup[] = [
    {
        label: 'OPERACIÓN',
        items: [
            {
                title: 'Dashboard',
                allowedPermissions: ['dashboard.view'],
                href: dashboard().url,
                icon: LayoutGrid,
            },
            {
                title: 'Materias Primas',
                allowedPermissions: ['raw_materials.view'],
                href: rawMaterialsIndex().url,
                icon: Boxes,
            },
            {
                title: 'Movimientos',
                allowedPermissions: ['inventory_movements.view'],
                href: inventoryMovementsIndex().url,
                icon: ArrowLeftRight,
            },
            {
                title: 'Inventario PT',
                allowedPermissions: ['finished_inventory.view'],
                href: finishedInventoryIndex().url,
                icon: Package,
            },
            {
                title: 'Movimientos PT',
                allowedPermissions: ['finished_inventory_movements.view'],
                href: finishedInventoryMovementsIndex().url,
                icon: ArrowLeftRight,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Bodegas',
                allowedPermissions: ['warehouses.view'],
                href: warehousesIndex().url,
                icon: Warehouse,
            },
            {
                title: 'Portafolio de Productos',
                allowedPermissions: ['products.view'],
                href: productsIndex().url,
                icon: Factory,
            },
            {
                title: 'Fórmulas',
                allowedPermissions: ['formulas.view'],
                href: formulasIndex().url,
                icon: FlaskConical,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Órdenes de Producción',
                allowedPermissions: ['production_orders.view'],
                href: productionOrdersIndex().url,
                icon: ClipboardList,
            },
            {
                title: 'Saldos de Producción',
                allowedPermissions: ['production_remnants.view'],
                href: remnantsIndex().url,
                icon: FlaskConical,
            },
        ],
    },
    {
        label: 'FINANZAS',
        items: [
            {
                title: 'Costos',
                allowedPermissions: ['costs.view'],
                href: adminCostsIndex().url,
                icon: Calculator,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Lista de Precios',
                allowedPermissions: ['price_lists.view'],
                href: pricesIndex().url,
                icon: WalletCards,
            },
            {
                title: 'Clientes',
                allowedPermissions: ['clients.view'],
                href: clientsIndex().url,
                icon: Users,
            },
            {
                title: 'Cotizaciones',
                allowedPermissions: [
                    'quotations.view_own',
                    'quotations.view_all',
                ],
                href: quotationsIndex().url,
                icon: FileText,
            },
            {
                title: 'Desarrollo de pinturas',
                allowedPermissions: [
                    'paint_development_requests.view_own',
                    'paint_development_requests.view_all',
                ],
                href: paintDevIndex().url,
                icon: FlaskConical,
            },
            {
                title: 'Pedidos',
                allowedPermissions: [
                    'sales_orders.view_own',
                    'sales_orders.view_all',
                ],
                href: salesOrdersIndex().url,
                icon: ShoppingCart,
            },
        ],
    },
    {
        label: 'CONTROLES',
        items: [
            {
                title: 'Alertas',
                allowedPermissions: ['alerts.view'],
                href: alertsIndex().url,
                icon: BellRing,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Códigos QR',
                allowedPermissions: ['qr_codes.view'],
                href: qrCodesIndex().url,
                icon: QrCode,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Reportes',
                href: '/reports',
                icon: LayoutGrid,
                allowedPermissions: ['production_orders.create'],
                unauthorizedBehavior: 'hide',
                disabled: true,
                disabledLabel: 'Módulo en desarrollo',
            },
        ],
    },
    {
        label: 'SISTEMA',
        items: [
            {
                title: 'Usuarios',
                allowedPermissions: ['users.view'],
                href: usersIndex().url,
                icon: Users,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Roles',
                allowedPermissions: ['roles.view'],
                href: rolesIndex().url,
                icon: KeyRound,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Auditoría',
                allowedPermissions: ['audit_logs.view'],
                href: auditLogsIndex().url,
                icon: ShieldCheck,
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Configuración',
                href: editAppearance().url,
                icon: Settings,
                unauthorizedBehavior: 'hide',
            },
        ],
    },
];

/**
 * Filtra el menú por permisos (docs/MATRIZ_RBAC.md). Un ítem sin `allowedPermissions` es visible para todo
 * usuario con sesión (p. ej. Configuración: perfil y apariencia).
 */
function buildSidebarGroups(userPermissions: Permission[]): NavGroup[] {
    return navigationGroups
        .map((group) => {
            const items = group.items
                .map((item) => {
                    if (!item.allowedPermissions?.length) {
                        return item;
                    }

                    const hasPermission = item.allowedPermissions.some(
                        (permission) => userPermissions.includes(permission),
                    );

                    if (hasPermission) {
                        return item;
                    }

                    if (item.unauthorizedBehavior === 'disable') {
                        return { ...item, disabled: true };
                    }

                    return null;
                })
                .filter(
                    (
                        item,
                    ): item is (typeof navigationGroups)[number]['items'][number] =>
                        item !== null,
                );

            return {
                ...group,
                items,
            };
        })
        .filter((group) => group.items.length > 0);
}

export function AppSidebar() {
    const { auth, unresolvedAlertsCount } = usePage().props;
    const userPermissions = auth.user?.permissions ?? [];
    const filteredGroups = buildSidebarGroups(userPermissions).map((group) => ({
        ...group,
        items: group.items.map((item) => {
            if (item.title !== 'Alertas') {
                return item;
            }

            return {
                ...item,
                badge:
                    unresolvedAlertsCount > 0
                        ? unresolvedAlertsCount
                        : undefined,
            };
        }),
    }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={filteredGroups} />
            </SidebarContent>
        </Sidebar>
    );
}
