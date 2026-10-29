import { Slot } from 'radix-ui';
import type { ReactElement, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** Lo que el campo recibe para quedar enlazado con su etiqueta, su ayuda y su error. */
export type FormFieldControlProps = {
    id: string;
    'aria-describedby'?: string;
    'aria-invalid'?: true;
    'aria-required'?: true;
};

type FormFieldProps = {
    /** El `id` del campo: lo señala la etiqueta y el foco llega a él ante un 422 (igual a la llave del error). */
    id: string;
    label: ReactNode;
    /** Ayuda bajo el campo (`text-xs text-muted-foreground`). */
    help?: ReactNode;
    error?: string;
    /** Marca la etiqueta con un asterisco rojo (oculto al lector de pantalla) y el campo con `aria-required`. */
    required?: boolean;
    /** Clases del contenedor: por ejemplo `md:col-span-2` para ocupar las dos columnas. */
    className?: string;
    /**
     * El campo. Un elemento (`Input`, `Textarea`, `DecimalInput`, `Combobox`, `PlantDateInput`…) recibe solo `id` y los
     * `aria-*`. Un `Select` recibe una función, para que los reciba el `SelectTrigger` y no la raíz:
     * `{(control) => <Select …><SelectTrigger {...control}>…</SelectTrigger></Select>}`. Un grupo de opciones
     * (radios, casillas) también recibe una función y se nombra con la etiqueta, cuyo `id` es `${control.id}-label`.
     */
    children: ReactElement | ((control: FormFieldControlProps) => ReactNode);
};

/**
 * Un campo de formulario (DESIGN.md §8): etiqueta, campo, ayuda y error, siempre con el mismo espacio y enlazados para
 * el lector de pantalla. Ocupa su columna completa y nunca se sale de ella (`min-w-0`); cuántas columnas hay lo decide
 * el formulario.
 */
export function FormField({
    id,
    label,
    help,
    error,
    required = false,
    className,
    children,
}: FormFieldProps) {
    const helpId = help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ');
    const control: FormFieldControlProps = {
        id,
        ...(describedBy ? { 'aria-describedby': describedBy } : {}),
        ...(error ? { 'aria-invalid': true } : {}),
        ...(required ? { 'aria-required': true } : {}),
    };

    return (
        <div
            data-slot="form-field"
            className={cn('grid min-w-0 content-start gap-2', className)}
        >
            <Label id={`${id}-label`} htmlFor={id}>
                {label}
                {required && (
                    <span
                        className="ml-0.5 text-destructive"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </Label>
            {typeof children === 'function' ? (
                children(control)
            ) : (
                <Slot.Root {...control}>{children}</Slot.Root>
            )}
            {help && (
                <p id={helpId} className="text-xs text-muted-foreground">
                    {help}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
