import { useForm, Link } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';
import { route } from 'ziggy-js';

import PasswordInput from '@/components/password-input';

interface Role {
    id: number;
    name: string;
}

interface Props {
    roles: Role[];
}

const UsersCreate: FC<Props> = ({ roles }) => {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles.find(r => r.name === 'produccion')?.name || 'produccion',
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => reset('password', 'password_confirmation'),
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
                        ← Volver a Gestión de Usuarios
                    </Link>
                    <h1 className="mb-2 text-4xl font-bold text-foreground">
                        Crear Nuevo Usuario
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
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Ej: Juan Pérez"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.name && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.name}
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
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="juan@pintech.com"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.email && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.email}
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
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
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
                                {errors.role}
                            </p>
                        )}
                    </div>

                    {/* Password */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Contraseña
                        </label>
                        <PasswordInput
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Mínimo 8 caracteres"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.password && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    {/* Password Confirmation */}
                    <div>
                        <label className="text-foreground mb-2 block text-sm font-semibold">
                            Confirmar Contraseña
                        </label>
                        <PasswordInput
                            name="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            placeholder="Repite la contraseña"
                            className="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/40 w-full rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
                        />
                        {errors.password_confirmation && (
                            <p className="text-destructive mt-1 text-sm">
                                {errors.password_confirmation}
                            </p>
                        )}
                    </div>

                    {/* Buttons */}
                    <div className="flex gap-4 pt-6">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex-1 rounded-lg px-6 py-2 font-semibold transition disabled:opacity-50"
                        >
                            {processing ? 'Creando...' : 'Crear Usuario'}
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
