import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    suffix?: string;
    required?: boolean;
    error?: string;
    step?: string;
};

export default function NumberField({
    label,
    value,
    onChange,
    placeholder,
    suffix,
    required,
    error,
    step,
}: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <div className="relative">
                <Input
                    type="number"
                    step={step}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                />
                {suffix && (
                    <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                        {suffix}
                    </span>
                )}
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
