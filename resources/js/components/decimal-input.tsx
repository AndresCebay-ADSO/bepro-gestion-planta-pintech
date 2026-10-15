import { useState } from 'react';
import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import {
    acceptsEdit,
    checkDecimalText,
    DECIMAL_SEPARATOR_ERROR,
    decimalAmbiguityHint,
    decimalLimitsMessage,
} from '@/lib/numeric-input';
import type { DecimalLimits } from '@/lib/numeric-input';
import { cn } from '@/lib/utils';

type DecimalInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'inputMode' | 'value' | 'onChange'
> &
    DecimalLimits & {
        value: string | number | null | undefined;
        /** Recibe el texto tal como quedó escrito; se convierte al enviar con `toDecimalPayload`. */
        onValueChange: (value: string) => void;
        /** Clases del contenedor (campo y mensaje), p. ej. `flex-1` dentro de una fila flex. */
        containerClassName?: string;
    };

/**
 * Campo decimal del sistema (auditoría 3.6, INV-15): acepta punto o coma como separador decimal, sin rueda del mouse
 * ni `e`, y con los decimales de su columna. Reglas en `@/lib/numeric-input`.
 */
export function DecimalInput({
    value,
    onValueChange,
    containerClassName,
    maxDecimals,
    maxIntegerDigits,
    allowNegative,
    ...props
}: DecimalInputProps) {
    const limits = { maxDecimals, maxIntegerDigits, allowNegative };
    const text = value === null || value === undefined ? '' : String(value);
    const textCheck = checkDecimalText(text, limits);
    const hasSeparatorError = textCheck === 'separator-error';
    // Un valor que llegó de fuera (calculado o guardado) y no cabe en el campo: se avisa y se deja borrar.
    const isOutOfLimits = textCheck === 'reject';
    const error = hasSeparatorError
        ? DECIMAL_SEPARATOR_ERROR
        : isOutOfLimits
          ? decimalLimitsMessage(limits)
          : null;
    // El aviso es sobre lo que escribió el usuario: un valor que llega del servidor con tres decimales (2,125 kg) no lo
    // muestra al abrir la página.
    const [isEdited, setIsEdited] = useState(false);
    const hint = isEdited ? decimalAmbiguityHint(text) : null;
    const isEditable = !props.disabled && !props.readOnly;

    // El contenedor está siempre, aunque no haya mensaje: si apareciera solo con el mensaje, React volvería a montar
    // el campo y se perdería el foco mientras se escribe. `w-0 min-w-full`: el mensaje toma el ancho del campo y se
    // parte en líneas, sin ensanchar una celda angosta (consumo real en la tabla de ingredientes).
    return (
        <div className={cn('space-y-1', containerClassName)}>
            <Input
                {...props}
                type="text"
                // El teclado decimal del celular (iOS) no tiene el signo menos: con negativos va el teclado completo.
                inputMode={allowNegative ? 'text' : 'decimal'}
                autoComplete="off"
                aria-invalid={error !== null || props['aria-invalid']}
                value={text}
                onChange={(event) => {
                    const next = event.target.value;

                    if (
                        acceptsEdit(next, text, (value) =>
                            checkDecimalText(value, limits),
                        )
                    ) {
                        setIsEdited(true);
                        onValueChange(next);
                    }
                }}
            />
            {error ? (
                <p
                    role="alert"
                    className="w-0 min-w-full text-xs text-destructive"
                >
                    {error}
                </p>
            ) : (
                hint &&
                isEditable && (
                    <p
                        role="status"
                        className="w-0 min-w-full text-xs text-amber-700 dark:text-amber-400"
                    >
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
