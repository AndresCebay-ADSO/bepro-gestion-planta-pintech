import { Link, useForm } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';

import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
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
import type { Role, RoleOption, User } from '@/types';

interface Props {
    user: User & { roles: Role[] };
    roles: RoleOption[];
}

const UsersEdit: FC<Props> = ({ user, roles }) => {
    const { data, setData, post, put, transform, processing, errors } = useForm(
        {
            name: user.name,
            email: user.email ? user.email.toLowerCase() : '',
            job_title: user.job_title ?? '',
            phone: user.phone ?? '',
            signature: null as File | null,
            remove_signature: false,
            role: user.roles[0]?.name ?? '',
            is_active: user.is_active ?? true,
        },
    );

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (data.signature instanceof File) {
            transform((currentData) => ({
                ...currentData,
                _method: 'put',
            }));
            post(UserController.update.url(user.id), {
                forceFormData: true,
            });
        } else {
            transform((currentData) => currentData);
            put(UserController.update.url(user.id));
        }
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
                        Editar Usuario
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Actualiza la información, rol y permisos del usuario
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
                        currentSignatureUrl={user.signature_url}
                        disabled={processing}
                    />

                    <div className="space-y-6 border-t border-border pt-6">
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
                                        <SelectItem
                                            key={role.id}
                                            value={role.name}
                                        >
                                            {role.label}
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
                            <Label
                                htmlFor="is_active"
                                className="cursor-pointer"
                            >
                                Usuario activo
                            </Label>
                        </div>
                    </div>

                    {/* Info */}
                    <div className="rounded-lg border border-primary/30 bg-primary/10 p-4">
                        <p className="text-sm text-primary">
                            💡 Para cambiar la contraseña, el usuario debe usar
                            la opción "Olvidé mi contraseña" en el login.
                        </p>
                    </div>

                    {/* Botones */}
                    <div className="flex gap-4 pt-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="flex-1"
                        >
                            {processing ? 'Actualizando...' : 'Guardar Cambios'}
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

export default UsersEdit;
