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

export function withReturnTo(
    href: string,
    returnTo?: string | null,
): string {
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
