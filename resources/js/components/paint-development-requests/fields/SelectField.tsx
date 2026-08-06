import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder?: string;
    required?: boolean;
    error?: string;
};

export default function SelectField({
    label,
    value,
    onChange,
    options,
    placeholder = 'Selecciona una opción',
    required,
    error,
}: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm ring-offset-background outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="">{placeholder}</option>
                {options.map((o) => (
                    <option key={o} value={o}>
                        {o}
                    </option>
                ))}
            </select>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
