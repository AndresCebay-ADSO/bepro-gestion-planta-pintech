import * as React from 'react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, ChevronsUpDown } from 'lucide-react';
import { DayPicker, useDayPicker, type CalendarMonth } from 'react-day-picker';

import { cn } from '@/lib/utils';

export type CalendarProps = React.ComponentProps<typeof DayPicker> & {
    startYear?: number;
    endYear?: number;
};

function CustomMonthCaption({
    calendarMonth,
    startYear = 2021,
    endYear = new Date().getFullYear() + 5,
}: {
    calendarMonth: CalendarMonth;
    startYear?: number;
    endYear?: number;
}) {
    const { previousMonth, nextMonth, goToMonth } = useDayPicker();
    const currentYear = calendarMonth.date.getFullYear();
    const currentMonthIdx = calendarMonth.date.getMonth();

    const years = React.useMemo(() => {
        const list: number[] = [];
        for (let y = startYear; y <= endYear; y++) {
            list.push(y);
        }
        return list;
    }, [startYear, endYear]);

    const handleYearChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newYear = parseInt(e.target.value, 10);
        goToMonth(new Date(newYear, currentMonthIdx, 1));
    };

    return (
        <div className="flex items-center justify-center pt-1 pb-1 mb-1 w-full">
            <button
                type="button"
                disabled={!previousMonth}
                onClick={() => previousMonth && goToMonth(previousMonth)}
                className="h-7 w-7 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-accent/70 transition-colors disabled:opacity-30 disabled:pointer-events-none shrink-0"
                aria-label="Mes anterior"
            >
                <ChevronLeft className="h-4 w-4" />
            </button>

            <div className="flex items-center justify-center gap-1.5 w-[165px] px-1">
                <span className="text-sm font-semibold capitalize text-foreground select-none truncate">
                    {format(calendarMonth.date, 'MMMM', { locale: es })}
                </span>

                <div className="relative inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md hover:bg-accent/60 transition-colors cursor-pointer border border-transparent hover:border-border/60 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 focus-within:border-ring shrink-0">
                    <span className="text-sm font-semibold text-foreground select-none">
                        {currentYear}
                    </span>
                    <ChevronsUpDown className="h-3 w-3 text-muted-foreground opacity-70" />
                    <select
                        aria-label="Seleccionar año"
                        value={currentYear}
                        onChange={handleYearChange}
                        className="absolute inset-0 opacity-0 cursor-pointer w-full h-full focus:outline-hidden"
                    >
                        {years.map((y) => (
                            <option key={y} value={y}>
                                {y}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <button
                type="button"
                disabled={!nextMonth}
                onClick={() => nextMonth && goToMonth(nextMonth)}
                className="h-7 w-7 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-accent/70 transition-colors disabled:opacity-30 disabled:pointer-events-none shrink-0"
                aria-label="Mes siguiente"
            >
                <ChevronRight className="h-4 w-4" />
            </button>
        </div>
    );
}

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    locale = es,
    startYear,
    endYear,
    ...props
}: CalendarProps) {
    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            locale={locale}
            hideNavigation={true}
            className={cn('p-3', className)}
            classNames={{
                months: 'flex flex-col sm:flex-row gap-4',
                month: 'space-y-3',
                month_caption: 'flex justify-center items-center',
                caption_label: 'text-sm font-semibold capitalize',
                month_grid: 'w-full border-collapse space-y-1',
                weekdays: 'flex justify-between mb-1',
                weekday:
                    'text-muted-foreground w-9 text-center font-medium text-[0.75rem] uppercase tracking-wider',
                weeks: 'space-y-1',
                week: 'flex w-full mt-1 justify-between',
                day: 'relative p-0 text-center text-sm focus-within:relative focus-within:z-20 h-9 w-9 flex items-center justify-center first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md',
                day_button:
                    'h-8 w-8 p-0 font-normal rounded-md transition-all text-foreground hover:bg-primary/20 hover:text-primary active:scale-95 inline-flex items-center justify-center select-none',
                range_start:
                    'rounded-l-md bg-primary/15 [&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:font-semibold [&>button]:shadow-xs hover:[&>button]:bg-primary/90 hover:[&>button]:text-primary-foreground',
                range_end:
                    'rounded-r-md bg-primary/15 [&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:font-semibold [&>button]:shadow-xs hover:[&>button]:bg-primary/90 hover:[&>button]:text-primary-foreground',
                selected:
                    '[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:font-semibold [&>button]:shadow-xs',
                today: '[&>button]:border [&>button]:border-primary/60 [&>button]:text-primary [&>button]:font-bold',
                outside:
                    'text-muted-foreground/40 opacity-40 aria-selected:opacity-70 aria-selected:text-muted-foreground',
                disabled: 'text-muted-foreground/30 opacity-30 pointer-events-none',
                range_middle:
                    '!bg-primary/15 !rounded-none [&>button]:!bg-transparent [&>button]:!text-foreground [&>button]:!font-medium [&>button]:!shadow-none hover:!bg-primary/25 hover:[&>button]:!text-foreground hover:[&>button]:!bg-transparent',

                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                MonthCaption: (captionProps) => (
                    <CustomMonthCaption
                        {...captionProps}
                        startYear={startYear}
                        endYear={endYear}
                    />
                ),
            }}
            {...props}
        />
    );
}
Calendar.displayName = 'Calendar';

export { Calendar };
