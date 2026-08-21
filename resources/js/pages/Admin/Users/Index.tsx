import { Link, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Activity, UserPlus } from 'lucide-react';
import type { FC } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Pagination from '@/components/ui/pagination';
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
    roles: { name: string }[];
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
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
    };
}

const UsersIndex: FC<Props> = ({ users, recentActivities, filters, can }) => {
    const { filters: filterState, setFilter, clearFilters } = useFilters({
        routeUrl: usersIndex().url,
        initialFilters: {
            search: filters.search ?? '',
        },
    });

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    };

    return (
        <div className="min-h-screen bg-background p-6">
            <div className="mx-auto max-w-7xl space-y-8">
                {/* HEADER */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Gestión de Usuarios
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Gestiona el acceso, permisos y roles del personal de
                            planta.
                        </p>
                    </div>
                    {can?.create !== false && (
                        <Button
                            asChild
                            className="shrink-0 bg-blue-600 hover:bg-blue-700"
                        >
                            <Link href={usersCreate().url}>
                                <UserPlus className="mr-2 h-4 w-4" />
                                Nuevo Usuario
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
                            placeholder: 'Buscar por nombre o correo...',
                        },
                    ]}
                    filters={filterState}
                    onChange={(name, value) => setFilter(name, value)}
                    onClear={clearFilters}
                />

                {/* GRID */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* TABLE */}
                    <div className="lg:col-span-9">
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-card">
                            <table className="w-full">
                                <thead className="border-b border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">
                                            Usuario
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">
                                            Rol
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">
                                            Último Acceso
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">
                                            Estado
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {users.data.length > 0 ? (
                                        users.data.map((user) => (
                                            <tr
                                                key={user.id}
                                                className="transition-colors hover:bg-slate-50/50 dark:hover:bg-muted/30"
                                            >
                                                {/* USER */}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                            {getInitials(
                                                                user.name,
                                                            )}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-bold text-slate-900 dark:text-slate-100">
                                                                {user.name}
                                                            </p>
                                                            <p className="truncate text-xs text-slate-500">
                                                                {user.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* ROLE */}
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <Badge
                                                        variant="secondary"
                                                        className="font-bold uppercase"
                                                    >
                                                        {user.roles[0]?.name ||
                                                            'Invitado'}
                                                    </Badge>
                                                </td>

                                                {/* LAST LOGIN */}
                                                <td className="px-4 py-3 text-sm whitespace-nowrap text-slate-600 dark:text-slate-400">
                                                    {user.last_login_at
                                                        ? formatDistanceToNow(
                                                              new Date(
                                                                  user.last_login_at,
                                                              ),
                                                              {
                                                                  addSuffix: true,
                                                                  locale: es,
                                                              },
                                                          )
                                                        : 'Nunca'}
                                                </td>

                                                {/* STATUS */}
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`text-xs font-bold ${user.is_active ? 'text-emerald-600' : 'text-slate-400'}`}
                                                    >
                                                        {user.is_active
                                                            ? 'Activo'
                                                            : 'Inactivo'}
                                                    </span>
                                                </td>

                                                {/* ACTIONS */}
                                                <td className="px-4 py-3 text-right">
                                                    <TableActions
                                                        actions={{
                                                            view: false,
                                                            edit: true,
                                                            delete: true,
                                                        }}
                                                        onEdit={() =>
                                                            router.get(
                                                                usersEdit(
                                                                    user.id,
                                                                ).url,
                                                            )
                                                        }
                                                        onDelete={() => {
                                                            if (
                                                                confirm(
                                                                    '¿Eliminar este usuario definitivamente?',
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    usersDestroy(
                                                                        user.id,
                                                                    ).url,
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
                                                className="py-12 text-center text-slate-400"
                                            >
                                                No se encontraron usuarios.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {/* PAGINATION */}
                            <div className="border-t border-slate-100 px-4 py-4 dark:border-slate-800">
                                <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                                    <span className="text-xs font-medium text-slate-500">
                                        Mostrando {users.data.length} de{' '}
                                        {users.total} usuarios
                                    </span>
                                    <Pagination links={users.links} />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ACTIVITY */}
                    <div className="lg:col-span-3">
                        <Card className="bg-[#0a1a32] text-white">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="h-5 w-5 text-blue-400" />
                                    Registro de Actividad
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recentActivities.map((activity) => (
                                    <div key={activity.id} className="mb-4">
                                        <p className="text-xs text-blue-400">
                                            {formatDistanceToNow(
                                                new Date(activity.created_at),
                                                { addSuffix: true, locale: es },
                                            )}
                                        </p>
                                        <p className="text-sm font-bold">
                                            {activity.description}
                                        </p>
                                    </div>
                                ))}
                                <Button asChild className="mt-4 w-full">
                                    <Link href={auditLogsIndex().url}>
                                        VER TODOS LOS LOGS
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default UsersIndex;
