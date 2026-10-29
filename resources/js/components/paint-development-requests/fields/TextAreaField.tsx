import { FormField } from '@/components/form-field';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    /** El `id` del campo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
};

export default function TextAreaField({
    id,
    label,
    value,
    onChange,
    placeholder,
    required,
    error,
}: Props) {
    return (
        <FormField id={id} label={label} required={required} error={error}>
            <Textarea
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
            />
        </FormField>
    );
}
