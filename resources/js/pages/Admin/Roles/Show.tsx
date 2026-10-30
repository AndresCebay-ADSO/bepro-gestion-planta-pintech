import { Head, Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';

import { PageHeader } from '@/components/page-header';
import RolePermissionsFields from '@/components/roles/role-permissions-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    edit as rolesEdit,
    index as rolesIndex,
    show as rolesShow,
} from '@/routes/roles';
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

const RolesShow = ({ role, modules, can }: Props) => {
    return (
        <>
            <Head title={role.label} />
            <div className="space-y-6 p-6">
                <PageHeader
                    title={role.label}
                    badge={
                        <Badge
                            variant={role.is_system ? 'secondary' : 'outline'}
                        >
                            {role.is_system ? 'Sistema' : 'Personalizado'}
                        </Badge>
                    }
                    description={
                        <>
                            {role.permissions.length} permisos ·{' '}
                            {role.users_count} usuarios
                        </>
                    }
                    actions={
                        can.update && (
                            <Button asChild>
                                <Link href={rolesEdit(role.id).url}>
                                    Editar rol
                                </Link>
                            </Button>
                        )
                    }
                />

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
        </>
    );
};

// Las migas son la única forma de volver al listado (DESIGN.md §10).
RolesShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Roles', href: rolesIndex() },
        { title: props.role.label, href: rolesShow(props.role.id) },
    ],
});

export default RolesShow;
