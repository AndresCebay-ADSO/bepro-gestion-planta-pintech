import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import {
    acceptsEdit,
    checkIntegerText,
    INTEGER_SEPARATOR_ERROR,
    integerLimitsMessage,
} from '@/lib/numeric-input';
import { cn } from '@/lib/utils';

type IntegerInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'inputMode' | 'value' | 'onChange'
> & {
    value: string | number | null | undefined;
    onValueChange: (value: string) => void;
    /** Clases del contenedor (campo y mensaje), p. ej. `flex-1` dentro de una fila flex. */
    containerClassName?: string;
    maxDigits?: number;
};

/**
 * Campo de enteros del sistema (unidades, envases, etiquetas): solo dígitos, sin rueda del mouse ni `e`. Un punto o una
 * coma se dejan escritos y marcan el error, en vez de desaparecer y convertir `3,5` en 35 (auditoría 3.6). Reglas en
 * `@/lib/numeric-input`.
 */
export function IntegerInput({
    value,
    onValueChange,
    containerClassName,
    maxDigits,
    ...props
}: IntegerInputProps) {
    const text = value === null || value === undefined ? '' : String(value);
    const textCheck = checkIntegerText(text, maxDigits);
    const error =
        textCheck === 'separator-error'
            ? INTEGER_SEPARATOR_ERROR
            : textCheck === 'reject'
              ? integerLimitsMessage(maxDigits)
              : null;

    // Contenedor fijo y mensaje con el ancho del campo, como en DecimalInput.
    return (
        <div className={cn('space-y-1', containerClassName)}>
            <Input
                {...props}
                type="text"
                inputMode="numeric"
                // Dentro de un <form>, el navegador no deja enviar `3.5`, como hacía `step="1"`: el servidor aún acepta
                // decimales en las unidades (pasan a enteras en el PR 5 de la auditoría 3.6).
                pattern="[0-9]*"
                autoComplete="off"
                aria-invalid={error !== null || props['aria-invalid']}
                value={text}
                onChange={(event) => {
                    const next = event.target.value;

                    if (
                        acceptsEdit(next, text, (value) =>
                            checkIntegerText(value, maxDigits),
                        )
                    ) {
                        onValueChange(next);
                    }
                }}
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
