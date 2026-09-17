import { Link, useForm } from '@inertiajs/react';
import type { FC, FormEvent } from 'react';

import InputError from '@/components/input-error';
import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as rolesIndex, update as rolesUpdate } from '@/routes/roles';
import type { Permission } from '@/types/permissions';
import type { PermissionModuleGroup } from '@/types/roles';

interface Props {
    role: {
        id: number;
        name: string;
        permissions: Permission[];
    };
    modules: PermissionModuleGroup[];
}

const RolesEdit: FC<Props> = ({ role, modules }) => {
    // Un rol creado antes de que un permiso fuera obligatorio lo recibe al editarlo.
    const requiredPermissions = modules
        .flatMap((module) => module.permissions)
        .filter((permission) => permission.required)
        .map((permission) => permission.name);

    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        permissions: Permission[];
    }>({
        name: role.name,
        permissions: [
            ...new Set([...requiredPermissions, ...role.permissions]),
        ],
    });

    const permissionErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('permissions'))
        .map(([, message]) => message);

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(rolesUpdate(String(role.id)).url);
    };

    return (
        <div className="min-h-screen bg-background px-4 py-8 text-foreground">
            <div className="mx-auto max-w-5xl">
                <div className="mb-8">
                    <Link
                        href={rolesIndex().url}
                        className="mb-4 inline-block text-sm text-primary hover:text-primary/80"
                    >
                        ← Volver a Roles
                    </Link>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Editar rol
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Los cambios se aplican de inmediato a todos los usuarios
                        con este rol.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-card p-6 shadow-xs md:p-8"
                >
                    <div className="grid gap-2 md:max-w-md">
                        <Label htmlFor="name">
                            Nombre <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            maxLength={50}
                            disabled={processing}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-3 border-t border-border pt-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Permisos</h2>
                            <span className="text-sm text-muted-foreground">
                                {data.permissions.length} seleccionados
                            </span>
                        </div>
                        {permissionErrors.map((message) => (
                            <InputError key={message} message={message} />
                        ))}
                        <RolePermissionsFields
                            modules={modules}
                            selected={data.permissions}
                            onChange={(permissions) =>
                                setData('permissions', permissions)
                            }
                            disabled={processing}
                        />
                    </div>

                    <div className="flex gap-4 pt-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="flex-1"
                        >
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            asChild
                            className="flex-1"
                        >
                            <Link href={rolesIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default RolesEdit;
