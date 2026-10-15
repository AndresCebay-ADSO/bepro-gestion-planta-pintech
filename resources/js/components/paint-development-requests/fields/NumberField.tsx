import { DecimalInput } from '@/components/decimal-input';
import { IntegerInput } from '@/components/integer-input';
import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    suffix?: string;
    required?: boolean;
    error?: string;
    /** Solo enteros (número de manos, pot life en minutos). */
    integer?: boolean;
};

export default function NumberField({
    label,
    value,
    onChange,
    placeholder,
    suffix,
    required,
    error,
    integer,
}: Props) {
    const NumberInput = integer ? IntegerInput : DecimalInput;

    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <div className="relative">
                <NumberInput
                    value={value}
                    onValueChange={onChange}
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
