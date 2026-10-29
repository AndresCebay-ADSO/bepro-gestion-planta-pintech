import { X } from 'lucide-react';
import type { ChangeEvent, FC } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';

export interface SignatureUploadFieldProps {
    id?: string;
    currentSignatureUrl?: string | null;
    signatureFile?: File | null;
    onSignatureChange: (file: File | null) => void;
    onRemoveSignature: () => void;
    error?: string;
    disabled?: boolean;
    label?: string;
    className?: string;
}

export const SignatureUploadField: FC<SignatureUploadFieldProps> = ({
    id = 'signature',
    currentSignatureUrl,
    signatureFile,
    onSignatureChange,
    onRemoveSignature,
    error,
    disabled = false,
    label = 'Firma digital',
    className,
}) => {
    const signatureInputRef = useRef<HTMLInputElement>(null);

    const objectPreviewUrl = useMemo(() => {
        if (!signatureFile) {
            return null;
        }

        return URL.createObjectURL(signatureFile);
    }, [signatureFile]);

    useEffect(() => {
        if (!signatureFile && signatureInputRef.current) {
            signatureInputRef.current.value = '';
        }

        return () => {
            if (objectPreviewUrl) {
                URL.revokeObjectURL(objectPreviewUrl);
            }
        };
    }, [signatureFile, objectPreviewUrl]);

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        onSignatureChange(file);
    };

    const handleRemove = () => {
        onRemoveSignature();

        if (signatureInputRef.current) {
            signatureInputRef.current.value = '';
        }
    };

    const activeImageSrc = objectPreviewUrl ?? currentSignatureUrl;

    return (
        <FormField
            id={id}
            label={label}
            help="Formatos: PNG, JPG. Máximo 1 MB."
            error={error}
            className={className}
        >
            {(control) => (
                <div className="grid gap-2">
                    {activeImageSrc && (
                        <div className="relative inline-block max-w-[220px] rounded-lg border border-border bg-card p-2 shadow-xs">
                            <img
                                src={activeImageSrc}
                                alt="Vista previa de firma"
                                className="h-16 w-full object-contain"
                            />
                            {!disabled && (
                                <button
                                    type="button"
                                    onClick={handleRemove}
                                    className="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-destructive text-xs font-semibold text-destructive-foreground shadow-xs transition hover:bg-destructive/90 focus:outline-hidden"
                                    title="Eliminar firma"
                                    aria-label="Eliminar firma"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>
                    )}

                    <Input
                        {...control}
                        ref={signatureInputRef}
                        type="file"
                        className="block w-full cursor-pointer file:cursor-pointer"
                        accept="image/png,image/jpeg"
                        onChange={handleFileChange}
                        disabled={disabled}
                    />
                </div>
            )}
        </FormField>
    );
};

export default SignatureUploadField;
