import { useForm } from '@inertiajs/react';
import { useState } from 'react';

import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
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
import { store as qrDocumentsStore } from '@/routes/qr-codes/documents';

type UploadCertificateDialogProps = {
    qrCodeId: number;
};

/**
 * Sube a mano el certificado de calidad de un lote, para cuando el generado no sirve o no se pudo generar. Queda como la
 * versión vigente; el anterior sigue en el historial, fuera de la página pública.
 */
export function UploadCertificateDialog({
    qrCodeId,
}: UploadCertificateDialogProps) {
    const [open, setOpen] = useState(false);
    const { setData, post, processing, errors, reset, clearErrors } = useForm({
        document: null as File | null,
    });

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            reset();
            clearErrors();
        }

        setOpen(nextOpen);
    };

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(qrDocumentsStore(qrCodeId).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Button variant="outline" onClick={() => handleOpenChange(true)}>
                Subir certificado
            </Button>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent>
                    <form
                        noValidate
                        onSubmit={submit}
                        className="flex min-h-0 flex-col gap-4"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Subir certificado de calidad
                            </DialogTitle>
                            <DialogDescription>
                                Reemplaza al certificado vigente: es el que verá
                                el cliente en la página pública del QR. El
                                anterior queda en el historial.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogBody className="space-y-4">
                            <FormField
                                id="document"
                                label="Archivo PDF"
                                help="Tamaño máximo 10 MB."
                                error={errors.document}
                                required
                            >
                                <Input
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    className="cursor-pointer"
                                    onChange={(e) =>
                                        setData(
                                            'document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </FormField>
                        </DialogBody>
                        <DialogFooter>
                            <FormActions
                                submitLabel="Subir"
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
