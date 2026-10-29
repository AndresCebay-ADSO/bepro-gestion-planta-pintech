import { FormField } from '@/components/form-field';

type Props = {
    /** El `id` del campo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder?: string;
    required?: boolean;
    error?: string;
};

export default function SelectField({
    id,
    label,
    value,
    onChange,
    options,
    placeholder = 'Selecciona una opción',
    required,
    error,
}: Props) {
    return (
        <FormField id={id} label={label} required={required} error={error}>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 text-base ring-offset-background outline-none focus:ring-2 focus:ring-ring md:text-sm"
            >
                <option value="">{placeholder}</option>
                {options.map((o) => (
                    <option key={o} value={o}>
                        {o}
                    </option>
                ))}
            </select>
        </FormField>
    );
}
