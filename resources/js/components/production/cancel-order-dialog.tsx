import { useForm } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useState } from 'react';

import { cancel as productionOrderCancel } from '@/actions/App/Http/Controllers/ProductionOrderController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatNumber } from '@/lib/formatters';

/** Mínimo y máximo del motivo: los mismos del servidor (`CancelProductionOrderRequest`). */
const REASON_MIN_LENGTH = 10;
const REASON_MAX_LENGTH = 1000;

type CancelOrderDialogProps = {
    orderId: number;
    orderNumber: string;
    /** Galones de saldo que la orden ya consumió: al cancelar no vuelven al inventario (D-OPX2, B66). */
    consumedRemnantGallons: number;
};

/**
 * Cancela una orden pendiente o en curso (D-OPX2). Quien la muestra decide si se puede (`can.cancel`); el servidor vuelve
 * a revisar el estado con la orden bloqueada. El motivo es obligatorio y queda en las notas de la orden.
 */
export function CancelOrderDialog({
    orderId,
    orderNumber,
    consumedRemnantGallons,
}: CancelOrderDialogProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, clearErrors, reset } =
        useForm({ reason: '' });

    const openDialog = () => {
        reset();
        clearErrors();
        setOpen(true);
    };

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(productionOrderCancel.url(orderId), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="gap-1.5 border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                onClick={openDialog}
            >
                <Ban className="h-4 w-4" />
                Cancelar orden
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <DialogHeader>
                            <DialogTitle>
                                ¿Cancelar la orden {orderNumber}?
                            </DialogTitle>
                            <DialogDescription>
                                La orden queda cancelada y no se puede
                                reactivar. El motivo se guarda en sus notas.
                            </DialogDescription>
                        </DialogHeader>

                        {consumedRemnantGallons > 0 && (
                            <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                                Esta orden ya consumió{' '}
                                <strong>
                                    {formatNumber(consumedRemnantGallons, {
                                        maxDecimals: 4,
                                        trimTrailingZeros: true,
                                    })}{' '}
                                    gal
                                </strong>{' '}
                                de saldos. Al cancelar no vuelven al inventario
                                y quedan anotados en las notas de la orden.
                            </p>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="cancel-reason">
                                Motivo de la cancelación
                            </Label>
                            <Textarea
                                id="cancel-reason"
                                name="reason"
                                value={data.reason}
                                maxLength={REASON_MAX_LENGTH}
                                placeholder={`Mínimo ${REASON_MIN_LENGTH} caracteres: por qué no se va a producir`}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            />
                            <InputError message={errors.reason} />
                            {/* La orden cambió de estado mientras se escribía (RejectsStaleOrderWrites). */}
                            <InputError
                                message={
                                    (errors as Partial<Record<string, string>>)
                                        .production_order
                                }
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                disabled={processing}
                            >
                                Volver
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing ? 'Cancelando…' : 'Cancelar orden'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
