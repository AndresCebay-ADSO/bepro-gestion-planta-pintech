import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { flushSync } from 'react-dom';

import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { buildSearchIndex, filterSearchIndex } from '@/lib/search-text';
import { cn } from '@/lib/utils';

export type ComboboxOptionType = {
    id: string | number;
    label: string;
    description?: string;
    disabled?: boolean;
};

interface ComboboxProps {
    options: ComboboxOptionType[];
    value: string | number | null;
    onChange: (value: string | number) => void;
    placeholder?: string;
    emptyText?: string;
    /** Clases del botón (el campo), no de la lista. */
    className?: string;
    disabled?: boolean;
    /** El `id` del disparador: la etiqueta lo señala y el foco va a él cuando el servidor rechaza el valor. */
    id?: string;
    /** Ayuda, error y obligatoriedad del campo (los enlaza `<FormField>`). */
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
    'aria-required'?: boolean;
}

/** Una tecla que escribe texto (letra, número, signo), sin atajos de Ctrl/Cmd/Alt. El espacio abre la lista. */
function isTypingKey(event: KeyboardEvent): boolean {
    return (
        event.key.length === 1 &&
        event.key !== ' ' &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey
    );
}

/**
 * Campo de selección con búsqueda: un botón con el aspecto del `Select` que abre la lista con un buscador arriba.
 * Con el botón enfocado, teclear abre la lista con ese texto ya en el buscador, como si se escribiera en el campo.
 */
export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Seleccionar...',
    emptyText = 'Sin resultados.',
    className,
    disabled = false,
    id,
    'aria-describedby': ariaDescribedBy,
    'aria-invalid': ariaInvalid,
    'aria-required': ariaRequired,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    // La opción resaltada en la lista (el `value` de `cmdk`), para que Tab pueda elegirla.
    const [highlighted, setHighlighted] = useState('');
    const triggerRef = useRef<HTMLButtonElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    // Al cerrar, el foco vuelve al botón (Escape, elegir); con Tab sigue al campo siguiente.
    const returnFocusOnClose = useRef(true);

    const selectedOption = options.find(
        (option) => String(option.id) === String(value),
    );

    // El filtro lo hace el componente, no `cmdk`: conserva el orden de `options` y normaliza los textos una sola vez.
    const searchIndex = useMemo(
        () =>
            buildSearchIndex(options, (option) => [
                option.label,
                option.description,
            ]),
        [options],
    );
    const visibleOptions = filterSearchIndex(searchIndex, search);

    const openWith = (text: string) => {
        setSearch(text);
        setHighlighted(selectedOption ? String(selectedOption.id) : '');
        setOpen(true);
    };

    // Elegir la opción que ya estaba elegida no avisa: el formulario borraría lo que depende de ella (lote, costo).
    const choose = (option: ComboboxOptionType) => {
        if (String(option.id) !== String(value)) {
            onChange(option.id);
        }
    };

    const handleTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openWith('');
        } else if (isTypingKey(event)) {
            // Si la lista ya se abrió pero el foco aún no llegó al buscador, la letra se suma a lo escrito.
            event.preventDefault();

            if (open) {
                setSearch((previous) => previous + event.key);
            } else {
                openWith(event.key);
            }
        }
    };

    /**
     * Tab sale del campo con una sola pulsación, como en un autocompletado: si se escribió algo, elige la opción
     * resaltada. El foco pasa al botón y el navegador sigue con su Tab normal desde ahí (al siguiente campo, o al
     * anterior con Shift+Tab). `flushSync` aplica la elección antes, para que el campo que depende de ella (p. ej. la
     * presentación) ya esté habilitado cuando el foco llegue.
     */
    const handleSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Tab') {
            return;
        }

        const option =
            search.trim() === ''
                ? undefined
                : visibleOptions.find(
                      (candidate) =>
                          String(candidate.id) === highlighted &&
                          !candidate.disabled,
                  );

        returnFocusOnClose.current = false;
        flushSync(() => {
            if (option) {
                choose(option);
            }

            setOpen(false);
        });
        triggerRef.current?.focus();
    };

    const handleOpenAutoFocus = (event: Event) => {
        event.preventDefault();
        returnFocusOnClose.current = true;
        const input = inputRef.current;

        if (input) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => (next ? openWith('') : setOpen(false))}
        >
            <PopoverTrigger asChild disabled={disabled}>
                {/* Mismo aspecto que `SelectTrigger` (ui/select.tsx): llena su columna y corta el valor con «…». */}
                <button
                    ref={triggerRef}
                    type="button"
                    id={id}
                    role="combobox"
                    aria-expanded={open}
                    aria-describedby={ariaDescribedBy}
                    aria-invalid={ariaInvalid}
                    aria-required={ariaRequired}
                    title={selectedOption?.label}
                    data-slot="combobox-trigger"
                    onKeyDown={handleTriggerKeyDown}
                    className={cn(
                        'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-base whitespace-nowrap shadow-xs md:text-sm transition-[color,box-shadow] outline-none hover:bg-muted/25 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                        className,
                    )}
                >
                    <span
                        className={cn(
                            'min-w-0 truncate text-left',
                            !selectedOption && 'text-muted-foreground',
                        )}
                    >
                        {selectedOption?.label ?? placeholder}
                    </span>
                    <ChevronDownIcon
                        className="pointer-events-none size-4 shrink-0 text-muted-foreground opacity-50"
                        aria-hidden="true"
                    />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                collisionPadding={8}
                onOpenAutoFocus={handleOpenAutoFocus}
                onCloseAutoFocus={(event) => {
                    if (!returnFocusOnClose.current) {
                        event.preventDefault();
                    }
                }}
                className="flex max-h-(--radix-popover-content-available-height) w-(--radix-popover-trigger-width) max-w-(--radix-popover-content-available-width) min-w-80 flex-col overflow-hidden p-0"
            >
                <Command
                    shouldFilter={false}
                    value={highlighted}
                    onValueChange={setHighlighted}
                    className="min-h-0 flex-1"
                >
                    <CommandInput
                        ref={inputRef}
                        value={search}
                        onValueChange={setSearch}
                        onKeyDown={handleSearchKeyDown}
                        placeholder="Buscar..."
                        className="text-base md:text-sm"
                    />
                    {/* ~10 opciones a la vista; si la pantalla es más baja, la lista se ajusta al espacio que queda. */}
                    <CommandList
                        className="max-h-80 min-h-0 flex-1 p-1"
                    >
                        <CommandEmpty className="px-2 py-4 text-center text-sm text-muted-foreground">
                            {emptyText}
                        </CommandEmpty>
                        {visibleOptions.map((option) => {
                            const isSelected =
                                String(option.id) === String(value);

                            return (
                                <CommandItem
                                    key={option.id}
                                    value={String(option.id)}
                                    disabled={option.disabled}
                                    title={option.label}
                                    onSelect={() => {
                                        choose(option);
                                        setOpen(false);
                                    }}
                                    className="cursor-pointer pr-8"
                                >
                                    {/* Un nombre largo baja a una segunda línea en vez de cortarse. */}
                                    <div className="flex min-w-0 flex-col">
                                        <span
                                            className={cn(
                                                'line-clamp-2 break-words',
                                                isSelected && 'font-medium',
                                            )}
                                        >
                                            {option.label}
                                        </span>
                                        {option.description && (
                                            <span className="line-clamp-2 text-xs break-words text-muted-foreground">
                                                {option.description}
                                            </span>
                                        )}
                                    </div>
                                    {isSelected && (
                                        <CheckIcon
                                            className="absolute right-2 size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                </CommandItem>
                            );
                        })}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
