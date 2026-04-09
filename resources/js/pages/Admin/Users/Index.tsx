import { usePage, Link } from '@inertiajs/react';
import type { FC } from 'react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { name: string }[];
    created_at: string;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const UsersIndex: FC<Props> = ({ users }) => {
    const { flash } = usePage<{
        flash?: { message?: string; error?: string };
    }>().props;
    const [searchTerm, setSearchTerm] = useState('');

    const filteredUsers = users.data.filter(
        (user) =>
            user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.email.toLowerCase().includes(searchTerm.toLowerCase()),
    );

    return (
        <div className="bg-background text-foreground min-h-screen px-4 py-8">
            <div className="mx-auto max-w-6xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-4xl font-bold text-foreground">
                        👥 Gestión de Usuarios
                    </h1>
                    <p className="text-muted-foreground">
                        Administra los usuarios del sistema
                    </p>
                </div>

                {/* Success Message */}
                {flash?.message && (
                    <div className="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4">
                        <p className="text-emerald-700 dark:text-emerald-300">{flash.message}</p>
                    </div>
                )}

                {/* Toolbar */}
                <div className="mb-6 flex gap-4">
                    <input
                        type="text"
                        placeholder="Buscar por nombre o email..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 flex-1 rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                    />
                    <Link
                        href={route('users.create')}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-6 py-2 font-semibold transition"
                    >
                        + Crear Usuario
                    </Link>
                </div>

                {/* Users Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <table className="w-full">
                        <thead className="border-b border-border bg-muted/60">
                            <tr>
                                <th className="text-foreground px-6 py-3 text-left text-sm font-semibold">
                                    Nombre
                                </th>
                                <th className="text-foreground px-6 py-3 text-left text-sm font-semibold">
                                    Email
                                </th>
                                <th className="text-foreground px-6 py-3 text-left text-sm font-semibold">
                                    Rol
                                </th>
                                <th className="text-foreground px-6 py-3 text-left text-sm font-semibold">
                                    Creado
                                </th>
                                <th className="text-foreground px-6 py-3 text-center text-sm font-semibold">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredUsers.length > 0 ? (
                                filteredUsers.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-border hover:bg-muted/35"
                                    >
                                        <td className="text-foreground px-6 py-4 text-sm font-medium">
                                            {user.name}
                                        </td>
                                        <td className="text-muted-foreground px-6 py-4 text-sm">
                                            {user.email}
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            <span className="bg-primary/12 text-primary inline-block rounded-full px-3 py-1 text-xs font-semibold">
                                                {user.roles[0]?.name ||
                                                    'Sin rol'}
                                            </span>
                                        </td>
                                        <td className="text-muted-foreground px-6 py-4 text-sm">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString('es-ES')}
                                        </td>
                                        <td className="space-x-2 px-6 py-4 text-center">
                                            <Link
                                                href={route(
                                                    'users.edit',
                                                    user.id,
                                                )}
                                                className="text-primary rounded bg-primary/15 px-3 py-1 text-sm transition hover:bg-primary/25"
                                            >
                                                Editar
                                            </Link>
                                            <Link
                                                href={route(
                                                    'users.destroy',
                                                    user.id,
                                                )}
                                                method="delete"
                                                as="button"
                                                className="text-destructive rounded bg-destructive/15 px-3 py-1 text-sm transition hover:bg-destructive/25"
                                                onClick={() =>
                                                    confirm(
                                                        '¿Estás seguro de que deseas eliminar este usuario?',
                                                    )
                                                }
                                            >
                                                Eliminar
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-6 py-8 text-center"
                                    >
                                        No hay usuarios disponibles
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default UsersIndex;
