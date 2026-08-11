import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface QuickAccessItem {
    label: string;
    href: string;
    icon: LucideIcon;
}

interface QuickAccessGridProps {
    items: QuickAccessItem[];
    title?: string;
}

export function QuickAccessGrid({
    items,
    title = 'Accesos rápidos',
}: QuickAccessGridProps) {
    return (
        <Card className="border-none shadow-lg">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-4">
                {items.map((item) => (
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
