import { router, Link } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface Role {
    id: number;
    name: string;
}

interface Props {
    roles: Role[];
}

const UsersCreate: FC<Props> = ({ roles }) => {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'produccion',
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

        router.post(route('users.store'), formData, {
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
        <div className="bg-background text-foreground min-h-screen px-4 py-8">
            <div className="mx-auto max-w-2xl">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href={route('users.index')}
                        className="text-primary hover:text-primary/80 mb-4 inline-block"
                    >
                        ← Volver a Usuarios
                    </Link>
                    <h1 className="mb-2 text-4xl font-bold text-foreground">
                        ➕ Crear Nuevo Usuario
                    </h1>
                    <p className="text-muted-foreground">
                        Agrega un nuevo usuario al sistema
                    </p>
                </div>

                {/* Form */}
                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-lg border border-border bg-card p-8 shadow-sm"
                >
                    {/* Name */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Nombre Completo
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={formData.name}
                            onChange={handleChange}
                            placeholder="Ej: Juan Pérez"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.name && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.name[0]}
                            </p>
                        )}
                    </div>

                    {/* Email */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            placeholder="juan@pintech.com"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.email && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.email[0]}
                            </p>
                        )}
                    </div>

                    {/* Role */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Rol
                        </label>
                        <select
                            name="role"
                            value={formData.role}
                            onChange={handleChange}
                            className="border-input bg-background text-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        >
                            {roles.map((role) => (
                                <option key={role.id} value={role.name}>
                                    {role.name.charAt(0).toUpperCase() +
                                        role.name.slice(1)}
                                </option>
                            ))}
                        </select>
                        {errors.role && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.role[0]}
                            </p>
                        )}
                    </div>

                    {/* Password */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Contraseña
                        </label>
                        <input
                            type="password"
                            name="password"
                            value={formData.password}
                            onChange={handleChange}
                            placeholder="Mínimo 8 caracteres"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.password && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.password[0]}
                            </p>
                        )}
                    </div>

                    {/* Password Confirmation */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Confirmar Contraseña
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            value={formData.password_confirmation}
                            onChange={handleChange}
                            placeholder="Repite la contraseña"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.password_confirmation && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.password_confirmation[0]}
                            </p>
                        )}
                    </div>

                    {/* Buttons */}
                    <div className="flex gap-4 pt-6">
                        <button
                            type="submit"
                            disabled={loading}
                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex-1 rounded-lg px-6 py-2 font-semibold transition disabled:opacity-50"
                        >
                            {loading ? 'Creando...' : 'Crear Usuario'}
                        </button>
                        <Link
                            href={route('users.index')}
                            className="bg-secondary text-secondary-foreground hover:bg-secondary/80 flex-1 rounded-lg px-6 py-2 text-center font-semibold transition"
                        >
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default UsersCreate;
