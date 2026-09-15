import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badge?: number | string;
    /** Permisos que dan acceso (basta con uno). Sin permisos, el ítem es visible para todo usuario con sesión. */
    allowedPermissions?: string[];
    unauthorizedBehavior?: 'hide' | 'disable';
    disabled?: boolean;
    disabledLabel?: string;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
