import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    options: string[];
    required?: boolean;
    error?: string;
};

export default function CheckboxGroup({
    label,
    values,
    onChange,
    options,
    required,
    error,
}: Props) {
    const toggle = (val: string) => {
        onChange(
            values.includes(val)
                ? values.filter((v) => v !== val)
                : [...values, val],
        );
    };

    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {options.map((opt) => (
                    <label
                        key={opt}
                        className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition-colors ${
                            values.includes(opt)
                                ? 'border-primary bg-primary/5 text-primary'
                                : 'border-input hover:bg-muted/50'
                        }`}
                    >
                        <input
                            type="checkbox"
                            checked={values.includes(opt)}
                            onChange={() => toggle(opt)}
                            className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        {opt}
                    </label>
                ))}
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
