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
        <div className="min-h-screen bg-gray-50 px-4 py-8">
            <div className="mx-auto max-w-6xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-4xl font-bold text-gray-900">
                        👥 Gestión de Usuarios
                    </h1>
                    <p className="text-gray-600">
                        Administra los usuarios del sistema
                    </p>
                </div>

                {/* Success Message */}
                {flash?.message && (
                    <div className="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                        <p className="text-green-800">{flash.message}</p>
                    </div>
                )}

                {/* Toolbar */}
                <div className="mb-6 flex gap-4">
                    <input
                        type="text"
                        placeholder="Buscar por nombre o email..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    />
                    <Link
                        href={route('users.create')}
                        className="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white transition hover:bg-blue-700"
                    >
                        + Crear Usuario
                    </Link>
                </div>

                {/* Users Table */}
                <div className="overflow-hidden rounded-lg bg-white shadow">
                    <table className="w-full">
                        <thead className="border-b border-gray-200 bg-gray-100">
                            <tr>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Nombre
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Email
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Rol
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Creado
                                </th>
                                <th className="px-6 py-3 text-center text-sm font-semibold text-gray-900">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredUsers.length > 0 ? (
                                filteredUsers.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-gray-200 hover:bg-gray-50"
                                    >
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                            {user.name}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-600">
                                            {user.email}
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            <span className="inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                                {user.roles[0]?.name ||
                                                    'Sin rol'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-600">
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
                                                className="rounded bg-yellow-100 px-3 py-1 text-sm text-yellow-800 transition hover:bg-yellow-200"
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
                                                className="rounded bg-red-100 px-3 py-1 text-sm text-red-800 transition hover:bg-red-200"
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
                                        className="px-6 py-8 text-center text-gray-500"
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
