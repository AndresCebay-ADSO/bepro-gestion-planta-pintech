import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Permission } from '@/types';

interface QuickAccessItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Permiso necesario para mostrar el acceso (basta con uno de la lista). */
    permission?: Permission | Permission[];
}

interface QuickAccessGridProps {
    items: QuickAccessItem[];
    title?: string;
}

export function QuickAccessGrid({
    items,
    title = 'Accesos rápidos',
}: QuickAccessGridProps) {
    const permissions = usePage().props.auth.user?.permissions ?? [];
    const visibleItems = items.filter((item) => {
        if (!item.permission) {
            return true;
        }

        const required = Array.isArray(item.permission)
            ? item.permission
            : [item.permission];

        return required.some((permission) => permissions.includes(permission));
    });

    if (visibleItems.length === 0) {
        return null;
    }

    return (
        <Card className="border-none shadow-lg">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-4">
                {visibleItems.map((item) => (
                    <Button
                        key={item.label}
                        asChild
                        variant="outline"
                        className="h-auto justify-start py-4"
                    >
                        <Link href={item.href}>
                            <item.icon className="mr-2 h-4 w-4" />
                            {item.label}
                        </Link>
                    </Button>
                ))}
            </CardContent>
        </Card>
    );
}
