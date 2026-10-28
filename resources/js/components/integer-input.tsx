import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import { useNumericField } from '@/hooks/use-numeric-field';
import {
    checkIntegerText,
    INTEGER_SEPARATOR_ERROR,
    integerField,
    integerLimitsMessage,
} from '@/lib/numeric-input';
import { cn } from '@/lib/utils';

type IntegerInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'inputMode' | 'value' | 'onChange'
> & {
    value: string | number | null | undefined;
    /** Recibe el texto sin miles (`1200`). */
    onValueChange: (value: string) => void;
    /** Clases del contenedor (campo y mensaje), p. ej. `flex-1` dentro de una fila flex. */
    containerClassName?: string;
    maxDigits?: number;
};

/**
 * Campo de enteros del sistema (unidades, envases, etiquetas): solo dígitos, sin rueda del mouse ni `e`. Un punto o una
 * coma se dejan escritos y marcan el error, en vez de desaparecer y convertir `3,5` en 35 (auditoría 3.6). Muestra los
 * miles mientras se escribe (`1.200`); el formulario guarda el texto sin ellos. Reglas en `@/lib/numeric-input`.
 */
export function IntegerInput({
    value,
    onValueChange,
    containerClassName,
    maxDigits,
    ...props
}: IntegerInputProps) {
    const field = integerField(maxDigits);
    const text = value === null || value === undefined ? '' : String(value);
    const textCheck = checkIntegerText(text, maxDigits);
    const error =
        textCheck === 'separator-error'
            ? INTEGER_SEPARATOR_ERROR
            : textCheck === 'reject'
              ? integerLimitsMessage(maxDigits)
              : null;
    const numeric = useNumericField({
        field,
        text,
        onValueChange,
        inputProps: props,
    });

    // Contenedor fijo y mensaje con el ancho del campo, como en DecimalInput.
    return (
        <div className={cn('space-y-1', containerClassName)}>
            <Input
                {...props}
                type="text"
                // Sin `pattern`: el navegador lo validaría contra lo que se ve (`1.200`) y no dejaría enviar el
                // formulario. Un punto o una coma tecleados ya marcan el error aquí, y el servidor rechaza lo que no sea
                // entero: las unidades son columnas enteras (NumberType::Units, PR 4 de la auditoría 3.6).
                inputMode="numeric"
                autoComplete="off"
                aria-invalid={error !== null || props['aria-invalid']}
                {...numeric}
            />
            {error && (
                <p
                    role="alert"
                    className="w-0 min-w-full text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
