import { Search, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type FilterField =
    | {
          type: 'text';
          name: string;
          label: string;
          placeholder?: string;
      }
    | {
          type: 'select';
          name: string;
          label: string;
          options: { value: string; label: string }[];
          allValue?: string;
      }
    | {
          type: 'date-range';
          nameFrom: string;
          nameTo: string;
          label: string;
      };

type DataTableFiltersProps = {
    fields: FilterField[];
    filters: Record<string, string | null | undefined>;
    defaultFilters?: Record<string, string | null | undefined>;
    onFilter: (
        keyOrUpdates: string | Record<string, string | undefined>,
        value?: string | undefined,
    ) => void;
    onFilterImmediate?: (
        keyOrUpdates: string | Record<string, string | undefined>,
        value?: string | undefined,
    ) => void;
    onClear: () => void;
};

export function DataTableFilters({
    fields,
    filters,
    defaultFilters,
    onFilter,
    onFilterImmediate,
    onClear,
}: DataTableFiltersProps) {
    const handleImmediate = onFilterImmediate ?? onFilter;

    const hasActiveFilters = fields.some((field) => {
        if (field.type === 'date-range') {
            const defaultFrom = defaultFilters?.[field.nameFrom] ?? '';
            const defaultTo = defaultFilters?.[field.nameTo] ?? '';

            return (
                (filters[field.nameFrom] ?? '') !== defaultFrom ||
                (filters[field.nameTo] ?? '') !== defaultTo
            );
        }

        const defaultValue = defaultFilters?.[field.name] ?? '';
        const rawValue = filters[field.name] ?? '';
        const normalizedValue =
            field.type === 'select' && rawValue === '__all__'
                ? ''
                : rawValue;

        return normalizedValue !== defaultValue;
    });

    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
            {fields.map((field) => {
                if (field.type === 'text') {
                    return (
                        <div key={field.name} className="relative flex-1">
                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                aria-label={field.label}
                                placeholder={field.placeholder ?? field.label}
                                value={filters[field.name] ?? ''}
                                onChange={(e) =>
                                    onFilter(field.name, e.target.value)
                                }
                                className="pl-9"
                            />
                        </div>
                    );
                }

                if (field.type === 'select') {
                    const allItemValue = field.allValue ?? '__all__';
                    const rawValue = filters[field.name] ?? '';
                    const selectValue =
                        rawValue === '__all__' && !field.allValue
                            ? ''
                            : rawValue;

                    return (
                        <Select
                            key={field.name}
                            value={selectValue}
                            onValueChange={(value) => {
                                const resolvedValue =
                                    value === '__all__'
                                        ? (defaultFilters?.[field.name] ?? '')
                                        : value;

                                handleImmediate(field.name, resolvedValue);
                            }}
                        >
                            <SelectTrigger
                                aria-label={field.label}
                                className="w-full md:w-52"
                            >
                                <SelectValue placeholder={field.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={allItemValue}>
                                    Todos
                                </SelectItem>
                                {field.options
                                    .filter((opt) => opt.value !== '')
                                    .map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    );
                }

                if (field.type === 'date-range') {
                    return (
                        <DateRangePicker
                            key={`${field.nameFrom}-${field.nameTo}`}
                            valueFrom={filters[field.nameFrom]}
                            valueTo={filters[field.nameTo]}
                            label={field.label}
                            onChange={(from, to) => {
                                handleImmediate({
                                    [field.nameFrom]: from,
                                    [field.nameTo]: to,
                                });
                            }}
                            className="w-full md:w-56"
                        />
                    );
                }

                return null;
            })}

            {hasActiveFilters && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClear}
                    className="text-muted-foreground"
                >
                    <X className="mr-1 h-4 w-4" />
                    Limpiar
                </Button>
            )}
        </div>
    );
}
