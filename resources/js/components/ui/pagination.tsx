import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { FC } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types/ui';

interface PaginationProps {
    links: PaginationLink[];
    className?: string;
}

const Pagination: FC<PaginationProps> = ({ links, className }) => {
    // Hide component if there are 3 or fewer links (Prev, Page 1, Next)
    if (links.length <= 3) {
        return null;
    }

    const handleVisit = (url: string | null) => {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    // Separate special links (Previous/Next) from numeric indices
    const previousLink = links[0];
    const nextLink = links[links.length - 1];
    const numericLinks = links.slice(1, -1);

    return (
        <nav
            role="navigation"
            aria-label="Pagination"
            className={cn('flex items-center justify-center space-x-1', className)}
        >
            {/* Previous Button */}
            <Button
                variant="outline"
                size="icon"
                disabled={!previousLink.url}
                onClick={() => handleVisit(previousLink.url)}
                className="h-9 w-9"
                title="Previous"
            >
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Previous</span>
            </Button>

            {/* Page Numbers */}
            <div className="flex items-center space-x-1">
                {numericLinks.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        variant={link.active ? 'default' : 'ghost'}
                        size="sm"
                        disabled={!link.url || link.label === '...'}
                        onClick={() => handleVisit(link.url)}
                        className={cn(
                            'h-9 min-w-9 px-3',
                            link.active ? 'pointer-events-none' : ''
                        )}
                    >
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                ))}
            </div>

            {/* Next Button */}
            <Button
                variant="outline"
                size="icon"
                disabled={!nextLink.url}
                onClick={() => handleVisit(nextLink.url)}
                className="h-9 w-9"
                title="Next"
            >
                <ChevronRight className="h-4 w-4" />
                <span className="sr-only">Next</span>
            </Button>
        </nav>
    );
};

export default Pagination;
