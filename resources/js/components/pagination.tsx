import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { FC } from 'react';

import { buttonVariants } from '@/components/ui/button';
import { paginationSummary } from '@/lib/pagination';
import type { PaginationNoun } from '@/lib/pagination';
import { cn } from '@/lib/utils';
import type { PaginatorMeta } from '@/types/ui';

type PaginatorSummaryData = Pick<
    PaginatorMeta,
    'links' | 'from' | 'to' | 'total' | 'current_page' | 'last_page'
>;

interface PaginationProps {
    paginator: PaginatorSummaryData;
    noun?: PaginationNoun;
    className?: string;
}

interface PageArrowProps {
    url: string | null | undefined;
    label: string;
    icon: typeof ChevronLeft;
}

const PageArrow: FC<PageArrowProps> = ({ url, label, icon: Icon }) => {
    const classes = cn(
        buttonVariants({ variant: 'outline', size: 'icon' }),
        'h-9 w-9',
        !url && 'pointer-events-none opacity-50',
    );

    if (!url) {
        return (
            <span aria-disabled="true" className={classes} title={label}>
                <Icon className="size-4" />
                <span className="sr-only">{label}</span>
            </span>
        );
    }

    return (
        <Link
            href={url}
            preserveScroll
            preserveState
            className={classes}
            title={label}
        >
            <Icon className="size-4" />
            <span className="sr-only">{label}</span>
        </Link>
    );
};

const Pagination: FC<PaginationProps> = ({ paginator, noun, className }) => {
    const {
        links,
        from,
        to,
        total,
        current_page: currentPage,
        last_page: lastPage,
    } = paginator;
    const summary = paginationSummary(from, to, total, noun);
    const hasPages = links.length > 3;

    if (!summary && !hasPages) {
        return null;
    }

    const previousLink = links[0];
    const nextLink = links[links.length - 1];
    const numericLinks = links.slice(1, -1);

    return (
        <div
            className={cn(
                'flex w-full flex-col items-center gap-3 md:flex-row md:justify-between',
                className,
            )}
        >
            {summary && (
                <p className="text-sm text-muted-foreground tabular-nums">
                    {summary}
                </p>
            )}

            {hasPages && (
                <nav
                    aria-label="Paginación"
                    className="flex items-center justify-center space-x-1 md:ml-auto"
                >
                    <PageArrow
                        url={previousLink?.url}
                        label="Anterior"
                        icon={ChevronLeft}
                    />

                    {/* En el celular solo caben «Anterior», «Página X de Y» y «Siguiente»; los números, desde md. */}
                    <span className="px-2 text-sm text-muted-foreground tabular-nums md:hidden">
                        Página {currentPage} de {lastPage}
                    </span>

                    <div className="hidden items-center space-x-1 md:flex">
                        {numericLinks.map((link, index) => {
                            const isDisabled =
                                !link.url || link.label === '...';
                            const classes = cn(
                                buttonVariants({
                                    variant: link.active ? 'default' : 'ghost',
                                    size: 'sm',
                                }),
                                'h-9 min-w-9 px-3 tabular-nums',
                                link.active && 'pointer-events-none',
                                !link.active &&
                                    isDisabled &&
                                    'pointer-events-none opacity-50',
                            );

                            if (link.active || isDisabled || !link.url) {
                                return (
                                    <span
                                        key={`${link.label}-${index}`}
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        aria-disabled={
                                            !link.active ? 'true' : undefined
                                        }
                                        className={classes}
                                    >
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </span>
                                );
                            }

                            return (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url}
                                    preserveScroll
                                    preserveState
                                    className={classes}
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Link>
                            );
                        })}
                    </div>

                    <PageArrow
                        url={nextLink?.url}
                        label="Siguiente"
                        icon={ChevronRight}
                    />
                </nav>
            )}
        </div>
    );
};

export default Pagination;
