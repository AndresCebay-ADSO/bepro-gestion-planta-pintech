import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    Calculator,
    ClipboardList,
    Droplets,
    FileText,
    FlaskConical,
    Handshake,
    LayoutDashboard,
    Package,
    PackageCheck,
    PaintBucket,
    QrCode,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Tags,
    TestTubes,
    TriangleAlert,
    Truck,
    UsersRound,
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
import { formatBadgeCount } from '@/lib/formatters';
import { filterNavItemsByPermissions } from '@/lib/navigation';
import { dashboard } from '@/routes';
import { index as adminCostsIndex } from '@/routes/admin/costs';
import { index as alertsIndex } from '@/routes/alerts';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as clientsIndex } from '@/routes/clients';
import { index as finishedInventoryIndex } from '@/routes/finished-inventory';
import { index as finishedInventoryMovementsIndex } from '@/routes/finished-inventory-movements';
import { index as formulasIndex } from '@/routes/formulas';
import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import { index as paintDevIndex } from '@/routes/paint-development-requests';
import { index as pricesIndex } from '@/routes/prices';
import { index as remnantsIndex } from '@/routes/production/remnants';
import { index as shrinkWrapsIndex } from '@/routes/production/shrink-wraps';
import { index as productionOrdersIndex } from '@/routes/production-orders';
import { index as productsIndex } from '@/routes/products';
import { edit as editProfile } from '@/routes/profile';
import { index as qrCodesIndex } from '@/routes/qr-codes';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as rawMaterialsIndex } from '@/routes/raw-materials';
import { index as rolesIndex } from '@/routes/roles';
import { index as salesOrdersIndex } from '@/routes/sales-orders';
import { index as usersIndex } from '@/routes/users';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { NavGroup } from '@/types/navigation';
import type { Permission } from '@/types/permissions';

// Grupos y orden del menú. El grupo sin título (Dashboard) no pinta cabecera (`nav-main.tsx`).
const navigationGroups: NavGroup[] = [
    {
        label: '',
        items: [
            {
                title: 'Dashboard',
                allowedPermissions: ['dashboard.view'],
                href: dashboard().url,
                icon: LayoutDashboard,
            },
        ],
    },
    {
        label: 'INVENTARIO',
        items: [
            {
                title: 'Materias primas',
                allowedPermissions: ['raw_materials.view'],
                href: rawMaterialsIndex().url,
                icon: Boxes,
            },
            {
                title: 'Movimientos de MP',
                allowedPermissions: ['inventory_movements.view'],
                href: inventoryMovementsIndex().url,
                icon: ArrowLeftRight,
            },
            {
                title: 'Inventario PT',
                allowedPermissions: ['finished_inventory.view'],
                href: finishedInventoryIndex().url,
                icon: PackageCheck,
            },
            {
                title: 'Movimientos de PT',
                allowedPermissions: ['finished_inventory_movements.view'],
                href: finishedInventoryMovementsIndex().url,
                icon: Truck,
            },
            {
                title: 'Bodegas',
                allowedPermissions: ['warehouses.view'],
                href: warehousesIndex().url,
                icon: Warehouse,
            },
        ],
    },
    {
        label: 'PRODUCCIÓN',
        items: [
            {
                title: 'Órdenes de producción',
                allowedPermissions: ['production_orders.view'],
                href: productionOrdersIndex().url,
                icon: ClipboardList,
            },
            {
                title: 'Saldos de producción',
                allowedPermissions: ['production_remnants.view'],
                href: remnantsIndex().url,
                icon: Droplets,
            },
            {
                title: 'Termoencogido',
                allowedPermissions: ['shrink_wraps.view'],
                href: shrinkWrapsIndex().url,
                icon: Package,
            },
            {
                title: 'Fórmulas',
                allowedPermissions: ['formulas.view'],
                href: formulasIndex().url,
                icon: FlaskConical,
            },
        ],
    },
    {
        label: 'COMERCIAL',
        items: [
            {
                title: 'Clientes',
                allowedPermissions: ['clients.view'],
                href: clientsIndex().url,
                icon: Handshake,
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
                title: 'Pedidos',
                allowedPermissions: [
                    'sales_orders.view_own',
                    'sales_orders.view_all',
                ],
                href: salesOrdersIndex().url,
                icon: ShoppingCart,
            },
            {
                title: 'Desarrollo de pinturas',
                allowedPermissions: [
                    'paint_development_requests.view_own',
                    'paint_development_requests.view_all',
                ],
                href: paintDevIndex().url,
                icon: TestTubes,
            },
        ],
    },
    {
        label: 'PRODUCTOS Y PRECIOS',
        items: [
            {
                title: 'Portafolio de productos',
                allowedPermissions: ['products.view'],
                href: productsIndex().url,
                icon: PaintBucket,
            },
            {
                title: 'Costos',
                allowedPermissions: ['costs.view'],
                href: adminCostsIndex().url,
                icon: Calculator,
            },
            {
                title: 'Lista de precios',
                allowedPermissions: ['price_lists.view'],
                href: pricesIndex().url,
                icon: Tags,
            },
        ],
    },
    {
        label: 'CONTROL',
        items: [
            {
                title: 'Alertas',
                allowedPermissions: ['alerts.view'],
                href: alertsIndex().url,
                icon: TriangleAlert,
            },
            {
                title: 'Códigos QR',
                allowedPermissions: ['qr_codes.view'],
                href: qrCodesIndex().url,
                icon: QrCode,
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
                icon: UsersRound,
            },
            {
                title: 'Roles',
                allowedPermissions: ['roles.view'],
                href: rolesIndex().url,
                icon: ShieldCheck,
            },
            {
                title: 'Auditoría',
                allowedPermissions: ['audit_logs.view'],
                href: auditLogsIndex().url,
                icon: ScrollText,
            },
            {
                title: 'Configuración',
                href: editProfile().url,
                icon: Settings,
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
        .map((group) => ({
            ...group,
            items: filterNavItemsByPermissions(group.items, userPermissions),
        }))
        .filter((group) => group.items.length > 0);
}

export function AppSidebar() {
    const { auth, unresolvedAlertsCount } = usePage().props;
    const userPermissions = auth.user?.permissions ?? [];

    // Sin useMemo: React Compiler (babel-plugin-react-compiler) ya memoriza el componente.
    const filteredGroups = buildSidebarGroups(userPermissions).map((group) => ({
        ...group,
        items: group.items.map((item) =>
            // Se identifica por la ruta, no por el título: renombrar el elemento no debe quitar el contador.
            item.href === alertsIndex().url
                ? {
                      ...item,
                      badge:
                          unresolvedAlertsCount > 0
                              ? formatBadgeCount(unresolvedAlertsCount)
                              : undefined,
                  }
                : item,
        ),
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
