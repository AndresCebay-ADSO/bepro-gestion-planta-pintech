import { useRef, useState } from 'react';
import type {
    ChangeEvent,
    ClipboardEvent,
    CompositionEvent,
    FocusEvent,
    SyntheticEvent,
} from 'react';

import { applyNumericInput, applyNumericPaste } from '@/lib/numeric-input';
import type { NumericField, NumericSelection } from '@/lib/numeric-input';

/** Los props del campo que el hook también usa: los del que llama se ejecutan primero. */
type NumericInputProps = {
    readOnly?: boolean;
    onPaste?: (event: ClipboardEvent<HTMLInputElement>) => void;
    onSelect?: (event: SyntheticEvent<HTMLInputElement>) => void;
    onCompositionEnd?: (event: CompositionEvent<HTMLInputElement>) => void;
    onBlur?: (event: FocusEvent<HTMLInputElement>) => void;
};

type UseNumericFieldOptions = {
    field: NumericField;
    /** El texto que guarda el formulario, sin miles. */
    text: string;
    onValueChange: (value: string) => void;
    /** Los props que recibió el campo (`readOnly` y los eventos de arriba). */
    inputProps: NumericInputProps;
    /** Se llama cuando el usuario cambia el valor (DecimalInput lo usa para el aviso de ambigüedad). */
    onEdited?: () => void;
    /** Aviso de lo pegado (`pastedThousandsHint`) y el valor que quedó; `null` al teclear. */
    onPasteHint?: (hint: string | null, value: string) => void;
    /** Cómo se avisa lo pegado; sin él, no hay aviso (en un campo de enteros, `1.250` no puede ser un decimal). */
    pasteHint?: (pasted: string) => string | null;
};

/**
 * Lo que comparten DecimalInput e IntegerInput (una sola fuente): el valor que se ve, con miles, y los eventos que
 * traducen lo tecleado, borrado o pegado al texto que guarda el formulario. Reglas en `@/lib/numeric-input`.
 *
 * El campo escribe en el DOM lo que se ve y el cursor antes de que React vuelva a pintar; por eso `onValueChange` tiene
 * que actualizar el estado en el mismo evento (como `form.setData`). Con un `debounce` o `startTransition` el cursor
 * saltaría.
 */
export function useNumericField({
    field,
    text,
    onValueChange,
    inputProps,
    onEdited,
    onPasteHint,
    pasteHint,
}: UseNumericFieldOptions) {
    // Lo que estaba seleccionado antes del cambio: con selección, el tramo editado no se adivina comparando.
    const selection = useRef<NumericSelection | null>(null);
    // Mientras el teclado compone (IME de Android con el teclado completo), el campo muestra lo que el teclado escribe y
    // no se reescribe: reescribirlo en medio de la composición duplica o se come dígitos. Se aplica al terminar.
    const [composing, setComposing] = useState<string | null>(null);

    const changeTo = (next: string, hint: string | null = null) => {
        if (next !== text) {
            onEdited?.();
            onValueChange(next);
            onPasteHint?.(hint, next);
        }
    };

    // Termina una composición: con `compositionend` o, si el navegador no lo manda, al salir del campo.
    const finishComposition = (input: HTMLInputElement, event: Event) => {
        setComposing(null);
        selection.current = null;
        changeTo(applyNumericInput(input, event, field, text));
    };

    return {
        value: composing ?? field.format(text),
        onSelect: (event: SyntheticEvent<HTMLInputElement>) => {
            inputProps.onSelect?.(event);

            const input = event.currentTarget;

            selection.current = {
                start: input.selectionStart ?? 0,
                end: input.selectionEnd ?? 0,
                display: input.value,
            };
        },
        onChange: (event: ChangeEvent<HTMLInputElement>) => {
            const native = event.nativeEvent;

            if ('isComposing' in native && native.isComposing) {
                setComposing(event.currentTarget.value);

                return;
            }

            const before = selection.current ?? undefined;

            selection.current = null;
            changeTo(
                applyNumericInput(
                    event.currentTarget,
                    native,
                    field,
                    text,
                    before,
                ),
            );
        },
        onCompositionEnd: (event: CompositionEvent<HTMLInputElement>) => {
            inputProps.onCompositionEnd?.(event);
            finishComposition(event.currentTarget, event.nativeEvent);
        },
        onBlur: (event: FocusEvent<HTMLInputElement>) => {
            if (composing !== null) {
                finishComposition(event.currentTarget, event.nativeEvent);
            }

            inputProps.onBlur?.(event);
        },
        onPaste: (event: ClipboardEvent<HTMLInputElement>) => {
            inputProps.onPaste?.(event);

            // En un campo de solo lectura el navegador no pega, pero el evento llega igual.
            if (event.defaultPrevented || inputProps.readOnly) {
                return;
            }

            event.preventDefault();

            const pasted = event.clipboardData.getData('text');

            // Sin texto (una imagen en el portapapeles): no se pega nada, ni se borra lo seleccionado.
            if (pasted === '') {
                return;
            }

            selection.current = null;
            changeTo(
                applyNumericPaste(event.currentTarget, pasted, field, text),
                pasteHint?.(pasted) ?? null,
            );
        },
    };
}
