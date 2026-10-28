import * as React from 'react';
import {
    endOfMonth,
    endOfWeek,
    endOfYear,
    format,
    isValid,
    parseISO,
    startOfMonth,
    startOfToday,
    startOfWeek,
    startOfYear,
    subDays,
    subMonths,
    subWeeks,
    subYears,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon, ChevronsUpDown, X } from 'lucide-react';
import type { DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { toPlantDate } from '@/lib/plant-date';
import { cn } from '@/lib/utils';

export type DateRangePickerProps = {
    valueFrom?: string | null;
    valueTo?: string | null;
    onChange: (from: string | undefined, to: string | undefined) => void;
    label?: string;
    placeholder?: string;
    className?: string;
    align?: 'start' | 'center' | 'end';
    disabled?: boolean;
    showPresets?: boolean;
};

type Preset = {
    label: string;
    getValue: () => { from: Date; to: Date };
};

const PRESETS: Preset[] = [
    {
        label: 'Hoy',
        getValue: () => {
            const today = startOfToday();
            return {
                from: today,
                to: today,
            };
        },
    },
    {
        label: 'Ayer',
        getValue: () => {
            const yesterday = subDays(startOfToday(), 1);
            return {
                from: yesterday,
                to: yesterday,
            };
        },
    },
    {
        label: 'Esta semana',
        getValue: () => {
            const today = startOfToday();
            return {
                from: startOfWeek(today, { weekStartsOn: 1 }),
                to: endOfWeek(today, { weekStartsOn: 1 }),
            };
        },
    },
    {
        label: 'Semana anterior',
        getValue: () => {
            const lastWeek = subWeeks(startOfToday(), 1);
            return {
                from: startOfWeek(lastWeek, { weekStartsOn: 1 }),
                to: endOfWeek(lastWeek, { weekStartsOn: 1 }),
            };
        },
    },
    {
        label: 'Este mes',
        getValue: () => {
            const today = startOfToday();
            return {
                from: startOfMonth(today),
                to: endOfMonth(today),
            };
        },
    },
    {
        label: 'Mes anterior',
        getValue: () => {
            const lastMonth = subMonths(startOfToday(), 1);
            return {
                from: startOfMonth(lastMonth),
                to: endOfMonth(lastMonth),
            };
        },
    },
    {
        label: 'Últimos 30 días',
        getValue: () => ({
            from: subDays(startOfToday(), 29),
            to: startOfToday(),
        }),
    },
    {
        label: 'Este año',
        getValue: () => {
            const today = startOfToday();
            return {
                from: startOfYear(today),
                to: endOfYear(today),
            };
        },
    },
    {
        label: 'Año anterior',
        getValue: () => {
            const lastYear = subYears(startOfToday(), 1);
            return {
                from: startOfYear(lastYear),
                to: endOfYear(lastYear),
            };
        },
    },
];

function safeParseDate(value?: string | null): Date | undefined {
    if (!value) return undefined;
    const parts = value.split('-');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        const date = new Date(year, month, day);
        if (isValid(date)) return date;
    }
    const parsed = parseISO(value);
    return isValid(parsed) ? parsed : undefined;
}

