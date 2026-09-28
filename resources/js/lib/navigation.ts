import type { NavItem } from '@/types/navigation';
import type { Permission } from '@/types/permissions';

/**
 * Filtra ítems de navegación por permisos (docs/MATRIZ_RBAC.md). Un ítem sin `allowedPermissions` es visible para todo
 * usuario con sesión; si los tiene, basta con uno. Sin permiso se oculta, o se muestra deshabilitado si el ítem lo pide.
 * Lo usan el menú lateral y las pestañas de Configuración.
 */
export function filterNavItemsByPermissions<T extends NavItem>(
    items: T[],
    userPermissions: Permission[],
): T[] {
    return items.flatMap((item) => {
        if (!item.allowedPermissions?.length) {
            return [item];
        }

        if (
            item.allowedPermissions.some((permission) =>
                userPermissions.includes(permission),
            )
        ) {
            return [item];
        }

        return item.unauthorizedBehavior === 'disable'
            ? [{ ...item, disabled: true }]
            : [];
    });
}

export function currentReturnTo(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return stripReturnToParam(
        `${window.location.pathname}${window.location.search}`,
    );
}

export function stripReturnToParam(href: string): string {
    const url = new URL(href, 'http://localhost');
    url.searchParams.delete('return_to');
    const query = url.searchParams.toString();

    return query ? `${url.pathname}?${query}` : url.pathname;
}

export function withReturnTo(href: string, returnTo?: string | null): string {
    const resolved = returnTo ?? currentReturnTo();

    if (!resolved || resolved === '/') {
        return href;
    }

    const baseHref = stripReturnToParam(href);
    const separator = baseHref.includes('?') ? '&' : '?';

    return `${baseHref}${separator}return_to=${encodeURIComponent(resolved)}`;
}

export function resolveModuleListHref(
    returnTo: string | null | undefined,
    modulePathPrefix: string,
    defaultIndexHref: string,
): string {
    if (returnTo?.startsWith(modulePathPrefix)) {
        return returnTo;
    }

    return defaultIndexHref;
}
