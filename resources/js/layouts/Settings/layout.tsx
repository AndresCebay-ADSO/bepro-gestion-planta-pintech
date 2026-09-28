import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { filterNavItemsByPermissions } from '@/lib/navigation';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { index as unitsOfMeasureIndex } from '@/routes/catalogs/units-of-measure';
import { edit } from '@/routes/profile';
import type { NavGroup } from '@/types';

const settingsNavGroups: NavGroup[] = [
    {
        label: 'Cuenta',
        items: [
            {
                title: 'Perfil',
                href: edit().url,
                icon: null,
            },
            {
                title: 'Apariencia',
                href: editAppearance().url,
                icon: null,
            },
        ],
    },
    {
        // Catálogos del sistema (docs/MATRIZ_RBAC.md §3): solo con `catalogs.view`.
        label: 'Catálogos',
        items: [
            {
                title: 'Unidades de medida',
                href: unitsOfMeasureIndex().url,
                icon: null,
                allowedPermissions: ['catalogs.view'],
            },
        ],
    },
];

type SettingsLayoutProps = PropsWithChildren<{
    /** Las páginas con tablas (catálogos) usan todo el ancho disponible. */
    wide?: boolean;
}>;

export default function SettingsLayout({
    children,
    wide = false,
}: SettingsLayoutProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const userPermissions = usePage().props.auth.user?.permissions ?? [];

    const groups = settingsNavGroups
        .map((group) => ({
            ...group,
            items: filterNavItemsByPermissions(group.items, userPermissions),
        }))
        .filter((group) => group.items.length > 0);
    const showsCatalogs = groups.length > 1;

    return (
        <div className="px-4 py-6 md:px-6">
            <Heading
                title="Configuración"
                description={
                    showsCatalogs
                        ? 'Gestiona tu cuenta y los catálogos del sistema'
                        : 'Gestiona tu perfil y la configuración de tu cuenta'
                }
            />

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                <aside className="w-full max-w-xl lg:w-56">
                    <nav
                        className="flex flex-col space-y-1 rounded-xl border border-border bg-card p-2 shadow-sm"
                        aria-label="Configuración"
                    >
                        {groups.map((group, groupIndex) => (
                            <div key={group.label} className="space-y-1">
                                {showsCatalogs && (
                                    <p
                                        className={cn(
                                            'px-3 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase',
                                            groupIndex > 0 && 'pt-3',
                                        )}
                                    >
                                        {group.label}
                                    </p>
                                )}
                                {group.items.map((item) => (
                                    <Button
                                        key={toUrl(item.href)}
                                        size="sm"
                                        variant="ghost"
                                        asChild
                                        className={cn(
                                            'w-full justify-start rounded-lg px-3',
                                            {
                                                'bg-muted text-foreground':
                                                    isCurrentOrParentUrl(
                                                        item.href,
                                                    ),
                                            },
                                        )}
                                    >
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                'text-muted-foreground',
                                                {
                                                    'text-foreground':
                                                        isCurrentOrParentUrl(
                                                            item.href,
                                                        ),
                                                },
                                            )}
                                        >
                                            {item.icon && (
                                                <item.icon className="h-4 w-4" />
                                            )}
                                            {item.title}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div
                    className={cn('flex-1', wide ? 'min-w-0' : 'md:max-w-3xl')}
                >
                    <section
                        className={cn(
                            'space-y-10 rounded-xl border border-border bg-card p-6 shadow-sm',
                            !wide && 'max-w-2xl',
                        )}
                    >
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
