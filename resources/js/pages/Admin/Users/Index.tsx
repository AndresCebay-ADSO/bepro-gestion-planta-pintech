import { Head, Link, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Activity, Trash2 } from 'lucide-react';
import type { FC } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Pagination from '@/components/ui/pagination';
import { useConfirm } from '@/hooks/use-confirm';
import { useFilters } from '@/hooks/use-filters';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import {
    create as usersCreate,
    destroy as usersDestroy,
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/users';
import type { PaginationLink } from '@/types/ui';

interface User {
    id: number;
    name: string;
    email: string;
    role_label: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface ActivityLog {
    id: number;
    description: string;
    event: string;
    created_at: string;
    causer?: {
        name: string;
    };
    subject_type: string;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    recentActivities: ActivityLog[];
    filters: Record<string, string | null | undefined>;
    can?: {
        create?: boolean;
        viewActivity?: boolean;
    };
}

const UsersIndex: FC<Props> = ({ users, recentActivities, filters, can }) => {
    const confirm = useConfirm();
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: usersIndex().url,
        initialFilters: {
            search: filters.search ?? '',
        },
    });

    const columns: DataListingColumn<User>[] = [
        {
            key: 'name',
            header: 'Usuario',
            cell: (user) => user.name,
            mobile: 'title',
        },
        {
            key: 'email',
            header: 'Correo',
            cell: (user) => user.email,
            mobile: 'subtitle',
            className: 'text-muted-foreground',
        },
        {
            key: 'role',
            header: 'Rol',
            cell: (user) => (
                <Badge variant="secondary">{user.role_label}</Badge>
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
        {
            key: 'last_login_at',
            header: 'Último acceso',
            cell: (user) =>
                user.last_login_at
                    ? formatDistanceToNow(new Date(user.last_login_at), {
                          addSuffix: true,
                          locale: es,
                      })
                    : 'Nunca',
            mobile: 'meta',
            className: 'whitespace-nowrap text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (user) => <ActiveBadge isActive={user.is_active} />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Usuarios" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Usuarios"
                    description="Gestiona el acceso, permisos y roles del personal de planta."
                    actions={
                        can?.create !== false && (
                            <Button asChild>
                                <Link href={usersCreate().url}>
                                    Nuevo usuario
                                </Link>
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
                            placeholder: 'Buscar por nombre o correo...',
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                {/* min-w-0: sin él, la columna de la grilla se ensancha con la tabla y esta no se desplaza en su caja. */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div className="min-w-0 lg:col-span-9">
                        {/* Sin detalle: el nombre abre la edición. */}
                        <DataListing
                            rows={users.data}
                            rowKey={(user) => user.id}
                            rowHref={(user) =>
                                user.can.update
                                    ? usersEdit(user.id).url
                                    : undefined
                            }
                            rowLabel={(user) => user.name}
                            columns={columns}
                            // El nombre ya abre la edición: el menú no la repite (DESIGN.md §9).
                            actions={(user) => [
                                {
                                    label: 'Eliminar',
                                    icon: Trash2,
                                    destructive: true,
                                    onSelect: async () => {
                                        if (
                                            await confirm({
                                                title: '¿Eliminar este usuario definitivamente?',
                                                confirmLabel: 'Eliminar',
                                                destructive: true,
                                            })
                                        ) {
                                            router.delete(
                                                usersDestroy(user.id).url,
                                            );
                                        }
                                    },
                                    hidden: !user.can.delete,
                                },
                            ]}
                            empty="No se encontraron usuarios."
                        />

                        <div className="mt-4 flex justify-center">
                            <Pagination links={users.links} />
                        </div>
                    </div>

                    {/* ACTIVITY */}
                    {can?.viewActivity && (
                        <div className="lg:col-span-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-5 w-5 text-primary" />
                                        Registro de actividad
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {recentActivities.map((activity) => (
                                        <div key={activity.id} className="mb-4">
                                            <p className="text-xs text-muted-foreground">
                                                {formatDistanceToNow(
                                                    new Date(
                                                        activity.created_at,
                                                    ),
                                                    {
                                                        addSuffix: true,
                                                        locale: es,
                                                    },
                                                )}
                                            </p>
                                            <p className="text-sm font-semibold">
                                                {activity.description}
                                            </p>
                                        </div>
                                    ))}
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="mt-4 w-full"
                                    >
                                        <Link href={auditLogsIndex().url}>
                                            Ver toda la auditoría
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};

export default UsersIndex;
