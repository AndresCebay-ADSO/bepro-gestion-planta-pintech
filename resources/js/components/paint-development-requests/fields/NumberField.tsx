import { DecimalInput } from '@/components/decimal-input';
import { FormField } from '@/components/form-field';
import { IntegerInput } from '@/components/integer-input';
import { TECHNICAL_NUMBER_LIMITS } from '@/lib/number-types';

type Props = {
    /** El `id` del campo: igual a la llave del error, para que el foco llegue a él ante un 422. */
    id: string;
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
    id,
    label,
    value,
    onChange,
    placeholder,
    suffix,
    required,
    error,
    integer,
}: Props) {
    return (
        <FormField id={id} label={label} required={required} error={error}>
            {(control) => (
                <div className="relative">
                    {integer ? (
                        <IntegerInput
                            {...control}
                            value={value}
                            onValueChange={onChange}
                            placeholder={placeholder}
                        />
                    ) : (
                        <DecimalInput
                            {...control}
                            {...TECHNICAL_NUMBER_LIMITS}
                            value={value}
                            onValueChange={onChange}
                            placeholder={placeholder}
                        />
                    )}
                    {suffix && (
                        <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                            {suffix}
                        </span>
                    )}
                </div>
            )}
        </FormField>
    );
}
