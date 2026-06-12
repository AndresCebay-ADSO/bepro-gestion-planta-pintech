import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types/navigation';

type DetailPageNavProps = {
    breadcrumbs: BreadcrumbItem[];
    returnTo?: string | null;
    defaultReturnHref: string;
    defaultReturnLabel: string;
    actions?: ReactNode;
};

export function DetailPageNav({
    breadcrumbs,
    returnTo,
    defaultReturnHref,
    defaultReturnLabel,
    actions,
}: DetailPageNavProps) {
    const backHref = returnTo ?? defaultReturnHref;

    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div className="space-y-1">
                {breadcrumbs.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        {breadcrumbs.map((item, index) => (
                            <span
                                key={`${item.title}-${index}`}
                                className="flex items-center gap-2"
                            >
                                {index > 0 && <span>/</span>}
                                {index === breadcrumbs.length - 1 ? (
                                    <span>{item.title}</span>
                                ) : (
                                    <Link
                                        href={item.href}
                                        className="hover:text-foreground"
                                    >
                                        {item.title}
                                    </Link>
                                )}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                <Button variant="outline" asChild>
                    <Link href={backHref}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        {returnTo ? 'Volver' : defaultReturnLabel}
                    </Link>
                </Button>
                {actions}
            </div>
        </div>
    );
}
