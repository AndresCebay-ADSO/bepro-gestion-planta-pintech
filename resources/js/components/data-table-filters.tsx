import { Search, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
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
    onChange: (name: string, value: string | undefined) => void;
    onClear: () => void;
};

export function DataTableFilters({
    fields,
    filters,
    onChange,
    onClear,
}: DataTableFiltersProps) {
    const hasActiveFilters = fields.some((field) => {
        if (field.type === 'date-range') {
            return (
                (filters[field.nameFrom] ?? '') !== '' ||
                (filters[field.nameTo] ?? '') !== ''
            );
        }

        return (filters[field.name] ?? '') !== '';
    });

    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
            {fields.map((field) => {
                if (field.type === 'text') {
                    return (
                        <div key={field.name} className="relative flex-1">
                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder={
                                    field.placeholder ?? field.label
                                }
                                value={filters[field.name] ?? ''}
                                onChange={(e) =>
                                    onChange(field.name, e.target.value)
                                }
                                className="pl-9"
                            />
                        </div>
                    );
                }

                if (field.type === 'select') {
                    return (
                        <Select
                            key={field.name}
                            value={filters[field.name] ?? ''}
                            onValueChange={(value) =>
                                onChange(
                                    field.name,
                                    value === '__all__' ? undefined : value,
                                )
                            }
                        >
                            <SelectTrigger className="w-full md:w-52">
                                <SelectValue placeholder={field.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">Todos</SelectItem>
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
                        <div
                            key={`${field.nameFrom}-${field.nameTo}`}
                            className="flex items-center gap-2"
                        >
                            <Input
                                type="date"
                                aria-label={`${field.label} desde`}
                                value={filters[field.nameFrom] ?? ''}
                                max={filters[field.nameTo] ?? undefined}
                                onChange={(e) =>
                                    onChange(field.nameFrom, e.target.value)
                                }
                                className="w-full md:w-40"
                            />
                            <span className="text-sm text-muted-foreground">
                                -
                            </span>
                            <Input
                                type="date"
                                aria-label={`${field.label} hasta`}
                                value={filters[field.nameTo] ?? ''}
                                min={filters[field.nameFrom] ?? undefined}
                                onChange={(e) =>
                                    onChange(field.nameTo, e.target.value)
                                }
                                className="w-full md:w-40"
                            />
                        </div>
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
