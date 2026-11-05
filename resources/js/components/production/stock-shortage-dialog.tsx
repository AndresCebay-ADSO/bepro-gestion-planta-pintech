import { DetailLabel } from '@/components/detail-list';
import { FormattedNumber } from '@/components/formatted-number';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';
import type { StockShortage } from '@/lib/order-stock-check';

type StockShortageDialogProps = {
    open: boolean;
    shortages: StockShortage[];
    /** Mientras se crea la orden, el diálogo sigue abierto y no se puede cerrar. */
    processing: boolean;
    onCancel: () => void;
    onConfirm: () => void;
};

function ShortageFigure({
    label,
    value,
    unit,
    className,
}: {
    label: string;
    value: string;
    unit: string;
    className?: string;
}) {
    return (
        <div className="min-w-0">
            <DetailLabel>{label}</DetailLabel>
            <dd className={className}>
                <FormattedNumber
                    value={value}
                    kind="quantity"
                    className="tabular-nums"
                />{' '}
                {unit}
            </dd>
        </div>
    );
}

/**
 * Avisa, antes de crear una orden, de todas las materias primas que no alcanzan. No impide crearla: la orden puede usar
 * saldo y gastar menos de lo que pide la fórmula, y lo que no deja completarla es el consumo real.
 */
export function StockShortageDialog({
    open,
    shortages,
    processing,
    onCancel,
    onConfirm,
}: StockShortageDialogProps) {
    return (
        <AlertDialog
            open={open}
            onOpenChange={(next) => {
                if (!next && !processing) {
                    onCancel();
                }
            }}
        >
            {/* La lista puede ser larga: se desplaza ella y el diálogo nunca pasa del alto de la pantalla. */}
            <AlertDialogContent className="flex max-h-dvh flex-col">
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Falta materia prima para esta orden
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Puedes crearla, pero no se podrá completar hasta que
                        haya stock de lo que se vaya a consumir.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <ul className="min-h-0 flex-1 divide-y divide-border overflow-y-auto text-sm">
                    {shortages.map((shortage) => (
                        <li key={shortage.code} className="space-y-2 py-3">
                            <p className="font-mono font-medium break-words">
                                {shortage.code}
                            </p>
                            <dl className="grid grid-cols-3 gap-2">
                                <ShortageFigure
                                    label="Requerido"
                                    value={shortage.required}
                                    unit={shortage.unit}
                                />
                                <ShortageFigure
                                    label="Disponible"
                                    value={shortage.available}
                                    unit={shortage.unit}
                                />
                                <ShortageFigure
                                    label="Falta"
                                    value={shortage.missing}
                                    unit={shortage.unit}
                                    className="font-medium text-warning-text"
                                />
                            </dl>
                        </li>
                    ))}
                </ul>

                <AlertDialogFooter className="sm:justify-between">
                    <AlertDialogCancel disabled={processing}>
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={processing}
                        onClick={(event) => {
                            // Sin esto Radix cierra el diálogo al hacer clic, antes de que responda el servidor.
                            event.preventDefault();
                            onConfirm();
                        }}
                    >
                        {processing && <Spinner />}
                        Crear de todas formas
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
