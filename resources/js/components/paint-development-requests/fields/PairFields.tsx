import { DecimalInput } from '@/components/decimal-input';
import { Label } from '@/components/ui/label';

type FieldDef = {
    name: string;
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    suffix: string;
    /** Admite valores bajo cero (temperaturas). */
    allowNegative?: boolean;
};

type Props = {
    label: string;
    a: FieldDef;
    b: FieldDef;
    required?: boolean;
    error?: string;
};

function PairInput({ field }: { field: FieldDef }) {
    return (
        <div className="relative">
            <DecimalInput
                allowNegative={field.allowNegative}
                value={field.value}
                onValueChange={field.onChange}
                placeholder={field.placeholder}
            />
            <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                {field.suffix}
            </span>
        </div>
    );
}

export default function PairFields({ label, a, b, required, error }: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <div className="grid grid-cols-2 gap-3">
                <PairInput field={a} />
                <PairInput field={b} />
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
