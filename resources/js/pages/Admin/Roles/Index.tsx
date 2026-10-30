import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { FC } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useFilters } from '@/hooks/use-filters';
import {
    create as rolesCreate,
    destroy as rolesDestroy,
    edit as rolesEdit,
    index as rolesIndex,
    show as rolesShow,
} from '@/routes/roles';
import type { RoleFilters } from '@/types/roles';
import type { Paginated } from '@/types/ui';

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
    roles: Paginated<RoleRow>;
    filters: RoleFilters;
    can: {
        create: boolean;
    };
}

const RolesIndex: FC<Props> = ({ roles, filters, can }) => {
    const confirm = useConfirm();
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

    const columns: DataListingColumn<RoleRow>[] = [
        {
            key: 'label',
            header: 'Rol',
            cell: (role) => role.label,
            mobile: 'title',
        },
        {
            key: 'type',
            header: 'Tipo',
            cell: (role) => (
                <Badge variant={role.is_system ? 'secondary' : 'outline'}>
                    {role.is_system ? 'Sistema' : 'Personalizado'}
                </Badge>
            ),
            mobile: 'subtitle',
            className: 'whitespace-nowrap',
        },
        {
            key: 'permissions',
            header: 'Permisos',
            cell: (role) => <FormattedNumber value={role.permissions_count} />,
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
        {
            key: 'users',
            header: 'Usuarios',
            cell: (role) => <FormattedNumber value={role.users_count} />,
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Roles" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Roles"
                    description="Los roles del sistema se gestionan en código y se muestran en solo lectura. Crea roles personalizados para otras combinaciones de permisos."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={rolesCreate().url}>Nuevo rol</Link>
                            </Button>
                        )
                    }
                />

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

                <DataListing
                    rows={roles.data}
                    rowKey={(role) => role.id}
                    rowHref={(role) => rolesShow(role.id).url}
                    rowLabel={(role) => role.label}
                    columns={columns}
                    actions={(role) => [
                        {
                            label: 'Editar',
                            icon: Pencil,
                            href: rolesEdit(role.id).url,
                            hidden: !role.can.update,
                        },
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: async () => {
                                if (
                                    await confirm({
                                        title: `¿Eliminar el rol «${role.label}»?`,
                                        confirmLabel: 'Eliminar',
                                        destructive: true,
                                    })
                                ) {
                                    router.delete(rolesDestroy(role.id).url, {
                                        preserveScroll: true,
                                    });
                                }
                            },
                            hidden: !role.can.delete,
                        },
                    ]}
                    empty="No se encontraron roles."
                />

                <Pagination paginator={roles} />
            </div>
        </>
    );
};

export default RolesIndex;
