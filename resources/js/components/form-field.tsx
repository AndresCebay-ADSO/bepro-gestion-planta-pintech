import { Slot } from 'radix-ui';
import type { ComponentProps, ReactElement, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
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

type FormCheckboxProps = {
    /** El `id` de la casilla (igual a la llave del error). */
    id: string;
    /** El texto junto a la casilla. */
    label: ReactNode;
    /** Ayuda bajo el texto. */
    help?: ReactNode;
    error?: string;
    className?: string;
} & Omit<
    ComponentProps<typeof Checkbox>,
    'id' | 'className' | 'aria-describedby' | 'aria-invalid'
>;

/**
 * Una casilla con su texto al lado (DESIGN.md §8): «Activo», «Controla inventario», «Recordar sesión», un permiso.
 * Ayuda y error van bajo el texto, alineados con él, y quedan enlazados para el lector de pantalla como en
 * `<FormField>`. El resto de props (`checked`, `onCheckedChange`, `disabled`, `name`…) van a la casilla.
 */
export function FormCheckbox({
    id,
    label,
    help,
    error,
    className,
    ...checkboxProps
}: FormCheckboxProps) {
    const helpId = help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ');

    return (
        <div
            data-slot="form-checkbox"
            className={cn('flex min-w-0 items-start gap-3', className)}
        >
            <Checkbox
                id={id}
                className="mt-0.5"
                {...(describedBy ? { 'aria-describedby': describedBy } : {})}
                {...(error ? { 'aria-invalid': true } : {})}
                {...checkboxProps}
            />
            <div className="grid min-w-0 gap-1">
                <Label htmlFor={id} className="cursor-pointer leading-5">
                    {label}
                </Label>
                {help && (
                    <p id={helpId} className="text-xs text-muted-foreground">
                        {help}
                    </p>
                )}
                <InputError id={errorId} message={error} />
            </div>
        </div>
    );
}

type FormErrorProps = {
    /**
     * La llave del error cuando no tiene un campo propio (`items`, `production_order`): el foco llega aquí ante un 422
     * (`lib/focus-first-error.ts`) en vez de al primer mensaje de la página.
     */
    id?: string;
    message?: string;
    className?: string;
};

/**
 * Un error del formulario que no es de un campo (DESIGN.md §8): el de una lista de líneas entera («agrega al menos
 * una»), una confirmación que pide el servidor o la orden que cambió mientras se editaba.
 */
export function FormError({ id, message, className }: FormErrorProps) {
    return (
        <InputError
            id={id}
            tabIndex={id ? -1 : undefined}
            message={message}
            className={cn('outline-none', className)}
        />
    );
}
