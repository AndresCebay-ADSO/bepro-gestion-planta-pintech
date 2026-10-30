import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { FC } from 'react';

import { buttonVariants } from '@/components/ui/button';
import { paginationSummary } from '@/lib/pagination';
import { cn } from '@/lib/utils';
import type { PaginatorMeta } from '@/types/ui';

export type PaginatorSummaryData = Pick<
    PaginatorMeta,
    'links' | 'from' | 'to' | 'total'
>;

interface PaginationProps {
    paginator: PaginatorSummaryData;
    className?: string;
}

const Pagination: FC<PaginationProps> = ({ paginator, className }) => {
    const { links, from, to, total } = paginator;
    const summary = paginationSummary(from, to, total);
    const hasPages = links.length > 3;

    if (!summary && !hasPages) {
        return null;
    }

    const previousLink = links[0];
    const nextLink = links[links.length - 1];
    const numericLinks = links.slice(1, -1);
    // En el celular solo caben «Anterior», «Página X de Y» y «Siguiente»; los números, desde md.
    const pageNumbers = numericLinks
        .map((link) => Number(link.label))
        .filter((page) => Number.isInteger(page));
    const currentPage = Number(numericLinks.find((link) => link.active)?.label);
    const lastPage = Math.max(...pageNumbers);

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
                    role="navigation"
                    aria-label="Paginación"
                    className="flex items-center justify-center space-x-1 md:ml-auto"
                >
                    {previousLink?.url ? (
                        <Link
                            href={previousLink.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'icon',
                                }),
                                'h-9 w-9',
                            )}
                            title="Anterior"
                        >
                            <ChevronLeft className="size-4" />
                            <span className="sr-only">Anterior</span>
                        </Link>
                    ) : (
                        <span
                            aria-disabled="true"
                            className={cn(
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'icon',
                                }),
                                'pointer-events-none h-9 w-9 opacity-50',
                            )}
                            title="Anterior"
                        >
                            <ChevronLeft className="size-4" />
                            <span className="sr-only">Anterior</span>
                        </span>
                    )}

                    {Number.isInteger(currentPage) && (
                        <span className="px-2 text-sm text-muted-foreground tabular-nums md:hidden">
                            Página {currentPage} de {lastPage}
                        </span>
                    )}

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
                                isDisabled && 'pointer-events-none opacity-50',
                            );

                            if (isDisabled || !link.url) {
                                return (
                                    <span
                                        key={`${link.label}-${index}`}
                                        aria-disabled="true"
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
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
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

                    {nextLink?.url ? (
                        <Link
                            href={nextLink.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'icon',
                                }),
                                'h-9 w-9',
                            )}
                            title="Siguiente"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Siguiente</span>
                        </Link>
                    ) : (
                        <span
                            aria-disabled="true"
                            className={cn(
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'icon',
                                }),
                                'pointer-events-none h-9 w-9 opacity-50',
                            )}
                            title="Siguiente"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Siguiente</span>
                        </span>
                    )}
                </nav>
            )}
        </div>
    );
};

export default Pagination;
