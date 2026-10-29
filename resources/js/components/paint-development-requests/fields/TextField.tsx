import { useId } from 'react';

import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';

type Props = {
    /** El `id` del campo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id?: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
};

export default function TextField({
    id,
    label,
    value,
    onChange,
    placeholder,
    required,
    error,
}: Props) {
    const fallbackId = useId();

    return (
        <FormField
            id={id ?? fallbackId}
            label={label}
            required={required}
            error={error}
        >
            <Input
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
            />
        </FormField>
    );
}
