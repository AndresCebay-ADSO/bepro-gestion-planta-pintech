import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import {
    checkDecimalText,
    DECIMAL_SEPARATOR_ERROR,
    decimalAmbiguityHint,
} from '@/lib/numeric-input';
import type { DecimalLimits } from '@/lib/numeric-input';

type DecimalInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'inputMode' | 'value' | 'onChange'
> &
    DecimalLimits & {
        value: string | number | null | undefined;
        /** Recibe el texto tal como quedó escrito; se convierte al enviar con `toDecimalPayload`. */
        onValueChange: (value: string) => void;
    };

/**
 * Campo decimal del sistema (auditoría 3.6, INV-15): acepta punto o coma como separador decimal, sin rueda del mouse
 * ni `e`, y con los decimales de su columna. Reglas en `@/lib/numeric-input`.
 */
export function DecimalInput({
    value,
    onValueChange,
    maxDecimals,
    maxIntegerDigits,
    ...props
}: DecimalInputProps) {
    const limits = { maxDecimals, maxIntegerDigits };
    const text = value === null || value === undefined ? '' : String(value);
    const hasSeparatorError =
        checkDecimalText(text, limits) === 'separator-error';
    const hint = decimalAmbiguityHint(text);
    const isEditable = !props.disabled && !props.readOnly;

    // El contenedor está siempre, aunque no haya mensaje: si apareciera solo con el mensaje, React volvería a montar
    // el campo y se perdería el foco mientras se escribe.
    return (
        <div className="space-y-1">
            <Input
                {...props}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                aria-invalid={hasSeparatorError || props['aria-invalid']}
                value={text}
                onChange={(event) => {
                    const next = event.target.value;

                    if (checkDecimalText(next, limits) !== 'reject') {
                        onValueChange(next);
                    }
                }}
            />
            {hasSeparatorError ? (
                <p role="alert" className="text-xs text-destructive">
                    {DECIMAL_SEPARATOR_ERROR}
                </p>
            ) : (
                hint &&
                isEditable && (
                    <p
                        role="status"
                        className="text-xs text-amber-700 dark:text-amber-400"
                    >
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
