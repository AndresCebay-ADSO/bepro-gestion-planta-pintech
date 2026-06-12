import type { ReactNode } from 'react';

import { DetailPageNav } from '@/components/detail-page-nav';
import type { BreadcrumbItem } from '@/types/navigation';

type DetailPageHeaderProps = {
    breadcrumbs: BreadcrumbItem[];
    title: ReactNode;
    subtitle?: ReactNode;
    badge?: ReactNode;
    returnTo?: string | null;
    defaultReturnHref: string;
    defaultReturnLabel: string;
    actions?: ReactNode;
};

export function DetailPageHeader({
    breadcrumbs,
    title,
    subtitle,
    badge,
    returnTo,
    defaultReturnHref,
    defaultReturnLabel,
    actions,
}: DetailPageHeaderProps) {
    return (
        <div className="space-y-3">
            <DetailPageNav
                breadcrumbs={breadcrumbs}
                returnTo={returnTo}
                defaultReturnHref={defaultReturnHref}
                defaultReturnLabel={defaultReturnLabel}
                actions={actions}
            />

            <div className="space-y-1">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold text-foreground">
                        {title}
                    </h1>
                    {badge}
                </div>
                {subtitle && (
                    <div className="text-sm text-muted-foreground">
                        {subtitle}
                    </div>
                )}
            </div>
        </div>
    );
}
