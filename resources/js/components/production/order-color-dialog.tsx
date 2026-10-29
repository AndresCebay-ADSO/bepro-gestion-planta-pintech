import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

import { updateColor as productionOrderUpdateColor } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { FormActions } from '@/components/form-actions';
import { FormError, FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type OrderColorDialogProps = {
    orderId: number;
    color: string | null;
};

/**
 * Corrige el color que pidió el cliente mientras la orden sigue abierta (3.4). Quien la muestra decide si se puede
 * (`can.updateColor`); al completar la orden el color se congela.
 */
export function OrderColorDialog({ orderId, color }: OrderColorDialogProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, clearErrors } = useForm({
        color: color ?? '',
    });

    const openDialog = () => {
        setData('color', color ?? '');
        clearErrors();
        setOpen(true);
    };

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(productionOrderUpdateColor.url(orderId), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={openDialog}
                    >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Corregir color</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Corregir color</TooltipContent>
            </Tooltip>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form
                        noValidate
                        onSubmit={submit}
                        className="flex min-h-0 flex-col gap-4"
                    >
                        <DialogHeader>
                            <DialogTitle>Color de la orden</DialogTitle>
                            <DialogDescription>
                                El que pidió el cliente. Se añade al nombre del
                                producto en la orden, el certificado y el QR, y
                                no se puede cambiar después de completarla.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogBody className="space-y-4">
                            <FormField
                                id="color"
                                label="Color"
                                error={errors.color}
                            >
                                <Input
                                    value={data.color}
                                    maxLength={100}
                                    placeholder="Ej.: RAL 3020"
                                    onChange={(e) =>
                                        setData('color', e.target.value)
                                    }
                                />
                            </FormField>
                            {/* La orden cambió de estado mientras se editaba (RejectsStaleOrderWrites). */}
                            <FormError
                                id="production_order"
                                message={
                                    (errors as Partial<Record<string, string>>)
                                        .production_order
                                }
                            />
                        </DialogBody>
                        <DialogFooter>
                            <FormActions
                                submitLabel="Guardar"
                                processing={processing}
                                onCancel={() => setOpen(false)}
                            />
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
