import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FC, FormEvent } from 'react';

import InputError from '@/components/input-error';
import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as rolesIndex, store as rolesStore } from '@/routes/roles';
import type { Permission } from '@/types/permissions';
import type { PermissionModuleGroup, RoleTemplate } from '@/types/roles';

interface Props {
    modules: PermissionModuleGroup[];
    templates: RoleTemplate[];
    defaultPermissions: Permission[];
}

const RolesCreate: FC<Props> = ({ modules, templates, defaultPermissions }) => {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        permissions: Permission[];
    }>({
        name: '',
        permissions: defaultPermissions,
    });
    const [templateId, setTemplateId] = useState('');

    const permissionErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('permissions'))
        .map(([, message]) => message);

    const applyTemplate = (value: string) => {
        setTemplateId(value);

        const template = templates.find((t) => String(t.id) === value);

        if (template) {
            setData('permissions', template.permissions);
        }
    };

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(rolesStore().url);
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
                        Nuevo rol
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Elige los permisos del rol. Al marcar uno se incluyen
                        los permisos que necesita para funcionar.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-card p-6 shadow-xs md:p-8"
                >
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Nombre{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Ej. Jefe de calidad"
                                maxLength={50}
                                disabled={processing}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="template">
                                Partir de un rol existente
                            </Label>
                            <Select
                                value={templateId}
                                onValueChange={applyTemplate}
                                disabled={processing}
                            >
                                <SelectTrigger id="template" className="w-full">
                                    <SelectValue placeholder="Empezar desde cero" />
                                </SelectTrigger>
                                <SelectContent>
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
                            <p className="text-xs text-muted-foreground">
                                Copia sus permisos, salvo los reservados a
                                SuperAdmin.
                            </p>
                        </div>
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
                            {processing ? 'Creando...' : 'Crear rol'}
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

export default RolesCreate;
