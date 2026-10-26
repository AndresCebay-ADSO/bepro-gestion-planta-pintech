import type { ReactNode } from 'react';

import { DetailPageNav } from '@/components/detail-page-nav';
import { PageHeader } from '@/components/page-header';
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

            <PageHeader title={title} description={subtitle} badge={badge} />
        </div>
    );
}
