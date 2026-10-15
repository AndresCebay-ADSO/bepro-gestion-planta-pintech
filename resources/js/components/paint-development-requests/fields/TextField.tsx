import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
    type?: 'text' | 'date' | 'number';
    /** Solo para `type="date"`: el primer día que ofrece el calendario. */
    min?: string;
};

export default function TextField({
    label,
    value,
    onChange,
    placeholder,
    required,
    error,
    type = 'text',
    min,
}: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <Input
                type={type}
                min={min}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
