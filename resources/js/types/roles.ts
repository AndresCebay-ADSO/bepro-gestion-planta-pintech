import type { Permission } from '@/types/permissions';

/** Permiso en la pantalla de roles, con sus dependencias directas e indirectas (`Permission::dependencies()`). */
export type PermissionOption = {
    name: Permission;
    label: string;
    dependencies: Permission[];
};

/** Módulo de permisos (`PermissionModule`). */
export type PermissionModuleGroup = {
    key: string;
    label: string;
    permissions: PermissionOption[];
};

/** Rol existente que sirve como punto de partida al crear uno nuevo. */
export type RoleTemplate = {
    id: number;
    label: string;
    permissions: Permission[];
};
