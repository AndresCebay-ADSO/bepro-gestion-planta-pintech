import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import { checkIntegerText, INTEGER_SEPARATOR_ERROR } from '@/lib/numeric-input';

type IntegerInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'inputMode' | 'value' | 'onChange'
> & {
    value: string | number | null | undefined;
    onValueChange: (value: string) => void;
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
    maxDigits,
    ...props
}: IntegerInputProps) {
    const text = value === null || value === undefined ? '' : String(value);
    const hasSeparatorError =
        checkIntegerText(text, maxDigits) === 'separator-error';

    // Contenedor fijo y mensaje con el ancho del campo, como en DecimalInput.
    return (
        <div className="space-y-1">
            <Input
                {...props}
                type="text"
                inputMode="numeric"
                // Dentro de un <form>, el navegador no deja enviar `3.5`, como hacía `step="1"`: el servidor aún acepta
                // decimales en las unidades (pasan a enteras en el PR 5 de la auditoría 3.6).
                pattern="[0-9]*"
                autoComplete="off"
                aria-invalid={hasSeparatorError || props['aria-invalid']}
                value={text}
                onChange={(event) => {
                    const next = event.target.value;

                    if (checkIntegerText(next, maxDigits) !== 'reject') {
                        onValueChange(next);
                    }
                }}
            />
            {hasSeparatorError && (
                <p
                    role="alert"
                    className="w-0 min-w-full text-xs text-destructive"
                >
                    {INTEGER_SEPARATOR_ERROR}
                </p>
            )}
        </div>
    );
}
