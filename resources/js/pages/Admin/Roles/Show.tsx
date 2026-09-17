import { Link } from '@inertiajs/react';
import { Lock, Pencil } from 'lucide-react';
import type { FC } from 'react';

import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as rolesEdit, index as rolesIndex } from '@/routes/roles';
import type { Permission } from '@/types/permissions';
import type { PermissionModuleGroup } from '@/types/roles';

interface Props {
    role: {
        id: number;
        label: string;
        is_system: boolean;
        users_count: number;
        permissions: Permission[];
    };
    modules: PermissionModuleGroup[];
    can: {
        update: boolean;
        delete: boolean;
    };
}

const RolesShow: FC<Props> = ({ role, modules, can }) => {
    return (
        <div className="min-h-screen bg-background px-4 py-8 text-foreground">
            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <Link
                        href={rolesIndex().url}
                        className="mb-4 inline-block text-sm text-primary hover:text-primary/80"
                    >
                        ← Volver a Roles
                    </Link>
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                    {role.label}
                                </h1>
                                <Badge
                                    variant={
                                        role.is_system ? 'secondary' : 'outline'
                                    }
                                >
                                    {role.is_system
                                        ? 'Sistema'
                                        : 'Personalizado'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {role.permissions.length} permisos ·{' '}
                                {role.users_count} usuarios
                            </p>
                        </div>
                        {can.update && (
                            <Button asChild>
                                <Link href={rolesEdit(role.id).url}>
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Editar rol
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {role.is_system && (
                    <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
                        <Lock className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            Los roles del sistema se gestionan en código: sus
                            permisos se restablecen en cada despliegue y no se
                            pueden modificar desde aquí.
                        </p>
                    </div>
                )}

                <RolePermissionsFields
                    modules={modules}
                    selected={role.permissions}
                    readOnly
                />
            </div>
        </div>
    );
};

export default RolesShow;
