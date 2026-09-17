import { Link, router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import type { FC } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as rolesCreate,
    destroy as rolesDestroy,
    edit as rolesEdit,
    index as rolesIndex,
    show as rolesShow,
} from '@/routes/roles';
import type { PaginationLink } from '@/types/ui';

interface RoleRow {
    id: number;
    label: string;
    is_system: boolean;
    permissions_count: number;
    users_count: number;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface Props {
    roles: {
        data: RoleRow[];
        total: number;
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
}

const RolesIndex: FC<Props> = ({ roles, filters, can }) => {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: rolesIndex().url,
        initialFilters: {
            search: filters.search ?? '',
        },
    });

    return (
        <div className="min-h-screen bg-background p-6">
            <div className="mx-auto max-w-7xl space-y-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Roles y permisos
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Los roles del sistema se gestionan en código y se
                            muestran en solo lectura. Crea roles personalizados
                            para otras combinaciones de permisos.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild className="shrink-0">
                            <Link href={rolesCreate().url}>
                                <KeyRound className="mr-2 h-4 w-4" />
                                Nuevo rol
                            </Link>
                        </Button>
                    )}
                </div>

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por nombre...',
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-muted-foreground uppercase">
                                        Rol
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-muted-foreground uppercase">
                                        Tipo
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-bold text-muted-foreground uppercase">
                                        Permisos
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-bold text-muted-foreground uppercase">
                                        Usuarios
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-bold text-muted-foreground uppercase">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {roles.data.length > 0 ? (
                                    roles.data.map((role) => (
                                        <tr
                                            key={role.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 text-sm font-bold text-foreground">
                                                {role.label}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <Badge
                                                    variant={
                                                        role.is_system
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {role.is_system
                                                        ? 'Sistema'
                                                        : 'Personalizado'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm text-muted-foreground">
                                                {role.permissions_count}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm text-muted-foreground">
                                                {role.users_count}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <TableActions
                                                    actions={{
                                                        view: true,
                                                        edit: role.can.update,
                                                        delete: role.can.delete,
                                                    }}
                                                    onView={() =>
                                                        router.get(
                                                            rolesShow(
                                                                String(role.id),
                                                            ).url,
                                                        )
                                                    }
                                                    onEdit={() =>
                                                        router.get(
                                                            rolesEdit(
                                                                String(role.id),
                                                            ).url,
                                                        )
                                                    }
                                                    onDelete={() => {
                                                        if (
                                                            confirm(
                                                                `¿Eliminar el rol "${role.label}"?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                rolesDestroy(
                                                                    String(
                                                                        role.id,
                                                                    ),
                                                                ).url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            No se encontraron roles.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-border px-4 py-4">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <span className="text-xs font-medium text-muted-foreground">
                                Mostrando {roles.data.length} de {roles.total}{' '}
                                roles
                            </span>
                            <Pagination links={roles.links} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default RolesIndex;
