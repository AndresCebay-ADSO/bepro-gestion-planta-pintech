import { FormField } from '@/components/form-field';

type Props = {
    /** El `id` del grupo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id: string;
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    options: string[];
    required?: boolean;
    error?: string;
};

export default function CheckboxGroup({
    id,
    label,
    values,
    onChange,
    options,
    required,
    error,
}: Props) {
    const toggle = (val: string) => {
        onChange(
            values.includes(val)
                ? values.filter((v) => v !== val)
                : [...values, val],
        );
    };

    return (
        <FormField id={id} label={label} required={required} error={error}>
            {/* `aria-required` no aplica a un grupo de casillas: el asterisco de la etiqueta lo dice. */}
            {(control) => (
                <div
                    {...control}
                    aria-required={undefined}
                    role="group"
                    aria-labelledby={`${control.id}-label`}
                    tabIndex={-1}
                    className="grid grid-cols-1 gap-2 outline-none sm:grid-cols-2 lg:grid-cols-3"
                >
                    {options.map((opt) => (
                        <label
                            key={opt}
                            className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition-colors ${
                                values.includes(opt)
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-input hover:bg-muted/50'
                            }`}
                        >
                            <input
                                type="checkbox"
                                checked={values.includes(opt)}
                                onChange={() => toggle(opt)}
                                className="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                            />
                            {opt}
                        </label>
                    ))}
                </div>
            )}
        </FormField>
    );
}