export function DateRangePicker({
    valueFrom,
    valueTo,
    onChange,
    label,
    placeholder,
    className,
    align = 'start',
    disabled = false,
    showPresets = true,
}: DateRangePickerProps) {
    const [open, setOpen] = React.useState(false);

    const fromDate = React.useMemo(
        () => safeParseDate(valueFrom),
        [valueFrom],
    );
    const toDate = React.useMemo(() => safeParseDate(valueTo), [valueTo]);

    // Local draft range while interacting inside popover
    const [draftRange, setDraftRange] = React.useState<DateRange | undefined>(
        () => ({
            from: fromDate,
            to: toDate,
        }),
    );

    // Current displayed calendar month (positioned on `to` or `from` or today)
    const [calendarMonth, setCalendarMonth] = React.useState<Date>(
        () => toDate ?? fromDate ?? new Date(),
    );

    // When popover opens or external values change, sync local state
    React.useEffect(() => {
        setDraftRange({
            from: fromDate,
            to: toDate,
        });
        if (toDate) {
            setCalendarMonth(toDate);
        } else if (fromDate) {
            setCalendarMonth(fromDate);
        }
    }, [fromDate, toDate, open]);

    const hasValue = Boolean(fromDate || toDate);

    const handleApplyPreset = (preset: Preset) => {
        const { from, to } = preset.getValue();
        const fromStr = toPlantDate(from);
        const toStr = toPlantDate(to);
        setDraftRange({ from, to });
        setCalendarMonth(to);
        onChange(fromStr, toStr);
        setOpen(false);
    };

    const isPresetActive = (preset: Preset) => {
        const activeFrom = draftRange?.from ?? fromDate;
        const activeTo = draftRange?.to ?? toDate;
        if (!activeFrom || !activeTo) return false;

        const { from: pFrom, to: pTo } = preset.getValue();
        return (
            toPlantDate(activeFrom) === toPlantDate(pFrom) &&
            toPlantDate(activeTo) === toPlantDate(pTo)
        );
    };

    const handleApplyDraft = () => {
        if (!draftRange?.from) {
            onChange(undefined, undefined);
            setOpen(false);
            return;
        }

        const fromStr = toPlantDate(draftRange.from);
        const toStr = toPlantDate(draftRange.to ?? draftRange.from);
        onChange(fromStr, toStr);
        setOpen(false);
    };

    const handleCancel = () => {
        // Reset draft back to committed props
        setDraftRange({
            from: fromDate,
            to: toDate,
        });
        setOpen(false);
    };

    const handleClear = (e?: React.SyntheticEvent) => {
        e?.stopPropagation();
        setDraftRange(undefined);
        onChange(undefined, undefined);
    };

    const formattedDisplayText = React.useMemo(() => {
        if (!fromDate && !toDate) {
            return placeholder ?? label ?? 'Filtrar por fecha';
        }

        if (fromDate && toDate) {
            const formattedFrom = format(fromDate, 'd MMM yyyy', {
                locale: es,
            });
            const formattedTo = format(toDate, 'd MMM yyyy', { locale: es });

            if (formattedFrom === formattedTo) {
                return formattedFrom;
            }

            // Same year: e.g. "01 Feb - 27 Feb 2026"
            if (fromDate.getFullYear() === toDate.getFullYear()) {
                const shortFrom = format(fromDate, 'd MMM', { locale: es });
                return `${shortFrom} - ${formattedTo}`;
            }

            return `${formattedFrom} - ${formattedTo}`;
        }

        if (fromDate) {
            return `Desde ${format(fromDate, 'd MMM yyyy', { locale: es })}`;
        }

        if (toDate) {
            return `Hasta ${format(toDate, 'd MMM yyyy', { locale: es })}`;
        }

        return placeholder ?? label ?? 'Filtrar por fecha';
    }, [fromDate, toDate, label, placeholder]);

    return (
        <div className={cn('relative inline-flex items-center', className)}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        className={cn(
                            'h-9 w-full justify-between font-normal text-left shadow-xs transition-colors',
                            hasValue &&
                                'pr-8 border-primary/40 bg-primary/5 text-foreground font-medium',
                            !hasValue && 'text-muted-foreground',
                        )}
                    >
                        <div className="flex items-center gap-2 truncate">
                            <CalendarIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="truncate">{formattedDisplayText}</span>
                        </div>

                        {!hasValue && (
                            <ChevronsUpDown className="h-3.5 w-3.5 opacity-40 shrink-0 ml-2" />
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                align={align}
                collisionPadding={8}
                className="w-auto max-w-(--radix-popover-content-available-width) max-h-(--radix-popover-content-available-height) overflow-y-auto p-0 shadow-2xl border-border bg-popover rounded-xl"
            >
                <div className="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-border">
                    {showPresets && (
                        // En el celular, los atajos son una fila de pastillas que se desplaza de lado, encima del
                        // calendario; desde `sm`, una columna a la izquierda.
                        <div className="flex flex-col gap-1 bg-muted/20 p-3 sm:min-w-[150px]">
                            <span className="px-2 pb-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Atajos rápidos
                            </span>
                            {/* `w-0 min-w-full`: la fila no ensancha la ventanita; toma el ancho del calendario y se desplaza. */}
                            <div className="flex w-0 min-w-full gap-1.5 overflow-x-auto pb-1 sm:w-auto sm:min-w-0 sm:flex-col sm:gap-1 sm:overflow-visible sm:pb-0">
                                {PRESETS.map((preset) => {
                                    const active = isPresetActive(preset);
                                    return (
                                        <Button
                                            key={preset.label}
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleApplyPreset(preset)}
                                            className={cn(
                                                'h-8 shrink-0 rounded-full border px-3 text-sm font-normal transition-colors sm:h-7 sm:justify-start sm:rounded-md sm:border-0 sm:px-2.5 sm:text-left sm:text-xs',
                                                active
                                                    ? 'bg-primary text-primary-foreground font-semibold hover:bg-primary/90 hover:text-primary-foreground'
                                                    : 'hover:bg-primary/10 hover:text-primary',
                                            )}
                                        >
                                            {preset.label}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <div className="p-2 flex flex-col justify-between">
                        <Calendar
                            mode="range"
                            month={calendarMonth}
                            onMonthChange={setCalendarMonth}
                            selected={draftRange}
                            onSelect={setDraftRange}
                            numberOfMonths={1}
                            locale={es}
                        />

                        <div className="flex items-center justify-between border-t border-border pt-2.5 px-2 pb-1 mt-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setDraftRange(undefined);
                                    onChange(undefined, undefined);
                                    setOpen(false);
                                }}
                                className="h-8 text-sm text-muted-foreground hover:text-destructive px-2.5"
                            >
                                Limpiar
                            </Button>

                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleCancel}
                                    className="h-8 text-sm px-3"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    onClick={handleApplyDraft}
                                    className="h-8 text-sm px-3.5 font-medium shadow-xs"
                                >
                                    Aplicar
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>

        {hasValue && !disabled && (
            <button
                type="button"
                aria-label="Limpiar rango de fecha"
                onClick={handleClear}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 inline-flex h-5 w-5 items-center justify-center rounded-xs opacity-60 transition-opacity hover:opacity-100 hover:text-destructive focus:outline-hidden focus:ring-2 focus:ring-ring"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        )}
    </div>
);
}
