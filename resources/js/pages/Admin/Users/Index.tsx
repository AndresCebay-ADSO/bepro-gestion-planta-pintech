import { Link, router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Activity,
    Edit2,
    MoreHorizontal,
    Search,
    ShieldAlert,
    UserPlus,
} from 'lucide-react';
import type { FC } from 'react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import {
    create as usersCreate,
    destroy as usersDestroy,
    edit as usersEdit,
} from '@/routes/users';

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
    };
    recentActivities: ActivityLog[];
}

const UsersIndex: FC<Props> = ({ users, recentActivities }) => {
    const [searchTerm, setSearchTerm] = useState('');

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    };

    const getEventColor = (event: string) => {
        switch (event) {
            case 'created':
                return 'bg-emerald-500';
            case 'updated':
                return 'bg-amber-500';
            case 'deleted':
                return 'bg-rose-500';
            case 'role_changed':
                return 'bg-indigo-500';
            case 'failed_login':
                return 'bg-orange-500';
            default:
                return 'bg-slate-500';
        }
    };

    const filteredUsers = users.data.filter(
        (user) =>
            user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.email.toLowerCase().includes(searchTerm.toLowerCase()),
    );

    return (
        <div className="bg-background min-h-screen p-6">
            <div className="mx-auto max-w-7xl space-y-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Gestión de Usuarios
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Gestiona el acceso, permisos y roles del personal de planta.
                        </p>
                    </div>
                </div>

                {/* Main Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <input
                            type="text"
                            placeholder="Buscar usuario..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-10 text-sm ring-blue-500/20 shadow-sm focus:border-blue-300 focus:ring-4 focus:outline-none dark:border-slate-800 dark:bg-background"
                        />
                    </div>
                    <Button asChild className="shrink-0 bg-blue-600 hover:bg-blue-700">
                        <Link href={usersCreate()}>
                            <UserPlus className="mr-2 h-4 w-4" />
                            Nuevo Usuario
                        </Link>
                    </Button>
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Users Table Column */}
                    <div className="lg:col-span-9">
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-card">
                            <table className="w-full">
                                <thead className="bg-slate-50/50 border-b border-slate-100 dark:bg-muted/50 dark:border-slate-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
                                            Usuario
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
                                            Rol
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
                                            Último Acceso
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
                                            Estado
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {filteredUsers.length > 0 ? (
                                        filteredUsers.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50/50 transition-colors dark:hover:bg-muted/30">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
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
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <Badge variant="secondary" className="uppercase tracking-wider font-bold bg-blue-50 text-blue-700 border-blue-100 hover:bg-blue-100 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-900">
                                                        {user.roles[0]?.name || 'Invitado'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                                    {user.last_login_at
                                                        ? formatDistanceToNow(new Date(user.last_login_at), { addSuffix: true, locale: es })
                                                        : 'Nunca'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2 whitespace-nowrap">
                                                        <div className={`h-2 w-2 rounded-full ${user.is_active ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-slate-300'}`} />
                                                        <span className={`text-xs font-bold ${user.is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400'}`}>
                                                            {user.is_active ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button asChild variant="ghost" size="icon" className="h-8 w-8 text-slate-400 hover:text-blue-600">
                                                            <Link href={usersEdit(user.id)}>
                                                                <Edit2 className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-slate-400 hover:text-rose-600"
                                                            onClick={() => {
                                                                if (confirm('¿Eliminar este usuario definitivamente?')) {
                                                                    router.delete(usersDestroy(user.id));
                                                                }
                                                            }}
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </div>
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
                            
                            {/* Pagination Placeholder */}
                            <div className="border-t border-slate-100 bg-slate-50/30 px-4 py-3 dark:border-slate-800 dark:bg-muted/20">
                                <div className="flex items-center justify-between text-[10px] text-slate-500">
                                    <span>Mostrando {users.data.length} de {users.total}</span>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" disabled className="h-7 px-2 rounded-md bg-white text-[10px] dark:bg-background">Anterior</Button>
                                        <Button variant="outline" size="sm" disabled className="h-7 px-2 rounded-md bg-white text-[10px] dark:bg-background">Siguiente</Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Activity Feed Column */}
                    <div className="lg:col-span-3">
                        <Card className="border-none bg-[#0a1a32] text-white shadow-xl dark:bg-[#050e1a]">
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-bold">
                                    <Activity className="h-5 w-5 text-blue-400" />
                                    Registro de Actividad
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="relative space-y-6 before:absolute before:top-2 before:bottom-2 before:left-[11px] before:w-0.5 before:bg-blue-900/50">
                                    {recentActivities.length > 0 ? (
                                        recentActivities.map((activity) => (
                                            <div key={activity.id} className="relative pl-8">
                                                <div className={`absolute left-0 top-1.5 h-6 w-6 rounded-full border-4 border-[#0a1a32] ${getEventColor(activity.event)} ring-2 ring-white/10`} />
                                                <div className="space-y-1">
                                                    <p className="text-[10px] font-bold uppercase tracking-widest text-blue-400">
                                                        {formatDistanceToNow(new Date(activity.created_at), { addSuffix: true, locale: es })}
                                                    </p>
                                                    <p className="text-sm font-bold text-slate-100 leading-tight">
                                                        {activity.description}
                                                    </p>
                                                    <p className="text-xs text-slate-500 italic">
                                                        Por: {activity.causer?.name || 'Sistema'}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="py-4 text-center text-sm text-slate-500">
                                            No hay actividad reciente.
                                        </p>
                                    )}
                                </div>

                                <Button asChild className="w-full border-blue-900/50 bg-blue-900/20 text-blue-200 hover:bg-blue-900/40">
                                    <Link href={auditLogsIndex()}>
                                        VER TODOS LOS LOGS
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Security Notice / Hint */}
                        <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/30 dark:bg-blue-950/10">
                            <div className="flex gap-3">
                                <ShieldAlert className="h-5 w-5 text-blue-500" />
                                <div className="space-y-1">
                                    <p className="text-xs font-bold text-blue-900 dark:text-blue-300">Auditoría Activa</p>
                                    <p className="text-[10px] text-blue-600 dark:text-blue-500">
                                        Todos los movimientos de roles y cambios de estado son monitoreados para cumplimiento de normas de calidad.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default UsersIndex;
