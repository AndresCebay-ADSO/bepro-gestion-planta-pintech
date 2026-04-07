import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { UserRole } from '@/types/auth';

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
    allowedRoles?: UserRole[];
    unauthorizedBehavior?: 'hide' | 'disable';
    disabled?: boolean;
    disabledLabel?: string;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
