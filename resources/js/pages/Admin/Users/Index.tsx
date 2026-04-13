import { Link, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Activity,
    Search,
    UserPlus,
} from 'lucide-react';
import type { FC, FormEvent } from 'react';
import { useState } from 'react';

import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Pagination from '@/components/ui/pagination';
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
    filters: {
        search?: string;
    };
}

const UsersIndex: FC<Props> = ({ users, recentActivities, filters }) => {

    // ✅ SEARCH STATE DESDE BACKEND
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        router.get(
            usersIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    };

    return (
        <div className="bg-background min-h-screen p-6">
            <div className="mx-auto max-w-7xl space-y-8">

                {/* HEADER */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Gestión de Usuarios
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Gestiona el acceso, permisos y roles del personal de planta.
                        </p>
                    </div>
                    <Button asChild className="shrink-0 bg-blue-600 hover:bg-blue-700">
                        <Link href={usersCreate()}>
                            <UserPlus className="mr-2 h-4 w-4" />
                            Nuevo Usuario
                        </Link>
                    </Button>
                </div>

                {/* SEARCH */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form onSubmit={handleSearch} className="relative w-full max-w-sm">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <input
                            type="text"
                            placeholder="Buscar usuario..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-10 text-sm ring-blue-500/20 shadow-sm focus:border-blue-300 focus:ring-4 focus:outline-none dark:border-slate-800 dark:bg-background"
                        />
                    </form>
                </div>

                {/* GRID */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">

                    {/* TABLE */}
                    <div className="lg:col-span-9">
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-card">
                            <table className="w-full">
                                <thead className="bg-slate-50/50 border-b border-slate-100 dark:bg-muted/50 dark:border-slate-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Usuario</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Rol</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Último Acceso</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Estado</th>
                                        <th className="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {users.data.length > 0 ? (
                                        users.data.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50/50 transition-colors dark:hover:bg-muted/30">

                                                {/* USER */}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                            {getInitials(user.name)}
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
                                                    <Badge variant="secondary" className="uppercase font-bold">
                                                        {user.roles[0]?.name || 'Invitado'}
                                                    </Badge>
                                                </td>

                                                {/* LAST LOGIN */}
                                                <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                                    {user.last_login_at
                                                        ? formatDistanceToNow(new Date(user.last_login_at), { addSuffix: true, locale: es })
                                                        : 'Nunca'}
                                                </td>

                                                {/* STATUS */}
                                                <td className="px-4 py-3">
                                                    <span className={`text-xs font-bold ${user.is_active ? 'text-emerald-600' : 'text-slate-400'}`}>
                                                        {user.is_active ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>

                                                {/* ACTIONS */}
                                                <td className="px-4 py-3 text-right">
                                                    <TableActions
                                                        actions={{ view: false, edit: true, delete: true }}
                                                        onEdit={() => router.get(usersEdit(user.id))}
                                                        onDelete={() => {
                                                            if (confirm('¿Eliminar este usuario definitivamente?')) {
                                                                router.delete(usersDestroy(user.id));
                                                            }
                                                        }}
                                                    />
                                                </td>

                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="py-12 text-center text-slate-400">
                                                No se encontraron usuarios.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {/* PAGINATION */}
                            <div className="border-t border-slate-100 px-4 py-4 dark:border-slate-800">
                                <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <span className="text-xs text-slate-500 font-medium">
                                        Mostrando {users.data.length} de {users.total} usuarios
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
                                            {formatDistanceToNow(new Date(activity.created_at), { addSuffix: true, locale: es })}
                                        </p>
                                        <p className="text-sm font-bold">{activity.description}</p>
                                    </div>
                                ))}
                                <Button asChild className="w-full mt-4">
                                    <Link href={auditLogsIndex()}>
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