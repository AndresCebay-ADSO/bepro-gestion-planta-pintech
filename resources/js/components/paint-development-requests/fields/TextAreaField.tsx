import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
};

export default function TextAreaField({
    label,
    value,
    onChange,
    placeholder,
    required,
    error,
}: Props) {
    return (
        <div className="space-y-1.5">
            <Label>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            <Textarea
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
