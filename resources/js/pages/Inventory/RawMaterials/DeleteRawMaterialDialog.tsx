import { AlertTriangle, Loader2, Trash2 } from 'lucide-react';
import React from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

export type DeleteRawMaterialDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rawMaterial: { id: number; code: string } | null;
    hasActivity: boolean;
    /** Permiso de borrado físico; sin él, una MP sin historial también se desactiva. */
    canDeletePermanently: boolean;
    processing: boolean;
    onConfirm: () => void;
};

/**
 * Confirmation dialog for deactivating or permanently deleting a raw material.
 */
export function DeleteRawMaterialDialog({
    open,
    onOpenChange,
    rawMaterial,
    hasActivity,
    canDeletePermanently,
    processing,
    onConfirm,
}: DeleteRawMaterialDialogProps) {
    if (!rawMaterial) {
        return null;
    }

    const deactivates = hasActivity || !canDeletePermanently;

    return (
        <AlertDialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) {
                    onOpenChange(nextOpen);
                }
            }}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia
                        className={
                            deactivates
                                ? 'bg-warning/10 text-warning'
                                : 'bg-destructive/10 text-destructive'
                        }
                    >
                        {deactivates ? (
                            <AlertTriangle className="size-8" />
                        ) : (
                            <Trash2 className="size-8" />
                        )}
                    </AlertDialogMedia>
                    <AlertDialogTitle>
                        {deactivates
                            ? 'Desactivar materia prima'
                            : 'Eliminar materia prima permanentemente'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {deactivates && !hasActivity ? (
                            <>
                                La materia prima{' '}
                                <strong className="font-semibold text-foreground">
                                    {rawMaterial.code}
                                </strong>{' '}
                                pasará a estado{' '}
                                <strong className="font-semibold text-foreground">
                                    Inactivo
                                </strong>
                                .
                            </>
                        ) : hasActivity ? (
                            <>
                                La materia prima{' '}
                                <strong className="font-semibold text-foreground">
                                    {rawMaterial.code}
                                </strong>{' '}
                                cuenta con historial en bodega, movimientos o
                                fórmulas. Pasará a estado{' '}
                                <strong className="font-semibold text-foreground">
                                    Inactivo
                                </strong>{' '}
                                para conservar la trazabilidad.
                            </>
                        ) : (
                            <>
                                La materia prima{' '}
                                <strong className="font-semibold text-foreground">
                                    {rawMaterial.code}
                                </strong>{' '}
                                no tiene movimientos ni fórmulas. Se eliminará
                                de forma definitiva. Esta acción no se puede
                                deshacer.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        variant={deactivates ? 'warning' : 'destructive'}
                        disabled={processing}
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                    >
                        {processing && (
                            <Loader2 className="mr-2 size-4 animate-spin" />
                        )}
                        {deactivates
                            ? 'Desactivar'
                            : 'Eliminar definitivamente'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
