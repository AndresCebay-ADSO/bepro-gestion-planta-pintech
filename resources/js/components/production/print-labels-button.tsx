import { Printer } from 'lucide-react';

import { print as printLabels } from '@/actions/App/Http/Controllers/Production/ProductionLabelController';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PrintLabelsButtonProps = {
    orderId: number;
    planId: number;
};

/**
 * Abre en otra pestaña el PDF de la estampita de lote de una presentación. Las copias se eligen en el diálogo de
 * impresión del navegador, que la manda a la DYMO en tamaño real.
 */
export function PrintLabelsButton({ orderId, planId }: PrintLabelsButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="h-7 w-7"
                    onClick={() =>
                        window.open(
                            printLabels.url({
                                production_order: orderId,
                                plan: planId,
                            }),
                            '_blank',
                            'noopener',
                        )
                    }
                >
                    <Printer className="h-4 w-4" />
                    <span className="sr-only">Imprimir estampita</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>Imprimir estampita</TooltipContent>
        </Tooltip>
    );
}
