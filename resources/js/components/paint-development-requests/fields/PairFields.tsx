import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FieldDef = {
    name: string;
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    suffix: string;
};

type Props = {
    label: string;
    a: FieldDef;
    b: FieldDef;
    required?: boolean;
    error?: string;
};

export default function PairFields({ label, a, b, required, error }: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <div className="grid grid-cols-2 gap-3">
                <div className="relative">
                    <Input
                        type="number"
                        value={a.value}
                        onChange={(e) => a.onChange(e.target.value)}
                        placeholder={a.placeholder}
                    />
                    <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                        {a.suffix}
                    </span>
                </div>
                <div className="relative">
                    <Input
                        type="number"
                        value={b.value}
                        onChange={(e) => b.onChange(e.target.value)}
                        placeholder={b.placeholder}
                    />
                    <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                        {b.suffix}
                    </span>
                </div>
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
