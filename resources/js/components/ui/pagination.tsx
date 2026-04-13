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
    // Si hay 3 links o menos (Anterior, Página 1, Siguiente), no renderizamos nada
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

    // Separamos los links especiales (Anterior/Siguiente) de los numéricos
    const previousLink = links[0];
    const nextLink = links[links.length - 1];
    const numericLinks = links.slice(1, -1);

    return (
        <nav
            role="navigation"
            aria-label="Pagination"
            className={cn('flex items-center justify-center space-x-1', className)}
        >
            {/* Botón Anterior */}
            <Button
                variant="outline"
                size="icon"
                disabled={!previousLink.url}
                onClick={() => handleVisit(previousLink.url)}
                className="h-9 w-9"
                title="Anterior"
            >
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Anterior</span>
            </Button>

            {/* Números de Página */}
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

            {/* Botón Siguiente */}
            <Button
                variant="outline"
                size="icon"
                disabled={!nextLink.url}
                onClick={() => handleVisit(nextLink.url)}
                className="h-9 w-9"
                title="Siguiente"
            >
                <ChevronRight className="h-4 w-4" />
                <span className="sr-only">Siguiente</span>
            </Button>
        </nav>
    );
};

export default Pagination;
