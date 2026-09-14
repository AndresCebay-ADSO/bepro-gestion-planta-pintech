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
import { index as salesOrdersIndex } from '@/routes/sales-orders';
import { index as usersIndex } from '@/routes/users';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { User, UserRole } from '@/types/auth';
import type { NavGroup } from '@/types/navigation';

const navigationGroups: NavGroup[] = [
    {
        label: 'OPERACIÓN',
        items: [
            {
                title: 'Dashboard',
                allowedPermissions: ['dashboard.view'],
                href: dashboard(),
                icon: LayoutGrid,
                allowedRoles: ['admin', 'produccion', 'comercial', 'operador'],
            },
            {
                title: 'Materias Primas',
                allowedPermissions: ['raw_materials.view'],
                href: rawMaterialsIndex().url,
                icon: Boxes,
                allowedRoles: ['admin', 'produccion'],
            },
            {
                title: 'Movimientos',
                allowedPermissions: ['inventory_movements.view'],
                href: inventoryMovementsIndex().url,
                icon: ArrowLeftRight,
                allowedRoles: ['admin', 'produccion'],
            },
            {
                title: 'Inventario PT',
                allowedPermissions: ['finished_inventory.view'],
                href: finishedInventoryIndex().url,
                icon: Package,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Movimientos PT',
                allowedPermissions: ['finished_inventory_movements.view'],
                href: finishedInventoryMovementsIndex().url,
                icon: ArrowLeftRight,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Bodegas',
                allowedPermissions: ['warehouses.view'],
                href: warehousesIndex().url,
                icon: Warehouse,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Portafolio de Productos',
                allowedPermissions: ['products.view'],
                href: productsIndex().url,
                icon: Factory,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Fórmulas',
                allowedPermissions: ['formulas.view'],
                href: formulasIndex().url,
                icon: FlaskConical,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Órdenes de Producción',
                href: productionOrdersIndex().url,
                icon: ClipboardList,
                allowedRoles: ['admin', 'produccion', 'operador'],
            },
            {
                title: 'Saldos de Producción',
                allowedPermissions: ['production_remnants.view'],
                href: remnantsIndex().url,
                icon: FlaskConical,
                allowedRoles: ['admin', 'produccion', 'operador'],
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
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Lista de Precios',
                allowedPermissions: ['price_lists.view'],
                href: pricesIndex().url,
                icon: WalletCards,
                allowedRoles: ['admin', 'comercial'],
            },
            {
                title: 'Clientes',
                allowedPermissions: ['clients.view'],
                href: clientsIndex().url,
                icon: Users,
                allowedRoles: ['admin', 'comercial'],
            },
            {
                title: 'Cotizaciones',
                href: quotationsIndex().url,
                icon: FileText,
                allowedRoles: ['admin', 'comercial'],
            },
            {
                title: 'Desarrollo de pinturas',
                href: paintDevIndex().url,
                icon: FlaskConical,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Pedidos',
                href: salesOrdersIndex().url,
                icon: ShoppingCart,
                allowedRoles: ['admin', 'produccion', 'comercial'],
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
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Códigos QR',
                allowedPermissions: ['qr_codes.view'],
                href: qrCodesIndex().url,
                icon: QrCode,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Reportes',
                href: '/reports',
                icon: LayoutGrid,
                allowedRoles: ['admin', 'produccion'],
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
                href: usersIndex(),
                icon: Users,
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Auditoría',
                allowedPermissions: ['audit_logs.view'],
                href: auditLogsIndex(),
                icon: ShieldCheck,
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Configuración',
                href: editAppearance(),
                icon: Settings,
                allowedRoles: ['admin', 'operador'],
                unauthorizedBehavior: 'hide',
            },
        ],
    },
];

function extractUserRoles(user: User | null): UserRole[] {
    if (!user) {
        return [];
    }

    const roleCandidates = new Set<string>();

    if (Array.isArray(user.role_names)) {
        user.role_names.forEach((role) => roleCandidates.add(String(role)));
    }

    if (Array.isArray(user.roles)) {
        user.roles.forEach((role) => {
            if (typeof role === 'string') {
                roleCandidates.add(role);

                return;
            }

            if (role?.name) {
                roleCandidates.add(String(role.name));
            }
        });
    }

    if (typeof user.role === 'string') {
        roleCandidates.add(user.role);
    }

    return Array.from(roleCandidates).filter((role): role is UserRole =>
        ['admin', 'produccion', 'comercial', 'operador'].includes(role),
    );
}

function buildSidebarGroups(
    userRoles: UserRole[],
    userPermissions: string[],
): NavGroup[] {
    if (userRoles.length === 0 && userPermissions.length === 0) {
        return navigationGroups;
    }

    return navigationGroups
        .map((group) => {
            const items = group.items
                .map((item) => {
                    // Los módulos migrados a permisos (tarea 2.2) deciden por permiso.
                    if (item.allowedPermissions?.length) {
                        const hasPermission = item.allowedPermissions.some(
                            (permission) =>
                                userPermissions.includes(permission),
                        );

                        if (hasPermission) {
                            return item;
                        }

                        if (item.unauthorizedBehavior === 'disable') {
                            return { ...item, disabled: true };
                        }

                        return null;
                    }

                    if (!item.allowedRoles?.length) {
                        return item;
                    }

                    const isAllowed = item.allowedRoles.some((allowedRole) =>
                        userRoles.includes(allowedRole),
                    );

                    if (isAllowed) {
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
    const { auth, unresolvedAlertsCount = 0 } = usePage<{
        unresolvedAlertsCount?: number;
    }>().props;
    const userRoles = extractUserRoles(auth.user);
    const userPermissions = auth.user?.permissions ?? [];
    const filteredGroups = buildSidebarGroups(userRoles, userPermissions).map(
        (group) => ({
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
        }),
    );

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
