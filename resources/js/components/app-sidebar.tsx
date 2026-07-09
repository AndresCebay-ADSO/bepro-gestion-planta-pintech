import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BellRing,
    Boxes,
    Calculator,
    ClipboardList,
    Factory,
    FlaskConical,
    LayoutGrid,
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
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as clientsIndex } from '@/routes/clients';
import { index as formulasIndex } from '@/routes/formulas';
import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import { index as pricesIndex } from '@/routes/prices';
import { index as productionIndex } from '@/routes/production';
import { index as remnantsIndex } from '@/routes/production/remnants';
import { index as productionOrdersIndex } from '@/routes/production-orders';
import { index as productsIndex } from '@/routes/products';
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
                href: dashboard(),
                icon: LayoutGrid,
                allowedRoles: ['admin', 'produccion', 'comercial', 'operador'],
            },
            {
                title: 'Materias Primas',
                href: rawMaterialsIndex().url,
                icon: Boxes,
                allowedRoles: ['admin', 'produccion'],
            },
            {
                title: 'Movimientos',
                href: inventoryMovementsIndex().url,
                icon: ArrowLeftRight,
                allowedRoles: ['admin', 'produccion'],
            },
            {
                title: 'Bodegas',
                href: warehousesIndex().url,
                icon: Warehouse,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Portafolio de Productos',
                href: productsIndex().url,
                icon: Factory,
                allowedRoles: ['admin', 'produccion', 'comercial'],
            },
            {
                title: 'Fórmulas',
                href: formulasIndex().url,
                icon: FlaskConical,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Centro de Producción',
                href: productionIndex().url,
                icon: Factory,
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
                href: adminCostsIndex().url,
                icon: Calculator,
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Lista de Precios',
                href: pricesIndex().url,
                icon: WalletCards,
                allowedRoles: ['admin', 'comercial'],
            },
            {
                title: 'Clientes',
                href: clientsIndex().url,
                icon: Users,
                allowedRoles: ['admin', 'comercial'],
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
                href: alertsIndex().url,
                icon: BellRing,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Códigos QR',
                href: '/qr-codes',
                icon: QrCode,
                allowedRoles: ['admin', 'produccion'],
                unauthorizedBehavior: 'hide',
                disabled: true,
                disabledLabel: 'Módulo en desarrollo',
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
                href: usersIndex(),
                icon: Users,
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Auditoría',
                href: auditLogsIndex(),
                icon: ShieldCheck,
                allowedRoles: ['admin'],
                unauthorizedBehavior: 'hide',
            },
            {
                title: 'Configuración',
                href: '/settings/appearance',
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

function buildSidebarGroups(userRoles: UserRole[]): NavGroup[] {
    if (userRoles.length === 0) {
        return navigationGroups;
    }

    return navigationGroups
        .map((group) => {
            const items = group.items
                .map((item) => {
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
    const filteredGroups = buildSidebarGroups(userRoles).map((group) => ({
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
