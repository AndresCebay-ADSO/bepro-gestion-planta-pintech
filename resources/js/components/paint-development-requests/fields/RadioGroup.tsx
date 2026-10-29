import { FormField } from '@/components/form-field';

type Props = {
    /** El `id` del grupo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    required?: boolean;
    error?: string;
};

export default function RadioGroup({
    id,
    label,
    value,
    onChange,
    options,
    required,
    error,
}: Props) {
    return (
        <FormField id={id} label={label} required={required} error={error}>
            {(control) => (
                <div
                    {...control}
                    role="radiogroup"
                    aria-labelledby={`${control.id}-label`}
                    tabIndex={-1}
                    className="grid grid-cols-1 gap-2 outline-none sm:grid-cols-2 lg:grid-cols-3"
                >
                    {options.map((opt) => (
                        <label
                            key={opt}
                            className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition-colors ${
                                value === opt
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-input hover:bg-muted/50'
                            }`}
                        >
                            <input
                                type="radio"
                                name={control.id}
                                checked={value === opt}
                                onChange={() => onChange(opt)}
                                className="h-4 w-4 border-input text-primary focus:ring-primary"
                            />
                            {opt}
                        </label>
                    ))}
                </div>
            )}
        </FormField>
    );
}
