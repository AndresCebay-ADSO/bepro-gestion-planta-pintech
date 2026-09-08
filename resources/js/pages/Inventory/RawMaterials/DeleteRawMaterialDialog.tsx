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
    processing: boolean;
    onConfirm: () => void;
};

export function DeleteRawMaterialDialog({
    open,
    onOpenChange,
    rawMaterial,
    hasActivity,
    processing,
    onConfirm,
}: DeleteRawMaterialDialogProps) {
    if (!rawMaterial) {
        return null;
    }

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
                            hasActivity
                                ? 'bg-warning/10 text-warning'
                                : 'bg-destructive/10 text-destructive'
                        }
                    >
                        {hasActivity ? (
                            <AlertTriangle className="size-8" />
                        ) : (
                            <Trash2 className="size-8" />
                        )}
                    </AlertDialogMedia>
                    <AlertDialogTitle>
                        {hasActivity
                            ? 'Desactivar materia prima'
                            : 'Eliminar materia prima permanentemente'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {hasActivity ? (
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
                        variant={hasActivity ? 'warning' : 'destructive'}
                        disabled={processing}
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                    >
                        {processing && (
                            <Loader2 className="mr-2 size-4 animate-spin" />
                        )}
                        {hasActivity
                            ? 'Desactivar'
                            : 'Eliminar definitivamente'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
