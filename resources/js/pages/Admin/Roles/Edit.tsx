import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormActions } from '@/components/form-actions';
import { FormError, FormField } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    edit as rolesEdit,
    index as rolesIndex,
    show as rolesShow,
    update as rolesUpdate,
} from '@/routes/roles';
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

const RolesEdit = ({ role, modules }: Props) => {
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
        put(rolesUpdate(role.id).url);
    };

    return (
        <>
            <Head title="Editar rol" />
            <FormPage width="wide">
                <PageHeader
                    title="Editar rol"
                    description="Los cambios se aplican de inmediato a todos los usuarios con este rol."
                />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardContent className="space-y-6">
                            <FormField
                                id="name"
                                label="Nombre"
                                required
                                error={errors.name}
                                className="md:max-w-md"
                            >
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    maxLength={50}
                                    disabled={processing}
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Permisos</CardTitle>
                            <span className="text-sm text-muted-foreground">
                                {data.permissions.length} seleccionados
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {/* `permissions` o `permissions.3`: el foco del 422 llega al primero. */}
                            {permissionErrors.map((message, index) => (
                                <FormError
                                    key={message}
                                    id={index === 0 ? 'permissions' : undefined}
                                    message={message}
                                />
                            ))}
                            <RolePermissionsFields
                                modules={modules}
                                selected={data.permissions}
                                onChange={(permissions) =>
                                    setData('permissions', permissions)
                                }
                                disabled={processing}
                            />
                        </CardContent>
                    </Card>

                    <FormActions
                        submitLabel="Guardar cambios"
                        processing={processing}
                        cancelHref={rolesIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
};

// Las migas son la única forma de volver (DESIGN.md §10).
RolesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Roles', href: rolesIndex() },
        { title: props.role.name, href: rolesShow(props.role.id) },
        { title: 'Editar', href: rolesEdit(props.role.id) },
    ],
});

export default RolesEdit;
