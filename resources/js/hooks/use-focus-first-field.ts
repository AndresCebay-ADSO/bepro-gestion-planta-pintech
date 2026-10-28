import { useCallback, useEffect, useRef } from 'react';

/**
 * Un campo que se puede llenar: no los botones (la X de cerrar, «Cancelar»), sí `Select`, `Combobox` y fechas. Fuera
 * los ocultos a la vista o al teclado (`aria-hidden`, `tabindex="-1"`), como el `<select>` nativo que Radix monta junto
 * a cada `Select`.
 */
const FIELD_SELECTOR = [
    'input:not([type="hidden"]):not([disabled]):not([readonly])',
    'textarea:not([disabled]):not([readonly])',
    'select:not([disabled])',
    '[role="combobox"]:not([disabled])',
    '[data-slot="plant-date-input"]:not([disabled])',
]
    .map((field) => `${field}:not([aria-hidden="true"]):not([tabindex="-1"])`)
    .join(', ');

/** Cuánto se espera a que lleguen los campos de un formulario que carga sus opciones al abrir. */
const WAIT_FOR_FIELDS_MS = 10_000;

function firstField(container: HTMLElement): HTMLElement | null {
    return container.querySelector<HTMLElement>(FIELD_SELECTOR);
}

/**
 * Al abrir un cajón o una ventana con formulario, el foco va al primer campo y no a la X de cerrar (DESIGN.md §8).
 * Devuelve el manejador para `onOpenAutoFocus` de Radix.
 *
 * Solo actúa cuando el contenido tiene un cuerpo de formulario (`bodySelector`, el `SheetBody`/`DialogBody`); sin él
 * (el menú lateral del celular, un aviso) deja el comportamiento de Radix. Si los campos todavía no están (el cajón de
 * movimientos pide sus opciones al abrir y muestra un esqueleto), el foco espera en el contenido y pasa al primer campo
 * cuando aparece, salvo que el usuario ya lo haya movido.
 */
export function useFocusFirstField(
    bodySelector: string,
    onOpenAutoFocus?: (event: Event) => void,
) {
    const observerRef = useRef<MutationObserver | null>(null);
    const timeoutRef = useRef<number | null>(null);

    const stopWaiting = useCallback(() => {
        observerRef.current?.disconnect();
        observerRef.current = null;

        if (timeoutRef.current !== null) {
            window.clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }
    }, []);

    useEffect(() => stopWaiting, [stopWaiting]);

    return useCallback(
        (event: Event) => {
            onOpenAutoFocus?.(event);

            const content = event.currentTarget;

            if (
                event.defaultPrevented ||
                !(content instanceof HTMLElement) ||
                content.querySelector(bodySelector) === null
            ) {
                return;
            }

            event.preventDefault();
            stopWaiting();

            const field = firstField(content);

            if (field) {
                field.focus();

                return;
            }

            content.focus();

            observerRef.current = new MutationObserver(() => {
                const next = firstField(content);

                if (next === null) {
                    return;
                }

                stopWaiting();

                // Solo si el foco sigue en el contenido: no se le quita al usuario lo que ya enfocó.
                if (
                    document.activeElement === content ||
                    document.activeElement === document.body
                ) {
                    next.focus();
                }
            });
            observerRef.current.observe(content, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['disabled'],
            });
            timeoutRef.current = window.setTimeout(
                stopWaiting,
                WAIT_FOR_FIELDS_MS,
            );
        },
        [bodySelector, onOpenAutoFocus, stopWaiting],
    );
}
