import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { FormActions } from '@/components/form-actions';
import { FormError, FormField } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create as rolesCreate,
    index as rolesIndex,
    store as rolesStore,
} from '@/routes/roles';
import type { Permission } from '@/types/permissions';
import type { PermissionModuleGroup, RoleTemplate } from '@/types/roles';

interface Props {
    modules: PermissionModuleGroup[];
    templates: RoleTemplate[];
    defaultPermissions: Permission[];
}

/** Valor del selector de plantillas para volver a los permisos por defecto. */
const FROM_SCRATCH = 'none';

const RolesCreate = ({ modules, templates, defaultPermissions }: Props) => {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        permissions: Permission[];
    }>({
        name: '',
        permissions: defaultPermissions,
    });
    const [templateId, setTemplateId] = useState(FROM_SCRATCH);

    const permissionErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('permissions'))
        .map(([, message]) => message);

    const applyTemplate = (value: string) => {
        setTemplateId(value);

        if (value === FROM_SCRATCH) {
            setData('permissions', defaultPermissions);

            return;
        }

        const template = templates.find((t) => String(t.id) === value);

        if (template) {
            // Los permisos por defecto son obligatorios: se conservan aunque la plantilla no los tenga.
            setData('permissions', [
                ...new Set([...defaultPermissions, ...template.permissions]),
            ]);
        }
    };

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(rolesStore().url);
    };

    return (
        <>
            <Head title="Nuevo rol" />
            <FormPage width="wide">
                <PageHeader
                    title="Nuevo rol"
                    description="Elige los permisos del rol. Al marcar uno se incluyen los permisos que necesita para funcionar."
                />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6 rounded-lg border border-border bg-card p-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <FormField
                                id="name"
                                label="Nombre"
                                required
                                error={errors.name}
                            >
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Ej. Jefe de calidad"
                                    maxLength={50}
                                    disabled={processing}
                                />
                            </FormField>

                            <FormField
                                id="template"
                                label="Partir de un rol existente"
                                help="Copia sus permisos, salvo los reservados a SuperAdmin."
                            >
                                {(control) => (
                                    <Select
                                        value={templateId}
                                        onValueChange={applyTemplate}
                                        disabled={processing}
                                    >
                                        <SelectTrigger {...control}>
                                            <SelectValue placeholder="Selección personalizada" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={FROM_SCRATCH}>
                                                Empezar desde cero
                                            </SelectItem>
                                            {templates.map((template) => (
                                                <SelectItem
                                                    key={template.id}
                                                    value={String(template.id)}
                                                >
                                                    {template.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>
                        </div>

                        <div className="space-y-3 border-t border-border pt-6">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold">
                                    Permisos
                                </h2>
                                <span className="text-sm text-muted-foreground">
                                    {data.permissions.length} seleccionados
                                </span>
                            </div>
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
                                onChange={(permissions) => {
                                    // Tras un cambio manual ya no es la plantilla elegida.
                                    setTemplateId('');
                                    setData('permissions', permissions);
                                }}
                                disabled={processing}
                            />
                        </div>
                    </div>

                    <FormActions
                        submitLabel="Crear rol"
                        processing={processing}
                        cancelHref={rolesIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
};

// Las migas son la única forma de volver al listado (DESIGN.md §10).
RolesCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: rolesIndex() },
        { title: 'Nuevo rol', href: rolesCreate() },
    ],
};

export default RolesCreate;
