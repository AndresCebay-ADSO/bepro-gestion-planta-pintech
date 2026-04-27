import {
    Combobox as HeadlessCombobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    ComboboxButton,
    Transition,
    Portal
} from '@headlessui/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState, Fragment } from 'react';
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
    className?: string;
    disabled?: boolean;
}

export function Combobox({
    options,
    value,
    onChange,
    placeholder = "Seleccionar...",
    emptyText = "Sin resultados.",
    className,
    disabled = false
}: ComboboxProps) {
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const selectedOption = options.find((o) => String(o.id) === String(value));

    const filteredOptions = query === ''
        ? options
        : options.filter((option) =>
            option.label
                .toLowerCase()
                .replace(/\s+/g, '')
                .includes(query.toLowerCase().replace(/\s+/g, ''))
        );

    return (
        <div className={cn("w-full", className)}>
            <HeadlessCombobox
                value={value ?? undefined}
                onChange={(v) => {
                    if (v !== null && v !== undefined) {
                        onChange(v as string | number);
                        setQuery('');
                        setIsOpen(false);
                    }
                }}
                disabled={disabled}
            >
                <div className="relative">
                    <ComboboxButton className={cn(
                        "flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-hidden transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50",
                        disabled && "cursor-not-allowed opacity-50"
                    )}>
                        <ComboboxInput
                            className="w-full border-none bg-transparent p-0 text-sm text-foreground outline-hidden placeholder:text-muted-foreground focus:ring-0"
                            displayValue={() => selectedOption?.label || ''}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={placeholder}
                        />
                        <div className="flex shrink-0 items-center opacity-50">
                            <ChevronsUpDown className="h-4 w-4" aria-hidden="true" />
                        </div>
                    </ComboboxButton>

                    <Portal>
                        <Transition
                            as={Fragment}
                            leave="transition ease-in duration-100"
                            leaveFrom="opacity-100"
                            leaveTo="opacity-0"
                        >
                            <ComboboxOptions
                                anchor="bottom start"
                                className="z-[100] mt-1 max-h-48 min-w-64 overflow-auto rounded-md border border-border bg-popover py-1 text-base shadow-lg outline-hidden sm:text-sm [--anchor-gap:4px]"
                            >
                                {filteredOptions.length === 0 ? (
                                    <div className="relative cursor-default select-none px-4 py-2 text-muted-foreground">
                                        {emptyText}
                                    </div>
                                ) : (
                                    filteredOptions.map((option) => (
                                        <ComboboxOption
                                            key={option.id}
                                            className={({ active }) =>
                                                cn(
                                                    "relative cursor-pointer select-none py-1.5 pl-3 pr-9 transition-colors outline-none",
                                                    active ? "bg-accent text-accent-foreground" : "text-foreground"
                                                )
                                            }
                                            value={option.id}
                                            disabled={option.disabled}
                                        >
                                            {({ selected }) => (
                                                <>
                                                    <div className="flex flex-col">
                                                        <span className={cn("block truncate", selected ? "font-medium" : "font-normal")}>
                                                            {option.label}
                                                        </span>
                                                        {option.description && (
                                                            <span className="block truncate text-xs opacity-70">
                                                                {option.description}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {selected ? (
                                                        <span className="absolute inset-y-0 right-0 flex items-center pr-3">
                                                            <Check className="h-4 w-4" aria-hidden="true" />
                                                        </span>
                                                    ) : null}
                                                </>
                                            )}
                                        </ComboboxOption>
                                    ))
                                )}
                            </ComboboxOptions>
                        </Transition>
                    </Portal>
                </div>
            </HeadlessCombobox>
        </div>
    );
}
