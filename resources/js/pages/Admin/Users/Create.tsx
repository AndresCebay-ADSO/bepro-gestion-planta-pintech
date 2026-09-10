import { Link, useForm } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';

import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UserIdentityFields from '@/components/users/user-identity-fields';
import type { Role } from '@/types';

interface Props {
    roles: Role[];
}

const UsersCreate: FC<Props> = ({ roles }) => {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        job_title: '',
        phone: '',
        signature: null as File | null,
        remove_signature: false,
        password: '',
        password_confirmation: '',
        role: roles.find((r) => r.name === 'produccion')?.name || 'produccion',
        is_active: true,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(UserController.store.url(), {
            forceFormData: true,
            onSuccess: () => reset('password', 'password_confirmation', 'signature'),
        });
    };

    return (
        <div className="min-h-screen bg-background px-4 py-8 text-foreground">
            <div className="mx-auto max-w-2xl">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        href={UserController.index.url()}
                        className="mb-4 inline-block text-sm text-primary hover:text-primary/80"
                    >
                        ← Volver a Gestión de Usuarios
                    </Link>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Crear Nuevo Usuario
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Agrega un nuevo usuario al sistema con sus credenciales y permisos
                    </p>
                </div>

                {/* Form */}
                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-card p-6 shadow-xs md:p-8"
                >
                    {/* Identidad y Firma Compartida */}
                    <UserIdentityFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        disabled={processing}
                    />

                    <div className="border-t border-border pt-6 space-y-6">
                        {/* Rol */}
                        <div className="grid gap-2">
                            <Label htmlFor="role">
                                Rol <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.role}
                                onValueChange={(val) => setData('role', val)}
                                disabled={processing}
                            >
                                <SelectTrigger id="role" className="w-full">
                                    <SelectValue placeholder="Seleccionar rol..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={role.name}>
                                            {role.name.charAt(0).toUpperCase() +
                                                role.name.slice(1)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.role} />
                        </div>

                        {/* Estado Activo */}
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                                disabled={processing}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">
                                Usuario activo
                            </Label>
                        </div>
                    </div>

                    <div className="border-t border-border pt-6 space-y-6">
                        {/* Contraseña */}
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                Contraseña <span className="text-destructive">*</span>
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                placeholder="Mínimo 8 caracteres"
                                disabled={processing}
                            />
                            <InputError message={errors.password} />
                        </div>

                        {/* Confirmar Contraseña */}
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar Contraseña <span className="text-destructive">*</span>
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                                placeholder="Repite la contraseña"
                                disabled={processing}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>
                    </div>

                    {/* Botones */}
                    <div className="flex gap-4 pt-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="flex-1"
                        >
                            {processing ? 'Creando...' : 'Crear Usuario'}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            asChild
                            className="flex-1"
                        >
                            <Link href={UserController.index.url()}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default UsersCreate;

