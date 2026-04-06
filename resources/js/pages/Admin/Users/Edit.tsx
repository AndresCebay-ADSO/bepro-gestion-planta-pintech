import { router, Link } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { name: string }[];
}

interface Role {
    id: number;
    name: string;
}

interface Props {
    user: User;
    roles: Role[];
}

const UsersEdit: FC<Props> = ({ user, roles }) => {
    const [formData, setFormData] = useState({
        name: user.name,
        email: user.email,
        role: user.roles[0]?.name || 'produccion',
    });

    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [loading, setLoading] = useState(false);

    const handleChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        const { name, value } = e.target;
        setFormData((prev) => ({ ...prev, [name]: value }));

        if (errors[name]) {
            setErrors((prev) => ({ ...prev, [name]: [] }));
        }
    };

    const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setLoading(true);

        router.put(route('users.update', user.id), formData, {
            onError: (pageErrors) => {
                setErrors(pageErrors as unknown as Record<string, string[]>);
                setLoading(false);
            },
            onSuccess: () => {
                setLoading(false);
            },
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 px-4 py-8">
            <div className="mx-auto max-w-2xl">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href={route('users.index')}
                        className="mb-4 inline-block text-blue-600 hover:text-blue-800"
                    >
                        ← Volver a Usuarios
                    </Link>
                    <h1 className="mb-2 text-4xl font-bold text-gray-900">
                        ✏️ Editar Usuario
                    </h1>
                    <p className="text-gray-600">
                        Actualiza la información del usuario
                    </p>
                </div>

                {/* Form */}
                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-lg bg-white p-8 shadow"
                >
                    {/* Name */}
                    <div>
                        <label className="mb-2 block text-sm font-semibold text-gray-900">
                            Nombre Completo
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        {errors.name && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.name[0]}
                            </p>
                        )}
                    </div>

                    {/* Email */}
                    <div>
                        <label className="mb-2 block text-sm font-semibold text-gray-900">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        {errors.email && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.email[0]}
                            </p>
                        )}
                    </div>

                    {/* Role */}
                    <div>
                        <label className="mb-2 block text-sm font-semibold text-gray-900">
                            Rol
                        </label>
                        <select
                            name="role"
                            value={formData.role}
                            onChange={handleChange}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            {roles.map((role) => (
                                <option key={role.id} value={role.name}>
                                    {role.name.charAt(0).toUpperCase() +
                                        role.name.slice(1)}
                                </option>
                            ))}
                        </select>
                        {errors.role && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.role[0]}
                            </p>
                        )}
                    </div>

                    {/* Info */}
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <p className="text-sm text-blue-800">
                            💡 Para cambiar la contraseña, el usuario debe usar
                            la opción "Olvidé mi contraseña" en el login.
                        </p>
                    </div>

                    {/* Buttons */}
                    <div className="flex gap-4 pt-6">
                        <button
                            type="submit"
                            disabled={loading}
                            className="flex-1 rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            {loading ? 'Actualizando...' : 'Guardar Cambios'}
                        </button>
                        <Link
                            href={route('users.index')}
                            className="flex-1 rounded-lg bg-gray-300 px-6 py-2 text-center font-semibold text-gray-800 transition hover:bg-gray-400"
                        >
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default UsersEdit;
