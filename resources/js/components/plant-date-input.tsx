import { usePage } from '@inertiajs/react';
import { CalendarIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

import { formatSafeDate } from '@/components/formatted-date';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    isPlantDate,
    isPlantDateSelectable,
    parsePlantDate,
    plantDateBounds,
    plantDateInitialMonth,
    plantDateMonthRange,
    toPlantDate,
} from '@/lib/plant-date';
import { cn } from '@/lib/utils';

type PlantDateInputProps = {
    /** El `id` del botón: la etiqueta lo señala y el foco va a él cuando el servidor rechaza la fecha. */
    id?: string;
    /** Fecha de planta (`YYYY-MM-DD`) o `''` sin fecha. */
    value: string;
    onChange: (value: string) => void;
    min?: string | null;
    max?: string | null;
    /** No después de hoy en la planta. */
    notFuture?: boolean;
    /** No antes de hoy en la planta. */
    notPast?: boolean;
    /** La fecha ya guardada: se puede conservar aunque quede fuera de los límites (como `PlantDate::allowing()`). */
    saved?: string | null;
    /** Muestra «Quitar fecha» para dejar el campo vacío (solo en campos opcionales). */
    clearable?: boolean;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

/**
 * Campo de fecha de planta con el calendario de la app (DESIGN.md §8), nunca el selector nativo del navegador. Concentra
 * los límites de cada formulario: `notFuture`/`notPast` contra el «hoy» de la planta (`plantToday`), `min`/`max` y la
 * fecha guardada que se puede conservar. El valor por defecto (normalmente `plantToday`) lo pone el formulario.
 */
export function PlantDateInput({
    id,
    value,
    onChange,
    min,
    max,
    notFuture = false,
    notPast = false,
    saved,
    clearable = false,
    placeholder = 'Selecciona una fecha',
    disabled = false,
    className,
    'aria-describedby': ariaDescribedBy,
    'aria-invalid': ariaInvalid,
}: PlantDateInputProps) {
    const { plantToday } = usePage().props;
    const [open, setOpen] = useState(false);
    const bounds = useMemo(
        () =>
            plantDateBounds({
                today: plantToday,
                min,
                max,
                notFuture,
                notPast,
            }),
        [plantToday, min, max, notFuture, notPast],
    );
    const range = plantDateMonthRange(bounds, plantToday, value, saved);
    const startMonth = parsePlantDate(`${range.startMonth}-01`);
    const endMonth = parsePlantDate(`${range.endMonth}-01`);
    const selected = parsePlantDate(value);
    const [month, setMonth] = useState<Date>(
        () =>
            parsePlantDate(
                `${plantDateInitialMonth(value, bounds, plantToday)}-01`,
            ) ?? new Date(),
    );
    const canPickToday = isPlantDateSelectable(plantToday, bounds, saved);

    const handleOpenChange = (next: boolean) => {
        if (next) {
            // Cada apertura empieza en el mes del valor (o de hoy), no en el último que se miró.
            setMonth(
                parsePlantDate(
                    `${plantDateInitialMonth(value, bounds, plantToday)}-01`,
                ) ?? new Date(),
            );
        }

        setOpen(next);
    };

    const pick = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild disabled={disabled}>
                {/* Mismo aspecto que `SelectTrigger`: llena su columna y corta el texto con «…». */}
                <button
                    type="button"
                    id={id}
                    aria-haspopup="dialog"
                    aria-describedby={ariaDescribedBy}
                    aria-invalid={ariaInvalid}
                    data-slot="plant-date-input"
                    className={cn(
                        'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-base whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none hover:bg-muted/25 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40',
                        className,
                    )}
                >
                    <span
                        className={cn(
                            'min-w-0 truncate text-left',
                            !isPlantDate(value) && 'text-muted-foreground',
                        )}
                    >
                        {isPlantDate(value)
                            ? formatSafeDate(value, 'short')
                            : placeholder}
                    </span>
                    <CalendarIcon
                        className="pointer-events-none size-4 shrink-0 text-muted-foreground opacity-50"
                        aria-hidden="true"
                    />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                collisionPadding={8}
                className="max-h-(--radix-popover-content-available-height) w-auto overflow-y-auto p-0"
            >
                <Calendar
                    mode="single"
                    // Tocar el día ya elegido no lo quita: el campo se vacía con «Quitar fecha».
                    required
                    autoFocus
                    today={parsePlantDate(plantToday)}
                    selected={selected}
                    onSelect={(date) => date && pick(toPlantDate(date))}
                    month={month}
                    onMonthChange={setMonth}
                    startMonth={startMonth}
                    endMonth={endMonth}
                    startYear={startMonth?.getFullYear()}
                    endYear={endMonth?.getFullYear()}
                    disabled={(date) =>
                        !isPlantDateSelectable(toPlantDate(date), bounds, saved)
                    }
                />
                {(canPickToday || (clearable && value !== '')) && (
                    <div className="flex items-center justify-between gap-2 border-t px-3 py-2">
                        {clearable && value !== '' ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => pick('')}
                            >
                                Quitar fecha
                            </Button>
                        ) : (
                            <span />
                        )}
                        {canPickToday && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => pick(plantToday)}
                            >
                                Hoy
                            </Button>
                        )}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
